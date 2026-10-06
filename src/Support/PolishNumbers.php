<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Support;

/**
 * Checksums of Polish identifiers. Every method expects digits only.
 *
 * @internal
 */
final class PolishNumbers
{
    /**
     * NIP: 10 digits, weights 6 5 7 2 3 4 5 6 7, mod 11; a remainder of 10 never occurs in a valid number.
     */
    public static function nip(string $number): bool
    {
        if (preg_match('/^\d{10}$/D', $number) !== 1) {
            return false;
        }

        $check = Digits::weighted($number, [6, 5, 7, 2, 3, 4, 5, 6, 7]) % 11;

        return $check !== 10 && $check === (int) $number[9];
    }

    /**
     * REGON: 9 digits (weights 8 9 2 3 4 5 6 7) or 14 digits (the first nine plus weights 2 4 8 5 0 9 7 3 6 1 2 4 8).
     */
    public static function regon(string $number): bool
    {
        if (preg_match('/^(?:\d{9}|\d{14})$/D', $number) !== 1) {
            return false;
        }

        $nine = substr($number, 0, 9);

        if (Digits::weighted($nine, [8, 9, 2, 3, 4, 5, 6, 7]) % 11 % 10 !== (int) $nine[8]) {
            return false;
        }

        if (strlen($number) === 9) {
            return true;
        }

        return Digits::weighted($number, [2, 4, 8, 5, 0, 9, 7, 3, 6, 1, 2, 4, 8]) % 11 % 10 === (int) $number[13];
    }
}
