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

        return $value;
    }

    /**
     * Splits an EU VAT number into [VIES prefix, national part]; the prefix is taken from the number, falling
     * back to `$country`. Returns `null` when no known country can be determined or the two disagree.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function splitEuVat(string $value, ?string $country = null): ?array
    {
        $value = self::normalize($value, TaxIdType::EuVat);
        $given = $country !== null && trim($country) !== '' ? EuVatNumber::prefix($country) : null;

        if (strlen($value) >= 2 && !ctype_digit($value[0]) && EuVatNumber::prefix(substr($value, 0, 2)) !== null) {
            $prefix = (string) EuVatNumber::prefix(substr($value, 0, 2));

            // A prefix that disagrees with the chosen country is an error, not a silent override.
            if ($given !== null && $given !== $prefix) {
                return null;
            }

            return [$prefix, substr($value, 2)];
        }

        return $given === null ? null : [$given, $value];
    }

    public static function isValid(string $value, TaxIdType $type, ?string $country = null): bool
    {
        if (trim($value) === '') {
            return false;
        }

        if ($type === TaxIdType::EuVat) {
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
