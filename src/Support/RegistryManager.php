<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Support;

use Asignua\FilamentVatId\Contracts\CompanyRegistry;
use Asignua\FilamentVatId\Data\CompanyData;
use Asignua\FilamentVatId\Data\Verification;
use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Enums\VerificationStatus;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;

/**
 * Picks the registries for a country and an identifier type (in the configured order), caches the answers and
 * applies the `on_unavailable` policy.
 */
class RegistryManager
{
    /** @var list<class-string<CompanyRegistry>|CompanyRegistry> */
    protected array $extra = [];

    /** @var list<class-string<CompanyRegistry>|CompanyRegistry> */
    protected array $prepended = [];

    public function __construct(protected Container $container) {}

    /**
     * Adds a registry at runtime, after the configured ones (or before them with `$first`).
     *
     * @param class-string<CompanyRegistry>|CompanyRegistry $registry
     */
    public function extend(CompanyRegistry|string $registry, bool $first = false): static
    {
        if ($first) {
            $this->prepended[] = $registry;
        } else {
            $this->extra[] = $registry;
        }

        return $this;
    }

    /**
     * @return list<CompanyRegistry>
     */
    public function registries(): array
    {
        $configured = config('filament-vat-id.registries', []);

        $items = [...$this->prepended, ...(is_array($configured) ? $configured : []), ...$this->extra];
        $registries = [];

        foreach ($items as $item) {
            $registry = is_string($item) ? $this->container->make($item) : $item;

            if ($registry instanceof CompanyRegistry) {
                $registries[] = $registry;
            }
        }

        return $registries;
    }

    /**
     * @return list<CompanyRegistry>
     */
    public function registriesFor(string $country, TaxIdType $type, ?string $only = null): array
    {
        $country = self::country($country);

        return array_values(array_filter(
            $this->registries(),
            static fn (CompanyRegistry $registry): bool => ($only === null || $registry instanceof $only) && $registry->supports($country, $type),
        ));
    }

    public function supports(string $country, TaxIdType $type, ?string $only = null): bool
    {
        return $this->registriesFor($country, $type, $only) !== [];
    }

    /**
     * Looks a company up. The first registry (in the configured order) that knows it wins.
     *
     * @param string|null $only restrict to this registry class
     *
     * @throws RegistryUnavailable when nobody found it and at least one registry could not answer
     *
     * @return CompanyData|null `null` when no supporting registry knows the number (or none supports it)
     */
    public function lookup(string $country, TaxIdType $type, string $number, ?string $only = null): ?CompanyData
    {
        $resolved = $this->resolve($country, $type, $number);

        if ($resolved === null) {
            return null;
        }

        [$country, $national] = $resolved;

        $unavailable = null;

        foreach ($this->registriesFor($country, $type, $only) as $registry) {
            try {
                $company = $this->cached($registry, $country, $national);
            } catch (RegistryUnavailable $e) {
                $unavailable ??= $e;

                continue;
            }

            if ($company !== null) {
                return $company;
            }
        }

        if ($unavailable !== null) {
            throw $unavailable;
        }

        return null;
    }

    /**
     * Like `lookup()`, but never throws: the outcome is a Verification. The `on_unavailable` policy is applied by
     * the caller through {@see self::onUnavailable()}.
     */
    public function verify(string $country, TaxIdType $type, string $number, ?string $only = null): Verification
    {
        $resolved = $this->resolve($country, $type, $number);

        if ($resolved === null || !$this->supports($resolved[0], $type, $only)) {
            return new Verification(VerificationStatus::Skipped);
        }

        try {
            $company = $this->lookup($country, $type, $number, $only);
        } catch (RegistryUnavailable $e) {
            return new Verification(VerificationStatus::Unavailable, reason: $e->getMessage());
        }

        return $company === null
            ? new Verification(VerificationStatus::NotFound)
            : new Verification(VerificationStatus::Verified, $company);
    }

    /**
     * `allow`, `warn` or `fail`.
     */
    public function onUnavailable(): string
    {
        $mode = config('filament-vat-id.on_unavailable', 'warn');

        return in_array($mode, ['allow', 'warn', 'fail'], true) ? $mode : 'warn';
    }

    /**
     * The company already in the cache for this identifier, from any registry: never calls the network.
     */
    public function cachedCompany(string $country, TaxIdType $type, string $number): ?CompanyData
    {
        $resolved = $this->resolve($country, $type, $number);

        if ($resolved === null) {
            return null;
        }

        foreach ($this->registriesFor($resolved[0], $type) as $registry) {
            $hit = $this->store()->get($this->cacheKey($registry, $resolved[0], $resolved[1]));

            if (is_array($hit) && is_array($hit['data'] ?? null)) {
                return CompanyData::fromArray($hit['data']);
            }
        }

        return null;
    }

    /**
     * Resolves the ISO country and the national number a registry expects.
     *
     * @return array{0: string, 1: string}|null
     */
    public function resolve(string $country, TaxIdType $type, string $number): ?array
    {
        if ($type === TaxIdType::EuVat) {
            $parts = TaxIdValidator::splitEuVat($number, $country);

            return $parts === null ? null : [self::country($parts[0]), $parts[1]];
        }

        $fixed = $type->country();
        $national = TaxIdValidator::normalize($number, $type);

        return $fixed === null || $national === '' ? null : [$fixed, $national];
    }

    protected function cached(CompanyRegistry $registry, string $country, string $number): ?CompanyData
    {
        if (config('filament-vat-id.cache.enabled', true) !== true) {
            return $registry->lookup($country, $number);
        }

        $key = $this->cacheKey($registry, $country, $number);
        $store = $this->store();
        $hit = $store->get($key);

        if (is_array($hit) && array_key_exists('data', $hit)) {
            return is_array($hit['data']) ? CompanyData::fromArray($hit['data']) : null;
        }

        $company = $registry->lookup($country, $number);

        $ttl = (int) config('filament-vat-id.cache.ttl', 3600);

        if ($ttl > 0) {
            $store->put($key, ['data' => $company?->toArray()], $ttl);
        }

        return $company;
    }

    protected function cacheKey(CompanyRegistry $registry, string $country, string $number): string
    {
        return $this->prefix().':lookup:'.sha1($registry::class.'|'.$country.'|'.$number);
    }

    protected function prefix(): string
    {
        $prefix = config('filament-vat-id.cache.prefix', 'filament-vat-id');

        return is_string($prefix) && $prefix !== '' ? $prefix : 'filament-vat-id';
    }

    protected function store(): Repository
    {
        $store = config('filament-vat-id.cache.store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }

    /**
     * Registry-side country code: ISO alpha-2, so Greece is `GR` (the validator and VIES use `EL`).
     */
    protected static function country(string $country): string
    {
        $country = strtoupper(trim($country));

        return $country === 'EL' ? 'GR' : $country;
    }
}
