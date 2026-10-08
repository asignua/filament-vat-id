<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Tests\Feature;

use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Forms\Components\TaxIdInput;
use Asignua\FilamentVatId\Infolists\Components\TaxIdEntry;
use Asignua\FilamentVatId\Registries\BialaLista;
use Asignua\FilamentVatId\Support\TaxIdValidator;
use Asignua\FilamentVatId\Tests\Fixtures\Fixture;
use Asignua\FilamentVatId\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Workbench\App\Livewire\CompanyForm;
use Workbench\App\Models\User;

class TaxIdInputTest extends TestCase
{
    private function lookupAction(): TestAction
    {
        return TestAction::make('lookup')->schemaComponent('tax_id');
    }

    private function refake(array $stubs): void
    {
        $this->app->forgetInstance(Factory::class);
        Http::clearResolvedInstance(Factory::class);
        Http::fake($stubs);
    }

    // Validation ------------------------------------------------------------------------------------------------

    public function test_a_valid_number_saves(): void
    {
        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => '526-025-09-95'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved.tax_id', '526-025-09-95');
    }

    public function test_an_invalid_number_is_reported(): void
    {
        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => '5260250996'])
            ->call('save')
            ->assertHasFormErrors(['tax_id']);
    }

    public function test_the_country_comes_from_the_country_field(): void
    {
        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'CZ', 'tax_id' => '5260250995'])
            ->call('save')
            ->assertHasFormErrors(['tax_id']);

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'CZ', 'tax_id' => '45274649'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'CZ', 'tax_id' => 'PL5260250995'])
            ->call('save')
            ->assertHasFormErrors(['tax_id']);
    }

    public function test_the_error_message_is_translated(): void
    {
        $component = Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => '1'])
            ->call('save');

        $this->assertStringContainsString(
            TaxIdType::EuVat->label(),
            (string) $component->errors()->first('data.tax_id'),
        );
    }

    public function test_an_empty_value_passes_without_required(): void
    {
        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => ''])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_other_types(): void
    {
        Livewire::test(CompanyForm::class, ['type' => 'pl_nip'])
            ->fillForm(['tax_id' => 'PL5260250995'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(CompanyForm::class, ['type' => 'ua_edrpou'])
            ->fillForm(['tax_id' => '14360570'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(CompanyForm::class, ['type' => 'ua_edrpou'])
            ->fillForm(['tax_id' => '14360571'])
            ->call('save')
            ->assertHasFormErrors(['tax_id']);
    }

    // Remote validation -----------------------------------------------------------------------------------------

    public function test_vies_rejects_a_well_formed_number_unknown_to_the_registry(): void
    {
        Http::fake(['ec.europa.eu/*' => Fixture::jsonResponse('vies/invalid.json')]);

        Livewire::test(CompanyForm::class, ['remote' => true])
            ->fillForm(['country' => 'DE', 'tax_id' => '136695976'])
            ->call('save')
            ->assertHasFormErrors(['tax_id']);
    }

    public function test_vies_accepts_a_registered_number(): void
    {
        Http::fake(['ec.europa.eu/*' => Fixture::jsonResponse('vies/valid.json')]);

        Livewire::test(CompanyForm::class, ['remote' => true])
            ->fillForm(['country' => 'DE', 'tax_id' => '136695976'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_vies_is_not_called_for_a_malformed_number(): void
    {
        Http::fake();

        Livewire::test(CompanyForm::class, ['remote' => true])
            ->fillForm(['country' => 'DE', 'tax_id' => '136695977'])
            ->call('save')
            ->assertHasFormErrors(['tax_id']);

        Http::assertNothingSent();
    }

    public function test_vies_is_not_called_unless_asked(): void
    {
        Http::fake();

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'DE', 'tax_id' => '136695976'])
            ->call('save')
            ->assertHasNoFormErrors();

        Http::assertNothingSent();
    }

    public function test_on_unavailable_allow_saves_silently(): void
    {
        config()->set('filament-vat-id.on_unavailable', 'allow');
        Http::fake(['ec.europa.eu/*' => Fixture::jsonResponse('vies/ms_unavailable.json')]);

        Livewire::test(CompanyForm::class, ['remote' => true])
            ->fillForm(['country' => 'DE', 'tax_id' => '136695976'])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertNotNotified();
    }

    public function test_on_unavailable_warn_saves_and_notifies(): void
    {
        config()->set('filament-vat-id.on_unavailable', 'warn');
        Http::fake(['ec.europa.eu/*' => Fixture::jsonResponse('vies/ms_unavailable.json')]);

        Livewire::test(CompanyForm::class, ['remote' => true])
            ->fillForm(['country' => 'DE', 'tax_id' => '136695976'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved.tax_id', '136695976');

        Notification::assertNotified(__('filament-vat-id::filament-vat-id.notifications.unavailable_title'));
    }

    public function test_on_unavailable_fail_rejects(): void
    {
        config()->set('filament-vat-id.on_unavailable', 'fail');
        Http::fake(['ec.europa.eu/*' => Fixture::jsonResponse('vies/ms_unavailable.json')]);

        Livewire::test(CompanyForm::class, ['remote' => true])
            ->fillForm(['country' => 'DE', 'tax_id' => '136695976'])
            ->call('save')
            ->assertHasFormErrors(['tax_id']);

        Notification::assertNotNotified();
    }

    // Look up ---------------------------------------------------------------------------------------------------

    public function test_lookup_fills_the_sibling_fields_and_shows_the_name(): void
    {
        config()->set('filament-vat-id.registries', [BialaLista::class]);
        Http::fake(['wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/found.json')]);

        $component = Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => 'PL5260250995'])
            ->callAction($this->lookupAction());

        $component
            ->assertSet('data.company_name', 'ORANGE POLSKA SPÓŁKA AKCYJNA')
            ->assertSet('data.address', 'ALEJE JEROZOLIMSKIE 160, 02-326 WARSZAWA')
            ->assertSet('data.regon', '012100784')
            ->assertSet('data.iban', 'PL13103015080000000503131075')
            ->assertSee('ORANGE POLSKA SPÓŁKA AKCYJNA');

        Notification::assertNotified(__('filament-vat-id::filament-vat-id.lookup.found'));

        // the hint is tied to the number it was found for
        $component->fillForm(['tax_id' => 'PL1234567890']);
        $component->assertDontSee('ORANGE POLSKA');
    }

    public function test_the_lookup_hint_does_not_leak_into_the_saved_data(): void
    {
        config()->set('filament-vat-id.registries', [BialaLista::class]);
        Http::fake(['wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/found.json')]);

        $component = Livewire::test(CompanyForm::class, ['withRepeater' => true])
            ->fillForm(['country' => 'PL', 'tax_id' => 'PL5260250995', 'rows' => [['v' => 'x']]])
            ->callAction($this->lookupAction());

        $component->assertSee('ORANGE POLSKA SPÓŁKA AKCYJNA');
        $this->assertSame([], array_filter(array_keys($component->get('data')), fn (string $k): bool => str_starts_with($k, '__')));

        $component->call('save')->assertHasNoFormErrors();

        $this->assertSame([], array_filter(array_keys($component->get('saved')), fn (string $k): bool => str_starts_with($k, '__')));
        $this->assertCount(1, $component->get('saved.rows'));
    }

    public function test_lookup_escapes_the_company_name_in_the_notification(): void
    {
        config()->set('filament-vat-id.registries', [BialaLista::class]);
        $json = Fixture::json('biala-lista/found.json');
        $json['result']['subject']['name'] = '<b>Evil</b> Sp. z o.o.';
        Http::fake(['wl-api.mf.gov.pl/*' => Http::response($json)]);

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => '5260250995'])
            ->callAction($this->lookupAction());

        Notification::assertNotified(
            Notification::make()->success()->title(__('filament-vat-id::filament-vat-id.lookup.found'))->body(e('<b>Evil</b> Sp. z o.o.')),
        );
    }

    public function test_a_non_eu_country_skips_eu_vat_validation(): void
    {
        Http::fake();

        Livewire::test(CompanyForm::class, ['remote' => true])
            ->fillForm(['country' => 'UA', 'tax_id' => 'whatever 123'])
            ->call('save')
            ->assertHasNoFormErrors();

        Http::assertNothingSent();
    }

    public function test_lookup_does_not_overwrite_with_empty_values(): void
    {
        config()->set('filament-vat-id.registries', [BialaLista::class]);
        $json = Fixture::json('biala-lista/found.json');
        $json['result']['subject']['accountNumbers'] = [];
        Http::fake(['wl-api.mf.gov.pl/*' => Http::response($json)]);

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => '5260250995', 'iban' => 'PL00KEEP'])
            ->callAction($this->lookupAction())
            ->assertSet('data.iban', 'PL00KEEP');
    }

    public function test_lookup_not_found(): void
    {
        config()->set('filament-vat-id.registries', [BialaLista::class]);
        Http::fake(['wl-api.mf.gov.pl/*' => Fixture::jsonResponse('biala-lista/not_found.json')]);

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => '5260250995'])
            ->callAction($this->lookupAction())
            ->assertSet('data.company_name', null);

        Notification::assertNotified(__('filament-vat-id::filament-vat-id.lookup.not_found'));
    }

    public function test_lookup_unavailable(): void
    {
        config()->set('filament-vat-id.registries', [BialaLista::class]);
        Http::fake(['wl-api.mf.gov.pl/*' => Http::response('', 429)]);

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => '5260250995'])
            ->callAction($this->lookupAction())
            ->assertSet('data.company_name', null);

        Notification::assertNotified(__('filament-vat-id::filament-vat-id.lookup.unavailable'));
    }

    public function test_lookup_with_an_invalid_empty_or_unsupported_number_does_not_call_a_registry(): void
    {
        Http::fake();

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => ''])
            ->callAction($this->lookupAction());
        Notification::assertNotified(__('filament-vat-id::filament-vat-id.lookup.empty'));

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => '5260250996'])
            ->callAction($this->lookupAction());
        Notification::assertNotified(__('filament-vat-id::filament-vat-id.lookup.invalid', ['type' => TaxIdType::EuVat->label()]));

        Livewire::test(CompanyForm::class, ['type' => 'ua_edrpou'])
            ->fillForm(['tax_id' => '14360570'])
            ->callAction($this->lookupAction());
        Notification::assertNotified(__('filament-vat-id::filament-vat-id.lookup.no_registry'));

        Http::assertNothingSent();
    }

    public function test_lookup_works_through_vies_for_other_countries(): void
    {
        Http::fake(['ec.europa.eu/*' => Fixture::jsonResponse('vies/valid.json')]);

        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'DE', 'tax_id' => '136695976'])
            ->callAction($this->lookupAction())
            ->assertSet('data.company_name', 'ORANGE POLSKA SPÓŁKA AKCYJNA');
    }

    // Misc ------------------------------------------------------------------------------------------------------

    public function test_the_html_type_still_works(): void
    {
        $input = TaxIdInput::make('x')->type('tel');

        $this->assertSame('tel', $input->getType());
        $this->assertSame(TaxIdType::EuVat, $input->getTaxIdType());

        $input->type(TaxIdType::PlNip);
        $this->assertSame(TaxIdType::PlNip, $input->getTaxIdType());
        $this->assertSame('PL', $input->getTaxIdCountry());
    }

    public function test_the_entry_formats_and_copies_the_normalised_value(): void
    {
        $entry = $this->entry(TaxIdEntry::make('nip')->type(TaxIdType::PlNip), ['nip' => '5260250995']);

        $this->assertSame('526-025-09-95', $entry->formatState($entry->getState()));
        $this->assertTrue($entry->isCopyable('5260250995'));
        $this->assertSame('5260250995', $entry->getCopyableState('526-025-09-95'));
    }

    public function test_the_entry_takes_the_country_from_the_record(): void
    {
        $entry = $this->entry(TaxIdEntry::make('vat')->countryField('country'), new User(['vat' => '5260250995', 'country' => 'pl']));

        $this->assertSame('PL', $entry->getTaxIdCountry());
        $this->assertSame('PL 5260250995', $entry->formatState($entry->getState()));
    }

    public function test_an_enum_country_is_understood(): void
    {
        $this->assertSame('PL', TaxIdValidator::countryCode(TestCountry::Poland));
        $this->assertSame('GREECE', TaxIdValidator::countryCode(TestUnitCountry::Greece));
        $this->assertNull(TaxIdValidator::countryCode(' '));

        $input = TaxIdInput::make('vat')->country(fn (): TestCountry => TestCountry::Poland);
        $this->assertSame('PL', $input->getTaxIdCountry());

        $record = new class(['vat' => '5260250995', 'country' => 'pl']) extends User
        {
            protected function casts(): array
            {
                return ['country' => TestCountry::class];
            }
        };

        $entry = $this->entry(TaxIdEntry::make('vat')->countryField('country'), $record);

        $this->assertSame('PL', $entry->getTaxIdCountry());
        $this->assertSame('PL 5260250995', $entry->formatState($entry->getState()));
    }

    public function test_the_entry_copies_the_number_with_the_displayed_prefix(): void
    {
        $entry = $this->entry(TaxIdEntry::make('vat')->countryField('country'), new User(['vat' => '5260250995', 'country' => 'PL']));

        $this->assertSame('PL5260250995', $entry->getCopyableState('5260250995'));
    }

    public function test_greek_numbers_with_the_iso_prefix_pass_with_the_country_given(): void
    {
        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'GR', 'tax_id' => 'GR094014298'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_a_non_eu_country_is_not_verified_remotely(): void
    {
        Http::fake();

        Livewire::test(CompanyForm::class, ['remote' => true])
            ->fillForm(['country' => 'UA', 'tax_id' => 'DE12345'])
            ->call('save')
            ->assertHasNoFormErrors();

        Http::assertNothingSent();
    }

    public function test_the_entry_keeps_a_blank_state(): void
    {
        $entry = $this->entry(TaxIdEntry::make('vat'), ['vat' => null]);
        $this->assertNull($entry->formatState($entry->getState()));
    }

    /**
     * @param array<string, mixed>|User $state
     */
    private function entry(TaxIdEntry $entry, array|User $state): TaxIdEntry
    {
        $schema = Schema::make(Livewire::new(CompanyForm::class));
        $schema = ($state instanceof User ? $schema->record($state) : $schema->state($state))->components([$entry]);

        /** @var TaxIdEntry $built */
        $built = $schema->getComponents()[0];

        return $built;
    }
}

enum TestCountry: string
{
    case Poland = 'pl';
}

enum TestUnitCountry
{
    case Greece;

    public function test_an_acronym_label_is_kept_in_validation_messages(): void
    {
        $this->assertSame('VAT / Tax ID', TaxIdInput::make('t')->label('VAT / Tax ID')->getValidationAttribute());
        $this->assertSame('tax ID', TaxIdInput::make('t')->label('Tax ID')->getValidationAttribute());
        $this->assertSame('x', TaxIdInput::make('t')->label('VAT')->validationAttribute('x')->getValidationAttribute());
    }

    public function test_the_message_for_an_acronym_label_is_not_lowercased(): void
    {
        Livewire::test(CompanyForm::class)
            ->fillForm(['country' => 'PL', 'tax_id' => '526-025-09-96'])
            ->call('save')
            ->assertHasFormErrors(['tax_id'])
            ->assertSee('The VAT / Tax ID')
            ->assertDontSee('vAT');
    }
}
