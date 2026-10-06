<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Tests\Feature;

use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;
use Asignua\FilamentVatId\Registries\Ares;
use Asignua\FilamentVatId\Registries\BialaLista;
use Asignua\FilamentVatId\Registries\GusBir;
use Asignua\FilamentVatId\Registries\Vies;
use Asignua\FilamentVatId\Tests\Fixtures\Fixture;
use Asignua\FilamentVatId\Tests\TestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class RegistriesTest extends TestCase
{
    // VIES ------------------------------------------------------------------------------------------------------

    public function test_vies_found(): void
    {
        Http::fake(['*' => Fixture::jsonResponse('vies/valid.json')]);

        $company = app(Vies::class)->lookup('PL', '5260250995');

        $this->assertNotNull($company);
        $this->assertSame('ORANGE POLSKA SPÓŁKA AKCYJNA', $company->name);
        $this->assertSame('ALEJE JEROZOLIMSKIE 160, 02-326 WARSZAWA', $company->address);
        $this->assertSame('PL5260250995', $company->vatNumber);
        $this->assertTrue($company->active);
        $this->assertSame('vies', $company->source);
        $this->assertTrue($company->raw['valid']);

        Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
            && $r->url() === 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number'
            && $r['countryCode'] === 'PL' && $r['vatNumber'] === '5260250995');
    }

    public function test_vies_not_registered_and_dashes_mean_missing(): void
    {
        Http::fake(['*' => Fixture::jsonResponse('vies/invalid.json')]);

        $this->assertNull(app(Vies::class)->lookup('PL', '1234567890'));
    }

    public function test_vies_without_a_name_keeps_the_valid_flag(): void
    {
        Http::fake(['*' => Http::response(['valid' => true, 'name' => '---', 'address' => '---'])]);

        $company = app(Vies::class)->lookup('DE', '136695976');

        $this->assertNotNull($company);
        $this->assertSame('', $company->name);
        $this->assertNull($company->address);
        $this->assertSame('DE136695976', $company->vatNumber);
    }

    public function test_vies_uses_the_el_prefix_for_greece_and_strips_a_given_prefix(): void
    {
        Http::fake(['*' => Fixture::jsonResponse('vies/valid.json')]);

        app(Vies::class)->lookup('GR', 'EL094259216');

        Http::assertSent(fn (Request $r): bool => $r['countryCode'] === 'EL' && $r['vatNumber'] === '094259216');
    }

    public function test_vies_member_state_unavailable(): void
    {
        Http::fake(['*' => Fixture::jsonResponse('vies/ms_unavailable.json')]);

        $this->expectException(RegistryUnavailable::class);
        $this->expectExceptionMessage('MS_UNAVAILABLE');

        app(Vies::class)->lookup('DE', '136695976');
    }

    public function test_vies_server_error_and_timeout_are_unavailable(): void
    {
        $this->refake(['*' => Http::response('Bad gateway', 502)]);

        try {
            app(Vies::class)->lookup('PL', '5260250995');
            $this->fail('Expected RegistryUnavailable');
        } catch (RegistryUnavailable $e) {
            $this->assertSame('vies', $e->registry);
        }

        $this->refake(['*' => Fixture::timeout()]);

        $this->expectException(RegistryUnavailable::class);
        app(Vies::class)->lookup('PL', '5260250995');
    }

    public function test_vies_supports_only_eu_vat_of_eu_countries(): void
    {
        $vies = app(Vies::class);

        $this->assertTrue($vies->supports('DE', TaxIdType::EuVat));
        $this->assertTrue($vies->supports('GR', TaxIdType::EuVat));
        $this->assertTrue($vies->supports('XI', TaxIdType::EuVat));
        $this->assertFalse($vies->supports('UA', TaxIdType::EuVat));
        $this->assertFalse($vies->supports('PL', TaxIdType::PlNip));
    }

    // Biała lista -----------------------------------------------------------------------------------------------

    public function test_biala_lista_found(): void
    {
        Http::fake(['wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/found.json')]);

        $company = app(BialaLista::class)->lookup('PL', '5260250995');

        $this->assertNotNull($company);
        $this->assertSame('ORANGE POLSKA SPÓŁKA AKCYJNA', $company->name);
        $this->assertSame('ALEJE JEROZOLIMSKIE 160, 02-326 WARSZAWA', $company->address);
        $this->assertSame('ALEJE JEROZOLIMSKIE 160', $company->street);
        $this->assertSame('02-326', $company->postcode);
        $this->assertSame('WARSZAWA', $company->city);
        $this->assertSame('PL5260250995', $company->vatNumber);
        $this->assertSame('012100784', $company->regon);
        $this->assertSame('0000010681', $company->registryId);
        $this->assertTrue($company->active);
        $this->assertSame('Czynny', $company->status);
        $this->assertCount(3, $company->bankAccounts);

        foreach ($company->bankAccounts as $iban) {
            $this->assertStringStartsWith('PL', $iban);
            $this->assertSame(28, strlen($iban));
            $this->assertSame(1, self::mod97(substr($iban, 4).'2521'.substr($iban, 2, 2)), $iban.' is not a valid IBAN');
        }

        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://wl-api.mf.gov.pl/api/search/nip/5260250995?date=')
            && preg_match('/date=\d{4}-\d{2}-\d{2}$/', $r->url()) === 1);
    }

    public function test_biala_lista_looks_up_by_regon(): void
    {
        Http::fake(['wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/found.json')]);

        app(BialaLista::class)->lookup('PL', '012100784');

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/api/search/regon/012100784?date='));
    }

    public function test_biala_lista_not_found(): void
    {
        Http::fake(['wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/not_found.json')]);

        $this->assertNull(app(BialaLista::class)->lookup('PL', '1234567890'));
    }

    public function test_biala_lista_a_bad_request_is_not_found_but_rate_limit_is_unavailable(): void
    {
        Http::fake(['wl-api.mf.gov.pl/*' => Http::response(['code' => 'WL-112', 'message' => 'Nieprawidłowy NIP'], 400)]);
        $this->assertNull(app(BialaLista::class)->lookup('PL', '1234567890'));

        $this->refake(['wl-api.mf.gov.pl/*' => Http::response(['code' => 'WL-190', 'message' => 'Przekroczono limit'], 429)]);

        $this->expectException(RegistryUnavailable::class);
        $this->expectExceptionMessage('429');
        app(BialaLista::class)->lookup('PL', '5260250995');
    }

    public function test_biala_lista_server_error_and_timeout_are_unavailable(): void
    {
        Http::fake(['wl-api.mf.gov.pl/*' => Http::response('', 503)]);

        try {
            app(BialaLista::class)->lookup('PL', '5260250995');
            $this->fail('Expected RegistryUnavailable');
        } catch (RegistryUnavailable) {
            $this->addToAssertionCount(1);
        }

        $this->refake(['wl-api.mf.gov.pl/*' => Fixture::timeout()]);

        $this->expectException(RegistryUnavailable::class);
        app(BialaLista::class)->lookup('PL', '5260250995');
    }

    public function test_biala_lista_ignores_numbers_of_the_wrong_length_without_calling(): void
    {
        Http::fake();

        $this->assertNull(app(BialaLista::class)->lookup('PL', '12345'));

        Http::assertNothingSent();
    }

    // ARES ------------------------------------------------------------------------------------------------------

    public function test_ares_found(): void
    {
        Http::fake(['ares.gov.cz/*' => Fixture::jsonResponse('ares/found.json')]);

        $company = app(Ares::class)->lookup('CZ', '45274649');

        $this->assertNotNull($company);
        $this->assertSame('ČEZ, a. s.', $company->name);
        $this->assertSame('Duhová 1444/2, Michle, 14000 Praha 4', $company->address);
        $this->assertSame('Duhová 1444/2', $company->street);
        $this->assertSame('Praha', $company->city);
        $this->assertSame('14000', $company->postcode);
        $this->assertSame('CZ45274649', $company->vatNumber);
        $this->assertSame('45274649', $company->ico);
        $this->assertTrue($company->active);

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/45274649');
    }

    public function test_ares_accepts_a_dic_with_prefix_and_ignores_birth_number_dics(): void
    {
        Http::fake(['ares.gov.cz/*' => Fixture::jsonResponse('ares/found.json')]);

        $this->assertNotNull(app(Ares::class)->lookup('CZ', 'CZ45274649'));
        $this->assertNull(app(Ares::class)->lookup('CZ', '7103192745'));

        Http::assertSentCount(1);
    }

    public function test_ares_not_found_unavailable_and_timeout(): void
    {
        Http::fake(['ares.gov.cz/*' => Fixture::jsonResponse('ares/not_found.json', 404)]);
        $this->assertNull(app(Ares::class)->lookup('CZ', '12345678'));

        $this->refake(['ares.gov.cz/*' => Http::response('', 500)]);

        try {
            app(Ares::class)->lookup('CZ', '45274649');
            $this->fail('Expected RegistryUnavailable');
        } catch (RegistryUnavailable) {
            $this->addToAssertionCount(1);
        }

        $this->refake(['ares.gov.cz/*' => Fixture::timeout()]);

        $this->expectException(RegistryUnavailable::class);
        app(Ares::class)->lookup('CZ', '45274649');
    }

    public function test_ares_marks_ended_companies_inactive(): void
    {
        $json = Fixture::json('ares/found.json');
        $json['datumZaniku'] = '2020-01-31';

        Http::fake(['ares.gov.cz/*' => Http::response($json)]);

        $company = app(Ares::class)->lookup('CZ', '45274649');

        $this->assertNotNull($company);
        $this->assertFalse($company->active);
        $this->assertSame('ended', $company->status);
    }

    // GUS BIR ---------------------------------------------------------------------------------------------------

    public function test_gus_is_disabled_without_a_key(): void
    {
        config()->set('filament-vat-id.gus.key', null);
        config()->set('filament-vat-id.gus.environment', 'prod');
        Http::fake();

        $gus = app(GusBir::class);

        $this->assertFalse($gus->isEnabled());
        $this->assertFalse($gus->supports('PL', TaxIdType::PlNip));

        Http::assertNothingSent();
    }

    public function test_gus_test_environment_works_with_the_public_key(): void
    {
        config()->set('filament-vat-id.gus.key', null);
        config()->set('filament-vat-id.gus.environment', 'test');

        $this->assertTrue(app(GusBir::class)->supports('PL', TaxIdType::PlNip));
    }

    public function test_gus_found(): void
    {
        $this->gusConfig();
        Http::fake(['wyszukiwarkaregontest.stat.gov.pl/*' => Http::sequence()
            ->push(Fixture::text('gus/login.txt'), 200, ['Content-Type' => 'multipart/related; boundary="uuid:x"'])
            ->push(Fixture::text('gus/search_found.txt'), 200, ['Content-Type' => 'multipart/related; boundary="uuid:y"']),
        ]);

        $company = app(GusBir::class)->lookup('PL', '5260250995');

        $this->assertNotNull($company);
        $this->assertSame('ORANGE POLSKA SPÓŁKA AKCYJNA', $company->name);
        $this->assertSame('012100784', $company->regon);
        $this->assertSame('PL5260250995', $company->vatNumber);
        $this->assertSame('ul. Test-Krucza 160', $company->street);
        $this->assertSame('02-326', $company->postcode);
        $this->assertSame('Warszawa', $company->city);
        $this->assertSame('ul. Test-Krucza 160, 02-326 Warszawa', $company->address);
        $this->assertTrue($company->active);
        $this->assertSame('gus_bir', $company->source);

        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);

        /** @var Request $login */
        $login = $recorded[0][0];
        $this->assertStringContainsString('<ns:pKluczUzytkownika>abcde12345abcde12345</ns:pKluczUzytkownika>', $login->body());
        $this->assertStringContainsString('application/soap+xml', $login->header('Content-Type')[0]);
        $this->assertSame([], $login->header('sid'));

        /** @var Request $search */
        $search = $recorded[1][0];
        $this->assertSame(['fub7fg74ygrugu2ze56x'], $search->header('sid'));
        $this->assertStringContainsString('<dat:Nip>5260250995</dat:Nip>', $search->body());
    }

    public function test_gus_reuses_the_session(): void
    {
        $this->gusConfig();
        Http::fake(['wyszukiwarkaregontest.stat.gov.pl/*' => Http::sequence()
            ->push(Fixture::text('gus/login.txt'))
            ->push(Fixture::text('gus/search_found.txt'))
            ->push(Fixture::text('gus/search_found.txt')),
        ]);

        $gus = app(GusBir::class);
        $gus->lookup('PL', '5260250995');
        $gus->lookup('PL', '5260250995');

        Http::assertSentCount(3); // one login, two searches
    }

    public function test_gus_logs_in_again_when_the_session_is_rejected(): void
    {
        $this->gusConfig();
        $empty = str_replace(
            ['<DaneSzukajPodmiotyResult>', '</DaneSzukajPodmiotyResult>'],
            ['<DaneSzukajPodmiotyResult>', '</DaneSzukajPodmiotyResult>'],
            '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><DaneSzukajPodmiotyResponse xmlns="http://CIS/BIR/PUBL/2014/07"><DaneSzukajPodmiotyResult/></DaneSzukajPodmiotyResponse></s:Body></s:Envelope>',
        );

        Http::fake(['wyszukiwarkaregontest.stat.gov.pl/*' => Http::sequence()
            ->push(Fixture::text('gus/login.txt'))
            ->push($empty)
            ->push(Fixture::text('gus/login.txt'))
            ->push(Fixture::text('gus/search_found.txt')),
        ]);

        $this->assertNotNull(app(GusBir::class)->lookup('PL', '5260250995'));
        Http::assertSentCount(4);
    }

    public function test_gus_looks_up_a_regon_and_ignores_other_lengths(): void
    {
        $this->gusConfig();
        Http::fake(['wyszukiwarkaregontest.stat.gov.pl/*' => Http::sequence()
            ->push(Fixture::text('gus/login.txt'))
            ->push(Fixture::text('gus/search_found.txt')),
        ]);

        $gus = app(GusBir::class);
        $this->assertNull($gus->lookup('PL', '12345'));
        $gus->lookup('PL', '012100784');

        Http::assertSent(fn (Request $r): bool => str_contains($r->body(), '<dat:Regon>012100784</dat:Regon>'));
    }

    public function test_gus_not_found(): void
    {
        $this->gusConfig();
        Http::fake(['wyszukiwarkaregontest.stat.gov.pl/*' => Http::sequence()
            ->push(Fixture::text('gus/login.txt'))
            ->push(Fixture::text('gus/search_not_found.txt')),
        ]);

        $this->assertNull(app(GusBir::class)->lookup('PL', '1234567890'));
    }

    public function test_gus_rejected_key_is_unavailable(): void
    {
        $this->gusConfig('wrongwrongwrongwrong');
        Http::fake(['wyszukiwarkaregontest.stat.gov.pl/*' => Http::response(Fixture::text('gus/login_bad_key.txt'))]);

        $this->expectException(RegistryUnavailable::class);
        $this->expectExceptionMessage('API key');

        app(GusBir::class)->lookup('PL', '5260250995');
    }

    public function test_gus_server_error_and_timeout_are_unavailable(): void
    {
        $this->gusConfig();
        Http::fake(['wyszukiwarkaregontest.stat.gov.pl/*' => Http::response('', 500)]);

        try {
            app(GusBir::class)->lookup('PL', '5260250995');
            $this->fail('Expected RegistryUnavailable');
        } catch (RegistryUnavailable) {
            $this->addToAssertionCount(1);
        }

        $this->refake(['wyszukiwarkaregontest.stat.gov.pl/*' => Fixture::timeout()]);

        $this->expectException(RegistryUnavailable::class);
        app(GusBir::class)->lookup('PL', '5260250995');
    }

    public function test_gus_extracts_the_result_from_multipart_and_plain_soap(): void
    {
        $multipart = Fixture::text('gus/login.txt');
        $this->assertSame('fub7fg74ygrugu2ze56x', GusBir::extractResult($multipart, 'ZalogujResult'));

        $plain = '<?xml version="1.0"?><s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><ZalogujResponse xmlns="http://CIS/BIR/PUBL/2014/07"><ZalogujResult>abc</ZalogujResult></ZalogujResponse></s:Body></s:Envelope>';
        $this->assertSame('abc', GusBir::extractResult($plain, 'ZalogujResult'));

        $fault = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><s:Fault><s:Reason>x</s:Reason></s:Fault></s:Body></s:Envelope>';

        $this->expectException(RegistryUnavailable::class);
        GusBir::extractResult($fault, 'ZalogujResult');
    }

    public function test_gus_garbage_is_unavailable(): void
    {
        $this->expectException(RegistryUnavailable::class);

        GusBir::extractResult('<html>502 Bad Gateway</html>', 'ZalogujResult');
    }

    /**
     * Replaces the HTTP fakes (a second Http::fake() would only add stubs behind the first ones).
     *
     * @param array<string, mixed> $stubs
     */
    private function refake(array $stubs): void
    {
        $this->app->forgetInstance(Factory::class);
        Http::clearResolvedInstance(Factory::class);
        Http::fake($stubs);
    }

    private function gusConfig(string $key = 'abcde12345abcde12345'): void
    {
        config()->set('filament-vat-id.gus.key', $key);
        config()->set('filament-vat-id.gus.environment', 'test');
    }

    private static function mod97(string $number): int
    {
        $remainder = 0;

        foreach (str_split($number) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder;
    }
}
