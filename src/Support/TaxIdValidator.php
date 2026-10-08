<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Support;

use Asignua\FilamentVatId\Enums\TaxIdType;

/**
 * Offline validation of tax identifiers: format plus checksum. No network is involved.
 *
 *     TaxIdValidator::isValid('PL 526-025-09-95', TaxIdType::EuVat);
 *     TaxIdValidator::isValid('5260250995', TaxIdType::PlNip);
 *     TaxIdValidator::isValid('45274649', TaxIdType::EuVat, 'CZ'); // country given separately
 */
final class TaxIdValidator
{
    /**
     * Strips separators (spaces, dots, dashes, slashes), upper-cases, and for the country-bound types drops an
     * optional country prefix (`PL`, `CZ`, `UA`). EU VAT numbers keep their prefix.
     */
    public static function normalize(string $value, TaxIdType $type): string
    {
        $value = strtoupper((string) preg_replace('/[\s.\-\/]+/u', '', trim($value)));

        $prefix = $type->country();

        if ($prefix !== null && str_starts_with($value, $prefix) && !ctype_digit($value)) {
            $value = substr($value, 2);
        }

        // People drop the leading zeros of a ЄДРПОУ (it is always 8 digits).
        if ($type === TaxIdType::UaEdrpou && preg_match('/^\d{5,7}$/D', $value) === 1) {
            $value = str_pad($value, 8, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    /**
     * A country as a form state or a record attribute hands it over: a string, or an enum (a Select backed by an
     * enum, an enum cast on the model) / Stringable. Returns the upper-cased trimmed code, `null` when blank.
     */
    public static function countryCode(mixed $country): ?string
    {
        if ($country instanceof \BackedEnum) {
            $country = (string) $country->value;
        } elseif ($country instanceof \UnitEnum) {
            $country = $country->name;
        } elseif ($country instanceof \Stringable) {
            $country = (string) $country;
        }

        return is_string($country) && trim($country) !== '' ? strtoupper(trim($country)) : null;
    }

    /**
     * Splits an EU VAT number into [VIES prefix, national part]; the prefix is taken from the number, falling
     * back to `$country`. When the country is known it wins: a value without that prefix is a national number as it
     * stands. Returns `null` when no country can be determined.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function splitEuVat(string $value, ?string $country = null): ?array
    {
        $value = self::normalize($value, TaxIdType::EuVat);
        $given = $country !== null && trim($country) !== '' ? EuVatNumber::prefix($country) : null;

        // The country is known: the number either carries exactly that prefix or has none. Anything else is taken as
        // a national number as it is (a French key made of letters must not be read as another country's prefix).
        if ($given !== null) {
            // Greece is EL in VIES but GR in ISO: a number typed with the ISO prefix is still that country's.
            $prefixed = str_starts_with($value, $given) || ($given === 'EL' && str_starts_with($value, 'GR'));

            return [$given, $prefixed ? substr($value, 2) : $value];
        }

        if (strlen($value) >= 2 && !ctype_digit($value[0]) && ($prefix = EuVatNumber::prefix(substr($value, 0, 2))) !== null) {
            return [$prefix, substr($value, 2)];
        }

        return null;
    }

    /**
     * Whether the chosen country can have EU VAT numbers at all. A non-EU country (UA, US…) given for an EU VAT
     * field means "nothing to validate": `isValid()` passes such a value.
     */
    public static function isEuCountry(?string $country): bool
    {
        return $country === null || trim($country) === '' || EuVatNumber::prefix($country) !== null;
    }

    public static function isValid(string $value, TaxIdType $type, ?string $country = null): bool
    {
        if (trim($value) === '') {
            return false;
        }

        if ($type === TaxIdType::EuVat) {
            // A non-EU country: not an EU VAT number, nothing to check.
            if (!self::isEuCountry($country)) {
                return true;
            }

            $parts = self::splitEuVat($value, $country);

            return $parts !== null && EuVatNumber::isValid($parts[0], $parts[1]);
        }

        $number = self::normalize($value, $type);

        return match ($type) {
            TaxIdType::PlNip => PolishNumbers::nip($number),
            TaxIdType::PlRegon => PolishNumbers::regon($number),
            TaxIdType::CzIco => CzechNumbers::ico($number),
            TaxIdType::CzDic => CzechNumbers::dic($number),
            TaxIdType::UaEdrpou => UkrainianNumbers::edrpou($number),
            default => UkrainianNumbers::rnokpp($number), // UaRnokpp (EuVat returned above)
        };
    }

    /**
     * The value as it is shown to people: `PL 5260250995`, `526-025-09-95`, `45274649`.
     * An invalid or unparsable value is returned trimmed, untouched.
     */
    public static function format(string $value, TaxIdType $type, ?string $country = null): string
    {
        if ($type === TaxIdType::EuVat) {
            $parts = self::splitEuVat($value, $country);

            return $parts === null ? trim($value) : $parts[0].' '.$parts[1];
        }

        $number = self::normalize($value, $type);

        if ($type === TaxIdType::PlNip && preg_match('/^(\d{3})(\d{3})(\d{2})(\d{2})$/D', $number, $m) === 1) {
            return $m[1].'-'.$m[2].'-'.$m[3].'-'.$m[4];
        }

        if ($type === TaxIdType::CzDic) {
            return 'CZ'.$number;
        }

        return $number === '' ? trim($value) : $number;
    }
}
