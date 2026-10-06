<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Tests\Feature;

use Asignua\FilamentVatId\Contracts\CompanyRegistry;
use Asignua\FilamentVatId\Data\CompanyData;
use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Enums\VerificationStatus;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;
use Asignua\FilamentVatId\Registries\Ares;
use Asignua\FilamentVatId\Registries\BialaLista;
use Asignua\FilamentVatId\Registries\GusBir;
use Asignua\FilamentVatId\Registries\Vies;
use Asignua\FilamentVatId\Support\RegistryManager;
use Asignua\FilamentVatId\Tests\Fixtures\Fixture;
use Asignua\FilamentVatId\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class RegistryManagerTest extends TestCase
{
    public function test_registries_are_picked_by_country_and_type_in_the_configured_order(): void
    {
        config()->set('filament-vat-id.gus.key', 'k');
        $manager = app(RegistryManager::class);

        $names = fn (string $country, TaxIdType $type): array => array_map(fn (CompanyRegistry $r): string => $r::class, $manager->registriesFor($country, $type));

        $this->assertSame([BialaLista::class, GusBir::class, Vies::class], $names('PL', TaxIdType::EuVat));
        $this->assertSame([BialaLista::class, GusBir::class], $names('PL', TaxIdType::PlNip));
        $this->assertSame([Ares::class, Vies::class], $names('CZ', TaxIdType::EuVat));
        $this->assertSame([Ares::class], $names('CZ', TaxIdType::CzIco));
        $this->assertSame([Vies::class], $names('DE', TaxIdType::EuVat));
        $this->assertSame([], $names('UA', TaxIdType::UaEdrpou), 'Ukraine has no free registry');
        $this->assertFalse($manager->supports('UA', TaxIdType::UaRnokpp));

        config()->set('filament-vat-id.registries', [Vies::class, BialaLista::class]);
        $this->assertSame([Vies::class, BialaLista::class], $names('PL', TaxIdType::EuVat));
    }

    public function test_results_are_cached_including_not_found(): void
    {
        Http::fake([
            'ares.gov.cz/*/45274649' => Fixture::jsonResponse('ares/found.json'),
            'ares.gov.cz/*/12345678' => Fixture::jsonResponse('ares/not_found.json', 404),
        ]);
        $manager = app(RegistryManager::class);

        $first = $manager->lookup('CZ', TaxIdType::CzIco, '45274649');
        $second = $manager->lookup('CZ', TaxIdType::CzIco, '45274649');

        $this->assertSame('ČEZ, a. s.', $first?->name);
        $this->assertEquals($first, $second);
        Http::assertSentCount(1);

        $this->assertNull($manager->lookup('CZ', TaxIdType::CzIco, '12345678'));
        $this->assertNull($manager->lookup('CZ', TaxIdType::CzIco, '12345678'));
        Http::assertSentCount(2);
    }

    public function test_unavailable_answers_are_never_cached(): void
    {
        Http::fake(['ares.gov.cz/*' => Http::sequence()->push('', 500)->push(Fixture::text('ares/found.json'), 200, ['Content-Type' => 'application/json'])]);
        $manager = app(RegistryManager::class);

        try {
            $manager->lookup('CZ', TaxIdType::CzIco, '45274649');
            $this->fail('Expected RegistryUnavailable');
        } catch (RegistryUnavailable) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('ČEZ, a. s.', $manager->lookup('CZ', TaxIdType::CzIco, '45274649')?->name);
    }

    public function test_cache_can_be_disabled(): void
    {
        config()->set('filament-vat-id.cache.enabled', false);
        Http::fake(['ares.gov.cz/*' => Fixture::jsonResponse('ares/found.json')]);
        $manager = app(RegistryManager::class);

        $manager->lookup('CZ', TaxIdType::CzIco, '45274649');
        $manager->lookup('CZ', TaxIdType::CzIco, '45274649');

        Http::assertSentCount(2);
    }

    public function test_cached_company_reads_without_the_network(): void
    {
        Http::fake(['ares.gov.cz/*' => Fixture::jsonResponse('ares/found.json')]);
        $manager = app(RegistryManager::class);

        $this->assertNull($manager->cachedCompany('CZ', TaxIdType::CzIco, '45274649'));

        $manager->lookup('CZ', TaxIdType::CzIco, '45274649');

        $this->assertSame('ČEZ, a. s.', $manager->cachedCompany('CZ', TaxIdType::CzIco, 'CZ45274649')?->name);
        Http::assertSentCount(1);
    }

    public function test_falls_through_to_the_next_registry(): void
    {
        config()->set('filament-vat-id.registries', [BialaLista::class, Vies::class]);
        Http::fake([
            'wl-api.mf.gov.pl/*' => Http::response('', 503),
            'ec.europa.eu/*' => Fixture::jsonResponse('vies/valid.json'),
        ]);

        $company = app(RegistryManager::class)->lookup('PL', TaxIdType::EuVat, 'PL5260250995');

        $this->assertSame('vies', $company?->source);
    }

    public function test_a_not_found_answer_does_not_hide_an_unavailable_registry(): void
    {
        config()->set('filament-vat-id.registries', [BialaLista::class, Vies::class]);
        Http::fake([
            'wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/not_found.json'),
            'ec.europa.eu/*' => Fixture::jsonResponse('vies/ms_unavailable.json'),
        ]);

        $this->expectException(RegistryUnavailable::class);

        app(RegistryManager::class)->lookup('PL', TaxIdType::EuVat, 'PL5260250995');
    }

    public function test_only_restricts_to_one_registry(): void
    {
        Http::fake([
            'wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/found.json'),
            'ec.europa.eu/*' => Fixture::jsonResponse('vies/valid.json'),
        ]);

        $company = app(RegistryManager::class)->lookup('PL', TaxIdType::EuVat, 'PL5260250995', Vies::class);

        $this->assertSame('vies', $company?->source);
        Http::assertSentCount(1);
    }

    public function test_verify_outcomes(): void
    {
        $manager = app(RegistryManager::class);

        Http::fake(['ec.europa.eu/*' => Fixture::jsonResponse('vies/valid.json')]);
        $this->assertSame(VerificationStatus::Verified, $manager->verify('', TaxIdType::EuVat, 'DE136695976')->status);

        $this->assertSame(VerificationStatus::Skipped, $manager->verify('UA', TaxIdType::UaEdrpou, '14360570')->status);
        $this->assertSame(VerificationStatus::Skipped, $manager->verify('', TaxIdType::EuVat, '136695976')->status, 'no country');
    }

    public function test_verify_not_found_and_unavailable(): void
    {
        $manager = app(RegistryManager::class);

        Http::fake(['ec.europa.eu/*' => Fixture::jsonResponse('vies/invalid.json')]);
        $this->assertSame(VerificationStatus::NotFound, $manager->verify('', TaxIdType::EuVat, 'DE136695976')->status);

        $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
        Http::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        \Illuminate\Support\Facades\Cache::flush();
        Http::fake(['ec.europa.eu/*' => Fixture::jsonResponse('vies/ms_unavailable.json')]);

        $verification = $manager->verify('', TaxIdType::EuVat, 'DE136695976');
        $this->assertSame(VerificationStatus::Unavailable, $verification->status);
        $this->assertStringContainsString('MS_UNAVAILABLE', (string) $verification->reason);
    }

    public function test_on_unavailable_config(): void
    {
        $manager = app(RegistryManager::class);

        $this->assertSame('warn', $manager->onUnavailable());

        foreach (['allow', 'warn', 'fail'] as $mode) {
            config()->set('filament-vat-id.on_unavailable', $mode);
            $this->assertSame($mode, $manager->onUnavailable());
        }

        config()->set('filament-vat-id.on_unavailable', 'bogus');
        $this->assertSame('warn', $manager->onUnavailable());
    }

    public function test_a_custom_registry_can_be_plugged_in_for_ukraine(): void
    {
        $custom = new class implements CompanyRegistry
        {
            public function supports(string $country, TaxIdType $type): bool
            {
                return $country === 'UA' && $type === TaxIdType::UaEdrpou;
            }

            public function canVerify(string $country, TaxIdType $type): bool
            {
                return true;
            }

            public function lookup(string $country, TaxIdType $type, string $number): ?CompanyData
            {
                return $number === '14360570' ? new CompanyData(name: 'PrivatBank', source: 'custom') : null;
            }
        };

        $manager = app(RegistryManager::class)->extend($custom);

        $this->assertSame('PrivatBank', $manager->lookup('UA', TaxIdType::UaEdrpou, '14360570')?->name);
        $this->assertNull($manager->lookup('UA', TaxIdType::UaEdrpou, '00032129'));
    }

    public function test_custom_registries_can_be_listed_in_config_by_class(): void
    {
        config()->set('filament-vat-id.registries', [StubUkrainianRegistry::class]);

        $this->assertSame('Stub', app(RegistryManager::class)->lookup('UA', TaxIdType::UaEdrpou, '14360570')?->name);
    }

    public function test_eu_vat_is_verified_by_vies_only(): void
    {
        config()->set('filament-vat-id.registries', [BialaLista::class, Vies::class]);
        Http::fake([
            'wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/found.json'),
            'ec.europa.eu/*' => Fixture::jsonResponse('vies/invalid.json'),
        ]);

        $manager = app(RegistryManager::class);

        $this->assertSame(VerificationStatus::NotFound, $manager->verify('', TaxIdType::EuVat, 'PL5260250995')->status, 'the white list does not prove VAT-UE');
        Http::assertSentCount(1);

        // a lookup (to fill a form) still prefers the white list
        $this->assertSame('biala_lista', $manager->lookup('', TaxIdType::EuVat, 'PL5260250995')?->source);
    }

    public function test_an_inactive_company_is_not_verified(): void
    {
        $json = Fixture::json('ares/found.json');
        $json['datumZaniku'] = '2020-01-31';
        Http::fake(['ares.gov.cz/*' => Http::response($json)]);

        $verification = app(RegistryManager::class)->verify('', TaxIdType::CzIco, '45274649');

        $this->assertSame(VerificationStatus::Inactive, $verification->status);
        $this->assertFalse($verification->company?->active);
    }

    public function test_a_czech_birth_number_dic_is_skipped_not_not_found(): void
    {
        Http::fake();

        $manager = app(RegistryManager::class);

        $this->assertSame(VerificationStatus::Skipped, $manager->verify('', TaxIdType::CzDic, 'CZ7103192745')->status);
        $this->assertSame(VerificationStatus::Verified, $this->fakedAres($manager)->status);
    }

    private function fakedAres(RegistryManager $manager): \Asignua\FilamentVatId\Data\Verification
    {
        $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
        Http::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        Http::fake(['ares.gov.cz/*' => Fixture::jsonResponse('ares/found.json')]);

        return $manager->verify('', TaxIdType::CzDic, 'CZ45274649');
    }

    public function test_polish_numbers_are_verified_by_gus_only(): void
    {
        $manager = app(RegistryManager::class);
        Http::fake(['wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/not_found.json')]);

        // no GUS key: nothing authoritative to ask. A white-list miss is not "does not exist".
        config()->set('filament-vat-id.gus.key', null);
        config()->set('filament-vat-id.gus.environment', 'prod');
        $this->assertSame(VerificationStatus::Skipped, $manager->verify('', TaxIdType::PlNip, '5260250995')->status);
        Http::assertNothingSent();

        config()->set('filament-vat-id.gus.key', 'abcde12345abcde12345');
        config()->set('filament-vat-id.gus.environment', 'test');
        $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
        Http::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        Http::fake(['wyszukiwarkaregontest.stat.gov.pl/*' => Http::sequence()
            ->push(Fixture::text('gus/login.txt'))
            ->push(Fixture::text('gus/search_not_found.txt')),
        ]);

        $this->assertSame(VerificationStatus::NotFound, $manager->verify('', TaxIdType::PlNip, '5260250995')->status);
    }

    public function test_verification_stops_at_the_first_unavailable_registry(): void
    {
        $first = new CountingRegistry(unavailable: true);
        $second = new class extends CountingRegistry {};
        config()->set('filament-vat-id.registries', []);
        $manager = app(RegistryManager::class)->extend($first)->extend($second);

        $this->assertSame(VerificationStatus::Unavailable, $manager->verify('DE', TaxIdType::EuVat, 'DE136695976')->status);
        $this->assertSame(1, CountingRegistry::$calls);

        // a lookup (not verification) goes on to the next registry
        CountingRegistry::$calls = 0;
        $this->assertSame('counting', $manager->lookup('DE', TaxIdType::EuVat, 'DE136695976')?->source);
        $this->assertSame(2, CountingRegistry::$calls);
    }

    public function test_the_total_timeout_stops_the_chain(): void
    {
        config()->set('filament-vat-id.registries', []);
        config()->set('filament-vat-id.total_timeout', 1);
        $slow = new CountingRegistry(sleep: 1_200_000, found: false);
        $manager = app(RegistryManager::class)->extend($slow)->extend(new class extends CountingRegistry {});

        CountingRegistry::$calls = 0;

        try {
            $manager->lookup('DE', TaxIdType::EuVat, 'DE136695976');
            $this->fail('Expected RegistryUnavailable');
        } catch (RegistryUnavailable $e) {
            $this->assertStringContainsString('total timeout', $e->getMessage());
        }

        $this->assertSame(1, CountingRegistry::$calls);
    }

    public function test_the_cache_key_includes_the_type(): void
    {
        config()->set('filament-vat-id.registries', []);
        $manager = app(RegistryManager::class)->extend(new CountingRegistry(types: [TaxIdType::CzIco, TaxIdType::CzDic], country: 'CZ'));
        CountingRegistry::$calls = 0;

        $manager->lookup('', TaxIdType::CzIco, '45274649');
        $manager->lookup('', TaxIdType::CzDic, '45274649');
        $manager->lookup('', TaxIdType::CzDic, '45274649');

        $this->assertSame(2, CountingRegistry::$calls);
    }

    public function test_extend_ignores_a_class_that_is_already_registered(): void
    {
        config()->set('filament-vat-id.registries', []);
        $manager = app(RegistryManager::class);

        $manager->extend(new CountingRegistry)->extend(new CountingRegistry)->extend(CountingRegistry::class);

        $this->assertCount(1, $manager->registries());
    }

    public function test_company_data_paths(): void
    {
        $company = new CompanyData(name: 'ACME', source: 's', address: '', bankAccounts: ['PL1', 'PL2']);

        $this->assertSame('ACME', $company->get('name'));
        $this->assertNull($company->get('address'), 'empty strings read as missing');
        $this->assertSame('PL2', $company->get('bankAccounts.1'));
        $this->assertNull($company->get('bankAccounts.5'));
        $this->assertEquals($company, CompanyData::fromArray($company->toArray()));
    }
}

