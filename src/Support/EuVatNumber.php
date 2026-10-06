<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Support;

/**
 * Offline validation of the national part of an EU VAT number (without the country prefix): the format of
 * every member state (+ `XI`, Northern Ireland) and, where the algorithm is public and well documented, the
 * checksum. Bulgaria and Cyprus are checked by format only.
 *
 * Countries use the VIES prefixes: Greece is `EL` (not `GR`).
 *
 * @internal
 */
final class EuVatNumber
{
    /** @var array<string, string> */
    private const array PATTERNS = [
        'AT' => 'U\d{8}',
        'BE' => '[01]\d{9}',
        'BG' => '\d{9,10}',
        'CY' => '\d{8}[A-Z]',
        'CZ' => '\d{8,10}',
        'DE' => '\d{9}',
        'DK' => '\d{8}',
        'EE' => '\d{9}',
        'EL' => '\d{9}',
        'ES' => '[A-Z0-9]\d{7}[A-Z0-9]',
        'FI' => '\d{8}',
        'FR' => '[A-Z0-9]{2}\d{9}',
        'HR' => '\d{11}',
        'HU' => '\d{8}',
        'IE' => '(?:\d{7}[A-W][A-IW]?|\d[A-Z+*]\d{5}[A-W])',
        'IT' => '\d{11}',
        'LT' => '(?:\d{9}|\d{12})',
        'LU' => '\d{8}',
        'LV' => '\d{11}',
        'MT' => '\d{8}',
        'NL' => '\d{9}B\d{2}',
        'PL' => '\d{10}',
        'PT' => '\d{9}',
        'RO' => '\d{2,10}',
        'SE' => '\d{12}',
        'SI' => '\d{8}',
        'SK' => '\d{10}',
        'XI' => '(?:\d{9}|\d{12}|GD\d{3}|HA\d{3})',
    ];

    /**
     * @return list<string>
     */
    public static function countries(): array
    {
        return array_keys(self::PATTERNS);
    }

    /**
     * Maps an ISO 3166-1 country code to the VIES prefix (`GR` → `EL`); `null` for a non-EU country.
     */
    public static function prefix(string $country): ?string
    {
        $country = strtoupper(trim($country));

        if ($country === 'GR') {
            $country = 'EL';
        }

        return isset(self::PATTERNS[$country]) ? $country : null;
    }

    public static function isValid(string $country, string $number): bool
    {
        $country = self::prefix($country);

        if ($country === null || preg_match('/^'.self::PATTERNS[$country].'$/D', $number) !== 1) {
            return false;
        }

        return match ($country) {
            'AT' => self::at($number),
            'BE' => self::be($number),
            'CZ' => CzechNumbers::dic($number),
            'DE' => Digits::iso7064Mod1110($number),
            'DK' => Digits::weighted($number, [2, 7, 6, 5, 4, 3, 2, 1]) % 11 === 0,
            'EE' => (10 - Digits::weighted($number, [3, 7, 1, 3, 7, 1, 3, 7]) % 10) % 10 === (int) $number[8],
            'EL' => Digits::weighted($number, [256, 128, 64, 32, 16, 8, 4, 2]) % 11 % 10 === (int) $number[8],
            'ES' => self::es($number),
            'FI' => self::fi($number),
            'FR' => self::fr($number),
            'HR' => Digits::iso7064Mod1110($number),
            'HU' => Digits::weighted($number, [9, 7, 3, 1, 9, 7, 3, 1]) % 10 === 0,
            'IE' => self::ie($number),
            'IT' => self::it($number),
            'LT' => self::lt($number),
            'LU' => (int) substr($number, 0, 6) % 89 === (int) substr($number, 6),
            'LV' => self::lv($number),
            'MT' => self::mt($number),
            'NL' => self::nl($number),
            'PL' => PolishNumbers::nip($number),
            'PT' => self::pt($number),
            'RO' => self::ro($number),
            'SE' => str_ends_with($number, '01') && Digits::luhn(substr($number, 0, 10)),
            'SI' => self::si($number),
            'SK' => $number[0] !== '0' && in_array($number[2], ['2', '3', '4', '7', '8', '9'], true) && (int) $number % 11 === 0,
            'XI' => self::gb($number),
            default => true, // BG, CY: format only
        };
    }

