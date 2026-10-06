<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Contracts;

use Asignua\FilamentVatId\Data\CompanyData;
use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;

/**
 * A source of company data by tax identifier. Add your own (for example a paid Ukrainian provider) by
 * implementing this interface and listing the class in `config('filament-vat-id.registries')`.
 */
interface CompanyRegistry
{
    /**
     * Whether the registry can look up identifiers of this type for this country (ISO 3166-1 alpha-2, upper case).
     * Must be cheap and must not hit the network.
     */
    public function supports(string $country, TaxIdType $type): bool;

    /**
     * Looks the company up. `$number` is the normalised identifier (digits, EU VAT numbers without the prefix).
     *
     * @throws RegistryUnavailable when the registry could not answer
     *
     * @return CompanyData|null `null` when the registry answered that there is no such company
     */
    public function lookup(string $country, string $number): ?CompanyData;
}