class StubUkrainianRegistry implements CompanyRegistry
{
    public function supports(string $country, TaxIdType $type): bool
    {
        return $country === 'UA';
    }

    public function canVerify(string $country, TaxIdType $type): bool
    {
        return false;
    }

    public function lookup(string $country, TaxIdType $type, string $number): ?CompanyData
    {
        return new CompanyData(name: 'Stub', source: 'stub');
    }
}

class CountingRegistry implements CompanyRegistry
{
    public static int $calls = 0;

    /**
     * @param list<TaxIdType> $types
     */
    public function __construct(
        private bool $unavailable = false,
        private int $sleep = 0,
        private array $types = [TaxIdType::EuVat],
        private string $country = 'DE',
        private bool $found = true,
    ) {}

    public function supports(string $country, TaxIdType $type): bool
    {
        return $country === $this->country && in_array($type, $this->types, true);
    }

    public function canVerify(string $country, TaxIdType $type): bool
    {
        return $this->supports($country, $type);
    }

    public function lookup(string $country, TaxIdType $type, string $number): ?CompanyData
    {
        self::$calls++;

        if ($this->sleep > 0) {
            usleep($this->sleep);
        }

        if ($this->unavailable) {
            throw new RegistryUnavailable('counting', 'down');
        }

        return $this->found ? new CompanyData(name: 'X', source: 'counting') : null;
    }
}
