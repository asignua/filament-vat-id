<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Support;

/**
 * Checksums of Ukrainian identifiers. Every method expects digits only.
 *
 * @internal
 */
final class UkrainianNumbers
{
    /**
     * ЄДРПОУ: 8 digits. The check digit is the weighted sum of the first seven digits mod 11 (weights 1..7;
     * 7 1 2 3 4 5 6 for numbers 30 000 000 – 60 000 000). A remainder of 10 triggers a second pass with the
     * weights shifted by two (3..9; 9 3 4 5 6 7 8); a second 10 gives 0.
     */
    public static function edrpou(string $number): bool
    {
        if (preg_match('/^\d{8}$/D', $number) !== 1) {
            return false;
        }

        $value = (int) $number;
        $special = $value > 30000000 && $value < 60000000;

        $first = $special ? [7, 1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5, 6, 7];
        $second = $special ? [9, 3, 4, 5, 6, 7, 8] : [3, 4, 5, 6, 7, 8, 9];

        $check = Digits::weighted($number, $first) % 11;

        if ($check === 10) {
            $check = Digits::weighted($number, $second) % 11;

            if ($check === 10) {
                $check = 0;
            }
        }

        return $check === (int) $number[7];
    }

    /**
     * РНОКПП (tax number of an individual): 10 digits, weights -1 5 7 9 4 6 10 5 7, mod 11, mod 10.
     */
    public static function rnokpp(string $number): bool
    {
        if (preg_match('/^\d{10}$/D', $number) !== 1) {
            return false;
        }

        $sum = Digits::weighted($number, [-1, 5, 7, 9, 4, 6, 10, 5, 7]);

        return ((($sum % 11) + 11) % 11) % 10 === (int) $number[9];
    }
}
