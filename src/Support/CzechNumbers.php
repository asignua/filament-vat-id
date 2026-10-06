<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Support;

/**
 * Checksums of Czech identifiers. Every method expects digits only.
 *
 * @internal
 */
final class CzechNumbers
{
    /**
     * IČO: 8 digits, weights 8 7 6 5 4 3 2 on the first seven, mod 11.
     */
    public static function ico(string $number): bool
    {
        if (preg_match('/^\d{8}$/D', $number) !== 1) {
            return false;
        }

        $sum = Digits::weighted($number, [8, 7, 6, 5, 4, 3, 2]);

        return (11 - $sum % 11) % 10 === (int) $number[7];
    }

    /**
     * DIČ (the part after `CZ`): legal entities use the IČO (8 digits, never starting with 9); individuals use
     * the birth number (9 digits before 1954, 10 digits since, divisible by 11); special cases start with 6
     * (9 digits) and are checked by format only.
     */
    public static function dic(string $number): bool
    {
        return match (strlen($number)) {
            8 => $number[0] !== '9' && self::ico($number),
            9 => $number[0] === '6' || (self::birthDate($number) && (int) substr($number, 0, 2) < 54),
            10 => self::birthDate($number) && self::birthNumberChecksum($number),
            default => false,
        };
    }

    private static function birthDate(string $number): bool
    {
        if (!ctype_digit($number)) {
            return false;
        }

        $month = (int) substr($number, 2, 2);
        $day = (int) substr($number, 4, 2);

        // +50 for women, +20 for the extra range used since 2004.
        $validMonth = in_array($month, range(1, 12), true)
            || in_array($month, range(21, 32), true)
            || in_array($month, range(51, 62), true)
            || in_array($month, range(71, 82), true);

        return $validMonth && $day >= 1 && $day <= 31;
    }

    private static function birthNumberChecksum(string $number): bool
    {
        $remainder = (int) substr($number, 0, 9) % 11;

        return ($remainder === 10 ? 0 : $remainder) === (int) $number[9];
    }
}
