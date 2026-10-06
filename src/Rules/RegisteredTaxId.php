<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Rules;

use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Enums\VerificationStatus;
use Asignua\FilamentVatId\Support\RegistryManager;
use Asignua\FilamentVatId\Support\TaxIdValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The identifier must be known to an authoritative registry: VIES for EU VAT numbers (a domestic register does not
 * prove a VAT-UE registration), ARES for Czech IČO / DIČ, GUS for Polish NIP / REGON (needs a key). Calls the
 * network, so add it after the offline {@see TaxId} rule. A blank value, a value that fails the offline check, a
 * type without such a registry (Ukraine, Polish numbers without a GUS key, a Czech birth-number DIČ) and a non-EU
 * country for an EU VAT field all pass here. A company the registry marks inactive (closed, ended) is rejected.
 *
 * When no registry can answer, the `on_unavailable` config decides: `allow` accepts, `warn` accepts and calls the
 * `$onWarning` callback, `fail` rejects.
 *
 * @phpstan-consistent-constructor
 */
class RegisteredTaxId implements ValidationRule
{
    /**
     * @param Closure(): (string|null)|string|null $country
     * @param (Closure(string $number): void)|null $onWarning
     */
    public function __construct(
        protected TaxIdType $type = TaxIdType::EuVat,
        protected string|Closure|null $country = null,
        protected ?string $registry = null,
        protected ?Closure $onWarning = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!(is_string($value) || is_int($value)) || trim((string) $value) === '') {
            return;
        }

        $country = $this->country instanceof Closure ? ($this->country)() : $this->country;
        $country = is_string($country) ? $country : null;

        if (!TaxIdValidator::isValid((string) $value, $this->type, $country)) {
            return;
        }

        $manager = app(RegistryManager::class);
        $verification = $manager->verify($country ?? '', $this->type, (string) $value, $this->registry);

        match ($verification->status) {
            VerificationStatus::NotFound => $fail('filament-vat-id::filament-vat-id.validation.not_registered')->translate(['type' => $this->type->label()]),
            VerificationStatus::Inactive => $fail('filament-vat-id::filament-vat-id.validation.inactive')->translate(['type' => $this->type->label()]),
            VerificationStatus::Unavailable => $this->unavailable($manager->onUnavailable(), (string) $value, $fail),
            default => null,
        };
    }

    private function unavailable(string $mode, string $value, Closure $fail): void
    {
        if ($mode === 'fail') {
            $fail('filament-vat-id::filament-vat-id.validation.unavailable')->translate();

            return;
        }

        if ($mode === 'warn' && $this->onWarning !== null) {
            ($this->onWarning)($value);
        }
    }
}