    private static function at(string $number): bool
    {
        $sum = 0;

        foreach ([1, 2, 1, 2, 1, 2, 1] as $i => $weight) {
            $product = (int) $number[$i + 1] * $weight;
            $sum += intdiv($product, 10) + $product % 10;
        }

        return (96 - $sum) % 10 === (int) $number[8];
    }

    private static function be(string $number): bool
    {
        return 97 - (int) substr($number, 0, 8) % 97 === (int) substr($number, 8);
    }

    private static function es(string $number): bool
    {
        $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $first = $number[0];
        $last = $number[8];

        // NIF (DNI): 8 digits + control letter.
        if (ctype_digit($first)) {
            return ctype_digit(substr($number, 0, 8)) && $letters[(int) substr($number, 0, 8) % 23] === $last;
        }

        // NIE: X/Y/Z + 7 digits + control letter.
        if (in_array($first, ['X', 'Y', 'Z'], true)) {
            $digits = substr($number, 1, 7);

            return ctype_digit($digits) && $letters[(int) (strpos('XYZ', $first).$digits) % 23] === $last;
        }

        // Special NIFs (K, L, M): control letter like a DNI over the 7 digits.
        if (in_array($first, ['K', 'L', 'M'], true)) {
            return ctype_digit(substr($number, 1, 7)) && $letters[(int) substr($number, 1, 7) % 23] === $last;
        }

        // CIF: organisation letter + 7 digits + control digit or letter.
        if (!str_contains('ABCDEFGHJNPQRSUVW', $first) || !ctype_digit(substr($number, 1, 7))) {
            return false;
        }

        $sum = 0;

        for ($i = 1; $i <= 7; $i++) {
            $d = (int) $number[$i];

            if ($i % 2 === 1) {
                $d *= 2;
                $d = intdiv($d, 10) + $d % 10;
            }

            $sum += $d;
        }

        $control = (10 - $sum % 10) % 10;
        $asLetter = 'JABCDEFGHI'[$control];

        if (str_contains('PQRSNW', $first)) {
            return $last === $asLetter;
        }

        if (str_contains('ABEH', $first)) {
            return $last === (string) $control;
        }

        return $last === (string) $control || $last === $asLetter;
    }

    private static function fi(string $number): bool
    {
        $check = 11 - Digits::weighted($number, [7, 9, 10, 5, 8, 4, 2]) % 11;

        return $check !== 10 && $check % 11 === (int) $number[7];
    }

    private static function fr(string $number): bool
    {
        $key = substr($number, 0, 2);

        // A letter in the key (the newer scheme) has no public checksum: format only.
        if (!ctype_digit($key) || !ctype_digit(substr($number, 2))) {
            return true;
        }

        return (12 + 3 * ((int) substr($number, 2) % 97)) % 97 === (int) $key;
    }

    private static function ie(string $number): bool
    {
        // Old format 1S12345L: the second character is a letter; shift it to the new layout.
        if (!ctype_digit($number[1]) && ctype_digit($number[0]) && strlen($number) === 8) {
            $number = '0'.substr($number, 2, 5).$number[0].$number[7];
        }

        if (!ctype_digit(substr($number, 0, 7))) {
            return false;
        }

        $sum = Digits::weighted($number, [8, 7, 6, 5, 4, 3, 2]);

        if (isset($number[8])) {
            $sum += $number[8] === 'W' ? 0 : (ord($number[8]) - 64) * 9;
        }

        $remainder = $sum % 23;
        $expected = $remainder === 0 ? 'W' : chr(64 + $remainder);

        return $number[7] === $expected;
    }

