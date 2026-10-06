<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Enums;

/**
 * The kind of identifier a value is validated as. `EuVat` covers the VAT numbers of all 27 member states
 * plus Northern Ireland (`XI`); the country comes from the number's prefix or from the field's country.
 */
enum TaxIdType: string
{
    case EuVat = 'eu_vat';
    case PlNip = 'pl_nip';
    case PlRegon = 'pl_regon';
    case CzIco = 'cz_ico';
    case CzDic = 'cz_dic';
    case UaEdrpou = 'ua_edrpou';
    case UaRnokpp = 'ua_rnokpp';

    /**
     * The fixed country of the type (ISO 3166-1 alpha-2), `null` for EU VAT numbers.
     */
    public function country(): ?string
    {
        return match ($this) {
            self::EuVat => null,
            self::PlNip, self::PlRegon => 'PL',
            self::CzIco, self::CzDic => 'CZ',
            self::UaEdrpou, self::UaRnokpp => 'UA',
        };
    }

    public function label(): string
    {
        return __('filament-vat-id::filament-vat-id.types.'.$this->value);
    }
}
