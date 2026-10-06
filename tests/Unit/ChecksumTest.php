<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Tests\Unit;

use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Support\EuVatNumber;
use Asignua\FilamentVatId\Support\TaxIdValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChecksumTest extends TestCase
{
    /**
     * Valid EU VAT numbers with the index of a digit whose change must break the checksum.
     * PL, CZ and the registry-checked ones were confirmed against VIES / ARES / the MF white list on 2026-10-06;
     * the rest are the documented sample numbers of each national scheme.
     *
     * @return array<string, array{string, int}>
     */
    public static function euVat(): array
    {
        return [
            'AT' => ['ATU13585627', 10],
            'BE' => ['BE0428759497', 11],
            'DE' => ['DE136695976', 10],
            'DK' => ['DK13585628', 9],
            'EE' => ['EE100931558', 10],
            'EL' => ['EL094259216', 10],
            'ES CIF' => ['ESA82018474', 10],
            'ES NIF' => ['ES54362315K', 4],
            'FI' => ['FI09853608', 9],
            'FR' => ['FR40303265045', 12],
            'HR' => ['HR33392005961', 12],
            'HU' => ['HU12892312', 9],
            'IE' => ['IE6433435F', 4],
            'IT' => ['IT00743110157', 12],
            'LT 9' => ['LT119511515', 10],
            'LT 12' => ['LT290061371314', 8],
            'LU' => ['LU15027442', 9],
            'LV' => ['LV40003521600', 12],
            'MT' => ['MT11679112', 9],
            'NL' => ['NL004495445B01', 5],
            'PL' => ['PL5260250995', 11],
            'PT' => ['PT501964843', 10],
            'RO' => ['RO18547290', 9],
            'SE' => ['SE556188840401', 6],
            'SI' => ['SI50223054', 9],
            'SK' => ['SK2022749619', 11],
            'CZ' => ['CZ45274649', 9],
            'XI' => ['XI980780684', 10],
        ];
    }

    #[DataProvider('euVat')]
    public function test_valid_eu_vat_numbers_pass(string $number, int $index): void
    {
        $this->assertTrue(TaxIdValidator::isValid($number, TaxIdType::EuVat), $number);
    }

    #[DataProvider('euVat')]
    public function test_mutated_eu_vat_numbers_fail(string $number, int $index): void
    {
        $this->assertFalse(TaxIdValidator::isValid(self::mutate($number, $index), TaxIdType::EuVat), $number.' mutated at '.$index);
    }

    public function test_prefix_is_optional_when_the_country_is_given(): void
    {
        $this->assertTrue(TaxIdValidator::isValid('5260250995', TaxIdType::EuVat, 'PL'));
        $this->assertTrue(TaxIdValidator::isValid('PL 526-025-09-95', TaxIdType::EuVat, 'pl'));
        $this->assertFalse(TaxIdValidator::isValid('5260250995', TaxIdType::EuVat), 'no country, no prefix');
        $this->assertFalse(TaxIdValidator::isValid('PL5260250995', TaxIdType::EuVat, 'CZ'), 'prefix contradicts the country');
        $this->assertTrue(TaxIdValidator::isValid('094259216', TaxIdType::EuVat, 'GR'), 'GR is the ISO code of EL');
    }

    public function test_every_member_state_has_a_format(): void
    {
        $this->assertSame(
            ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI'],
            collect(EuVatNumber::countries())->sort()->values()->all(),
        );
    }

    public function test_format_only_countries_check_the_format(): void
    {
        $this->assertTrue(TaxIdValidator::isValid('BG175074752', TaxIdType::EuVat));
        $this->assertTrue(TaxIdValidator::isValid('BG7523169263', TaxIdType::EuVat));
        $this->assertFalse(TaxIdValidator::isValid('BG12345', TaxIdType::EuVat));
        $this->assertTrue(TaxIdValidator::isValid('CY10259033P', TaxIdType::EuVat));
        $this->assertFalse(TaxIdValidator::isValid('CY102590330', TaxIdType::EuVat));
    }

    public function test_wrong_formats_are_rejected(): void
    {
        foreach (['DE12345678', 'DE1234567890', 'PL52602509951', 'ATX13585627', 'FR4030326504', 'NL004495445X01', 'SE556188840402', 'XX123'] as $bad) {
            $this->assertFalse(TaxIdValidator::isValid($bad, TaxIdType::EuVat), $bad);
        }
    }

    public function test_known_polish_and_czech_numbers(): void
    {
        $this->assertTrue(TaxIdValidator::isValid('5260250995', TaxIdType::PlNip)); // Orange Polska (VIES, MF white list)
        $this->assertTrue(TaxIdValidator::isValid('PL 526-025-09-95', TaxIdType::PlNip));
        $this->assertTrue(TaxIdValidator::isValid('012100784', TaxIdType::PlRegon)); // Orange Polska (MF white list)
        $this->assertTrue(TaxIdValidator::isValid('45274649', TaxIdType::CzIco)); // ČEZ (ARES)
        $this->assertTrue(TaxIdValidator::isValid('00006947', TaxIdType::CzIco)); // Ministerstvo financí (ARES)
        $this->assertTrue(TaxIdValidator::isValid('CZ45274649', TaxIdType::CzDic));
        $this->assertFalse(TaxIdValidator::isValid('45274648', TaxIdType::CzIco));
        $this->assertFalse(TaxIdValidator::isValid('5260250996', TaxIdType::PlNip));
        $this->assertFalse(TaxIdValidator::isValid('012100785', TaxIdType::PlRegon));
        $this->assertFalse(TaxIdValidator::isValid('', TaxIdType::PlNip));
        $this->assertFalse(TaxIdValidator::isValid('52602509', TaxIdType::PlNip));
    }

    public function test_known_ukrainian_numbers(): void
    {
        $this->assertTrue(TaxIdValidator::isValid('14360570', TaxIdType::UaEdrpou)); // PrivatBank
        $this->assertTrue(TaxIdValidator::isValid('00032129', TaxIdType::UaEdrpou)); // Oschadbank
        $this->assertFalse(TaxIdValidator::isValid('14360571', TaxIdType::UaEdrpou));
        $this->assertFalse(TaxIdValidator::isValid('00032128', TaxIdType::UaEdrpou));
        $this->assertFalse(TaxIdValidator::isValid('1436057', TaxIdType::UaEdrpou));
    }

    /**
     * The expected check digits are computed here with a deliberately different formulation of each algorithm.
     */
    public function test_generated_numbers_follow_the_published_algorithms(): void
    {
        mt_srand(20261006);
        $checked = ['nip' => 0, 'regon9' => 0, 'regon14' => 0, 'ico' => 0, 'edrpou' => 0, 'edrpou_high' => 0, 'edrpou_second_pass' => 0, 'rnokpp' => 0];

        for ($i = 0; $i < 4000; $i++) {
            // NIP: sum(d_i * w_i) mod 11; a remainder of 10 means no valid number exists with that prefix.
            $body = self::digits(9);
            $sum = 0;

            foreach (str_split($body) as $k => $d) {
                $sum += (int) $d * [6, 5, 7, 2, 3, 4, 5, 6, 7][$k];
            }

            if ($sum % 11 !== 10) {
                $this->assertTrue(TaxIdValidator::isValid($body.($sum % 11), TaxIdType::PlNip), 'NIP '.$body);
                $this->assertFalse(TaxIdValidator::isValid($body.(($sum + 1) % 11 % 10), TaxIdType::PlNip), 'NIP mutated '.$body);
                $checked['nip']++;
            }

            // REGON 9 and 14.
            $body8 = self::digits(8);
            $regon9 = $body8.self::regonDigit($body8, [8, 9, 2, 3, 4, 5, 6, 7]);
            $this->assertTrue(TaxIdValidator::isValid($regon9, TaxIdType::PlRegon), 'REGON9 '.$regon9);
            $this->assertFalse(TaxIdValidator::isValid($body8.((int) $regon9[8] + 1) % 10, TaxIdType::PlRegon));
            $checked['regon9']++;

            $tail = self::digits(4);
            $regon14 = $regon9.$tail.self::regonDigit($regon9.$tail, [2, 4, 8, 5, 0, 9, 7, 3, 6, 1, 2, 4, 8]);
            $this->assertTrue(TaxIdValidator::isValid($regon14, TaxIdType::PlRegon), 'REGON14 '.$regon14);
            $this->assertFalse(TaxIdValidator::isValid(substr($regon14, 0, 13).((int) $regon14[13] + 1) % 10, TaxIdType::PlRegon));
            $checked['regon14']++;

            // IČO: 11 - (sum mod 11), with 10 -> 0 and 11 -> 1.
            $body7 = self::digits(7);
            $s = 0;

            foreach (str_split($body7) as $k => $d) {
                $s += (int) $d * (8 - $k);
            }
            $r = $s % 11;
            $check = match ($r) {
                0 => 1,
                1 => 0,
                default => 11 - $r,
            };
            $this->assertTrue(TaxIdValidator::isValid($body7.$check, TaxIdType::CzIco), 'IČO '.$body7);
            $this->assertFalse(TaxIdValidator::isValid($body7.(($check + 1) % 10), TaxIdType::CzIco));
            $checked['ico']++;

            // ЄДРПОУ.
            $high = $i % 2 === 0;
            $first = $high ? 30000000 + mt_rand(0, 29999999) : mt_rand(0, 29999999);
            $body7 = substr(str_pad((string) $first, 8, '0', STR_PAD_LEFT), 0, 7);
            $value = (int) ($body7.'0');
            $isMiddle = $value > 30000000 && $value < 60000000;
            $w1 = $isMiddle ? [7, 1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5, 6, 7];
            $w2 = $isMiddle ? [9, 3, 4, 5, 6, 7, 8] : [3, 4, 5, 6, 7, 8, 9];
            $c = self::dot($body7, $w1) % 11;

            if ($c === 10) {
                $c = self::dot($body7, $w2) % 11;
                $c = $c === 10 ? 0 : $c;
                $checked['edrpou_second_pass']++;
            }
            $number = $body7.$c;
            // The range test uses the whole number; recompute if the check digit moved it across a boundary.
            $n = (int) $number;

            if (($n > 30000000 && $n < 60000000) === $isMiddle) {
                $this->assertTrue(TaxIdValidator::isValid($number, TaxIdType::UaEdrpou), 'EDRPOU '.$number);
                $this->assertFalse(TaxIdValidator::isValid($body7.(($c + 1) % 10), TaxIdType::UaEdrpou), 'EDRPOU mutated '.$number);
                $isMiddle ? $checked['edrpou_high']++ : $checked['edrpou']++;
            }

            // РНОКПП: sum with weights -1 5 7 9 4 6 10 5 7, mod 11, mod 10.
            $body9 = self::digits(9);
            $s = -(int) $body9[0] + 5 * (int) $body9[1] + 7 * (int) $body9[2] + 9 * (int) $body9[3] + 4 * (int) $body9[4]
                + 6 * (int) $body9[5] + 10 * (int) $body9[6] + 5 * (int) $body9[7] + 7 * (int) $body9[8];
            $check = (($s % 11) + 11) % 11 % 10;
            $this->assertTrue(TaxIdValidator::isValid($body9.$check, TaxIdType::UaRnokpp), 'RNOKPP '.$body9);
            $this->assertFalse(TaxIdValidator::isValid($body9.(($check + 1) % 10), TaxIdType::UaRnokpp));
            $checked['rnokpp']++;
        }

        foreach ($checked as $name => $count) {
            $this->assertGreaterThan(50, $count, $name.' was exercised too rarely');
        }
    }

    public function test_czech_dic_variants(): void
    {
        $this->assertTrue(TaxIdValidator::isValid('CZ45274649', TaxIdType::CzDic));
        $this->assertFalse(TaxIdValidator::isValid('CZ95274649', TaxIdType::CzDic), 'a legal-entity DIČ never starts with 9');
        // 10-digit birth number: 7103192745 = 710319/2745, divisible by 11.
        $this->assertTrue(TaxIdValidator::isValid('CZ7103192745', TaxIdType::CzDic));
        $this->assertFalse(TaxIdValidator::isValid('CZ7103192746', TaxIdType::CzDic));
        $this->assertFalse(TaxIdValidator::isValid('CZ7113192745', TaxIdType::CzDic), 'month 13 does not exist');
        $this->assertTrue(TaxIdValidator::isValid('CZ530903745', TaxIdType::CzDic), '9-digit birth number (born before 1954)');
        $this->assertTrue(TaxIdValidator::isValid('CZ699001234', TaxIdType::CzDic), '9 digits starting with 6: special case, format only');
    }

    public function test_formatting(): void
    {
        $this->assertSame('PL 5260250995', TaxIdValidator::format('pl 526-025-09-95', TaxIdType::EuVat));
        $this->assertSame('PL 5260250995', TaxIdValidator::format('5260250995', TaxIdType::EuVat, 'PL'));
        $this->assertSame('526-025-09-95', TaxIdValidator::format('5260250995', TaxIdType::PlNip));
        $this->assertSame('CZ45274649', TaxIdValidator::format('45274649', TaxIdType::CzDic));
        $this->assertSame('rubbish', TaxIdValidator::format(' rubbish ', TaxIdType::EuVat));
    }

    private static function mutate(string $value, int $index): string
    {
        $char = $value[$index];
        $value[$index] = ctype_digit($char) ? (string) (((int) $char + 1) % 10) : $char;

        return $value;
    }

    private static function digits(int $length): string
    {
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= mt_rand(0, 9);
        }

        return $out;
    }

    /**
     * @param list<int> $weights
     */
    private static function dot(string $digits, array $weights): int
    {
        return array_sum(array_map(static fn (string $d, int $w): int => (int) $d * $w, str_split(substr($digits, 0, count($weights))), $weights));
    }

    /**
     * @param list<int> $weights
     */
    private static function regonDigit(string $digits, array $weights): int
    {
        return self::dot($digits, $weights) % 11 % 10;
    }
}