    private static function it(string $number): bool
    {
        $office = (int) substr($number, 7, 3);

        return substr($number, 0, 7) !== '0000000'
            && (($office >= 1 && $office <= 100) || in_array($office, [120, 121, 888, 999], true))
            && Digits::luhn($number);
    }

    private static function lt(string $number): bool
    {
        $length = strlen($number);

        // 9 digits: legal entity (8th digit 1); 12 digits: temporarily registered payer (11th digit 1).
        if ($number[$length - 2] !== '1') {
            return false;
        }

        $body = substr($number, 0, -1);
        $sum = 0;

        foreach (str_split($body) as $i => $digit) {
            $sum += (int) $digit * (1 + $i % 9);
        }

        $check = $sum % 11;

        if ($check === 10) {
            $sum = 0;

            foreach (str_split($body) as $i => $digit) {
                $sum += (int) $digit * (1 + ($i + 2) % 9);
            }

            $check = $sum % 11;
        }

        return $check % 10 === (int) $number[$length - 1];
    }

    private static function lv(string $number): bool
    {
        // Natural persons (first digit 0-3) carry a birth date, no checksum.
        if ((int) $number[0] <= 3) {
            return true;
        }

        $sum = Digits::weighted($number, [9, 1, 4, 8, 3, 10, 2, 5, 7, 6]);

        // The documented special case (remainder 4 with a leading 9) is accepted without a checksum.
        if ($sum % 11 === 4 && $number[0] === '9') {
            return true;
        }

        $check = 3 - $sum % 11;

        if ($check < 0) {
            $check += 11;
        }

        return $check < 10 && $check === (int) $number[10];
    }

    private static function mt(string $number): bool
    {
        if ($number[0] === '0') {
            return false;
        }

        return 37 - Digits::weighted($number, [3, 4, 6, 7, 8, 9]) % 37 === (int) substr($number, 6);
    }

    private static function nl(string $number): bool
    {
        $digits = substr($number, 0, 9);

        // The classic "11-proef".
        $sum = Digits::weighted($digits, [9, 8, 7, 6, 5, 4, 3, 2]) - (int) $digits[8];

        if ($sum % 11 === 0) {
            return true;
        }

        // Sole proprietors since 2020: ISO 7064 mod 97-10 over "NL" + the number.
        $numeric = '2321'.$digits.'11'.substr($number, 10);

        return self::mod97($numeric) === 1;
    }

    private static function pt(string $number): bool
    {
        $check = 11 - Digits::weighted($number, [9, 8, 7, 6, 5, 4, 3, 2]) % 11;

        return ($check >= 10 ? 0 : $check) === (int) $number[8];
    }

    private static function ro(string $number): bool
    {
        $padded = str_pad($number, 10, '0', STR_PAD_LEFT);
        $check = Digits::weighted($padded, [7, 5, 3, 2, 1, 7, 5, 3, 2]) * 10 % 11;

        return ($check === 10 ? 0 : $check) === (int) $padded[9];
    }

    private static function si(string $number): bool
    {
        if ($number[0] === '0') {
            return false;
        }

        $check = 11 - Digits::weighted($number, [8, 7, 6, 5, 4, 3, 2]) % 11;

        return ($check === 10 ? 0 : $check) === (int) $number[7];
    }

    private static function gb(string $number): bool
    {
        // Government (GD) and health authority (HA) numbers: GD 000-499, HA 500-999.
        if (str_starts_with($number, 'GD')) {
            return (int) substr($number, 2) < 500;
        }

        if (str_starts_with($number, 'HA')) {
            return (int) substr($number, 2) >= 500;
        }

        $sum = Digits::weighted($number, [8, 7, 6, 5, 4, 3, 2]);
        $check = (int) substr($number, 7, 2);

        return ($sum + $check) % 97 === 0 || ($sum + $check + 55) % 97 === 0;
    }

    private static function mod97(string $digits): int
    {
        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder;
    }
}
