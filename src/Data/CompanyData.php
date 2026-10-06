<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Data;

/**
 * What a registry knows about a company. Everything but `name` and `source` may be missing.
 */
final readonly class CompanyData
{
    /**
     * @param list<string>         $bankAccounts IBANs
     * @param array<string, mixed> $raw          the registry's own record (decoded), for anything not mapped here
     */
    public function __construct(
        public string $name,
        public string $source,
        public ?string $address = null,
        public ?string $street = null,
        public ?string $city = null,
        public ?string $postcode = null,
        public ?string $country = null,
        public ?string $vatNumber = null,
        public ?string $regon = null,
        public ?string $ico = null,
        public ?string $registryId = null,
        public ?bool $active = null,
        public ?string $status = null,
        public array $bankAccounts = [],
        public array $raw = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (string $key): ?string => isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : null;

        return new self(
            name: (string) ($data['name'] ?? ''),
            source: (string) ($data['source'] ?? ''),
            address: $string('address'),
            street: $string('street'),
            city: $string('city'),
            postcode: $string('postcode'),
            country: $string('country'),
            vatNumber: $string('vatNumber'),
            regon: $string('regon'),
            ico: $string('ico'),
            registryId: $string('registryId'),
            active: isset($data['active']) ? (bool) $data['active'] : null,
            status: $string('status'),
            bankAccounts: array_values(array_map('strval', (array) ($data['bankAccounts'] ?? []))),
            raw: (array) ($data['raw'] ?? []),
        );
    }

    /**
     * A value by property path: `name`, `address`, `bankAccounts.0`. `null` when empty or missing.
     */
    public function get(string $path): mixed
    {
        $value = data_get($this->toArray(), $path);

        return $value === '' ? null : $value;
    }
}
