<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Infolists\Components;

use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Support\TaxIdValidator;
use Closure;
use Filament\Infolists\Components\TextEntry;

/**
 * Shows a tax identifier formatted (`PL 5260250995`, `526-025-09-95`) and copyable.
 *
 *     TaxIdEntry::make('vat_number')->countryField('country')
 *     TaxIdEntry::make('nip')->type(TaxIdType::PlNip)
 */
class TaxIdEntry extends TextEntry
{
    protected TaxIdType|Closure $taxIdType = TaxIdType::EuVat;

    protected string|Closure|null $taxIdCountry = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->copyable();
        $this->copyMessage(fn (): string => __('filament-vat-id::filament-vat-id.entry.copied'));
        $this->copyMessageDuration(1500);

        $this->formatStateUsing(fn (mixed $state): mixed => is_string($state) && trim($state) !== ''
            ? TaxIdValidator::format($state, $this->getTaxIdType(), $this->getTaxIdCountry())
            : $state);

        // What is copied keeps the country prefix the entry displays (a VAT-UE number is useless without it).
        $this->copyableState(function (mixed $state): ?string {
            if (!is_string($state)) {
                return null;
            }

            $type = $this->getTaxIdType();

            if ($type === TaxIdType::EuVat) {
                $parts = TaxIdValidator::splitEuVat($state, $this->getTaxIdCountry());

                if ($parts !== null) {
                    return $parts[0].$parts[1];
                }
            }

            return TaxIdValidator::normalize($state, $type);
        });
    }

    public function type(TaxIdType|Closure $type): static
    {
        return $this->taxIdType($type);
    }

    public function taxIdType(TaxIdType|Closure $type): static
    {
        $this->taxIdType = $type;

        return $this;
    }

    public function getTaxIdType(): TaxIdType
    {
        $type = $this->evaluate($this->taxIdType);

        return $type instanceof TaxIdType ? $type : TaxIdType::EuVat;
    }

    public function country(string|Closure|null $country): static
    {
        $this->taxIdCountry = $country;

        return $this;
    }

    /**
     * Takes the country from another attribute of the record.
     */
    public function countryField(string $field): static
    {
        $this->taxIdCountry = fn (): mixed => data_get($this->getRecord(), $field);

        return $this;
    }

    public function getTaxIdCountry(): ?string
    {
        $country = $this->evaluate($this->taxIdCountry);

        return TaxIdValidator::countryCode($country) ?? $this->getTaxIdType()->country();
    }
}
