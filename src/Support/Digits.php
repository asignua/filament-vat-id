<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Support;

/**
 * Small arithmetic helpers shared by the checksum algorithms. Every method takes a string of digits.
 *
 * @internal
 */
final class Digits
{
    /**
     * Sum of digit × weight over the leading digits (as many as there are weights).
     *
     * @param list<int> $weights
     */
    public static function weighted(string $digits, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $i => $weight) {
            $sum += (int) $digits[$i] * $weight;
        }

        return $sum;
    }

    /**
     * The Luhn check over the whole string (the last digit is the check digit).
     */
    public static function luhn(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];

            if ($double) {
                $d *= 2;
                $d = intdiv($d, 10) + $d % 10;
            }

            $sum += $d;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }

    /**
     * ISO 7064 Mod 11,10 over the whole string (used by DE and HR); valid when the final product is 2
     * (the check digit c = (11 − product) mod 10 always leaves the running product at 2).
     */
    public static function iso7064Mod1110(string $digits): bool
    {
        $product = 10;

        for ($i = 0, $n = strlen($digits); $i < $n; $i++) {
            $sum = ((int) $digits[$i] + $product) % 10;
            $product = (($sum === 0 ? 10 : $sum) * 2) % 11;
        }

        return $product === 2;
    }

    /**
     * The mod 11 check digit with the "10 becomes 0" convention: 11 − (sum mod 11), where 10 and 11 map to 0 or
     * 1 through `% 10`.
     */
    public static function mod11Check(int $sum): int
    {
        return (11 - $sum % 11) % 10;
    }
}
