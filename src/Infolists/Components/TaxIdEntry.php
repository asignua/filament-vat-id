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

        $this->copyableState(fn (mixed $state): ?string => is_string($state) ? TaxIdValidator::normalize($state, $this->getTaxIdType()) : null);
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

        if (is_string($country) && trim($country) !== '') {
            return strtoupper(trim($country));
        }

        return $this->getTaxIdType()->country();
    }
}
