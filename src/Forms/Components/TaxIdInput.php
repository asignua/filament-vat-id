<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Forms\Components;

use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Exceptions\NumberNotSupported;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;
use Asignua\FilamentVatId\Rules\RegisteredTaxId;
use Asignua\FilamentVatId\Rules\TaxId;
use Asignua\FilamentVatId\Support\RegistryManager;
use Asignua\FilamentVatId\Support\TaxIdValidator;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * A text input for tax identifiers: offline format + checksum validation, optional remote verification on save
 * and an optional "Look up" button that fills sibling fields from a company registry.
 *
 *     TaxIdInput::make('vat_number')
 *         ->countryField('country')
 *         ->type(TaxIdType::EuVat)
 *         ->vies()
 *         ->lookup()
 *         ->fill(['name' => 'company_name', 'address' => 'address', 'bankAccounts.0' => 'iban']);
 */
class TaxIdInput extends TextInput
{
    protected TaxIdType|Closure $taxIdType = TaxIdType::EuVat;

    protected string|Closure|null $taxIdCountry = null;

    protected bool|Closure $verifiesRemotely = false;

    protected bool|Closure $hasLookup = false;

    /** @var array<string, string>|Closure */
    protected array|Closure $fillMap = [];

    protected ?string $registryClass = null;

    protected bool|Closure $normalizesState = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maxLength(40);
        $this->autocomplete(false);

        $this->rule(fn (Get $get): TaxId => new TaxId($this->getTaxIdType(), fn (): ?string => $this->getTaxIdCountry($get)));

        $this->rule(
            fn (Get $get): RegisteredTaxId => new RegisteredTaxId(
                $this->getTaxIdType(),
                fn (): ?string => $this->getTaxIdCountry($get),
                $this->registryClass,
                $this->warnUnavailable(...),
            ),
            fn (): bool => $this->isVerifiedRemotely(),
        );

        $this->suffixAction(
            fn (): Action => $this->makeLookupAction(),
        );

        // The company found by the last lookup is remembered in the session (not in the form state, which would leak
        // into the saved data), tied to the number it was found for.
        $this->hint(function (Get $get): ?string {
            $found = session($this->hintSessionKey());

            if (!is_array($found) || ($found['number'] ?? null) !== $this->currentNumber($get)) {
                return null;
            }

            return is_string($found['name'] ?? null) ? $found['name'] : null;
        });

        $this->dehydrateStateUsing(function (mixed $state): mixed {
            if (!is_string($state) || !$this->shouldNormalize()) {
                return $state;
            }

            $country = $this->getTaxIdCountry();
            $type = $this->getTaxIdType();

            if ($type === TaxIdType::EuVat) {
                $parts = TaxIdValidator::splitEuVat($state, $country);

                return $parts === null ? $state : $parts[0].$parts[1];
            }

            return TaxIdValidator::normalize($state, $type);
        });
    }

    /**
     * The identifier type. Strings keep Filament's meaning (the HTML input type: `->type('tel')`); a Closure
     * is read as returning a TaxIdType.
     */
    public function type(string|Closure|TaxIdType|null $type): static
    {
        if ($type instanceof TaxIdType || $type instanceof Closure) {
            $this->taxIdType = $type;

            return $this;
        }

        return parent::type($type);
    }

    public function taxIdType(TaxIdType|Closure $type): static
    {
        $this->taxIdType = $type;

        return $this;
    }

    public function getTaxIdType(): TaxIdType
    {
        $type = $this->evaluate($this->taxIdType);

        return $type instanceof TaxIdType ? $type : TaxIdType::EuVat;
    }

    /**
     * The country of an EU VAT number given without its prefix (ISO 3166-1 alpha-2), or a Closure returning it.
     */
    public function country(string|Closure|null $country): static
    {
        $this->taxIdCountry = $country;

        return $this;
    }

    /**
     * Takes the country from a sibling field (a path relative to this field's container).
     */
    public function countryField(string $field): static
    {
        $this->taxIdCountry = fn (Get $get): mixed => $get($field);

        return $this;
    }

    public function getTaxIdCountry(?Get $get = null): ?string
    {
        $country = $this->taxIdCountry instanceof Closure
            ? $this->evaluate($this->taxIdCountry, $get === null ? [] : ['get' => $get])
            : $this->taxIdCountry;

        if (is_string($country) && trim($country) !== '') {
            return strtoupper(trim($country));
        }

        return $this->getTaxIdType()->country();
    }

    /**
     * Checks the identifier against a registry when the form is saved (VIES for EU VAT numbers, …). Honours
     * `on_unavailable`: `warn` shows a notification and lets the value through.
     */
    public function vies(bool|Closure $condition = true): static
    {
        $this->verifiesRemotely = $condition;

        return $this;
    }

    /**
     * Pin the remote check and the lookup to one registry class (for example `Vies::class`).
     *
     * @param class-string<\Asignua\FilamentVatId\Contracts\CompanyRegistry>|null $registry
     */
    public function registry(?string $registry): static
    {
        $this->registryClass = $registry;

        return $this;
    }

    public function isVerifiedRemotely(): bool
    {
        return (bool) $this->evaluate($this->verifiesRemotely);
    }

    /**
     * Adds the "Look up" suffix button.
     */
    public function lookup(bool|Closure $condition = true): static
    {
        $this->hasLookup = $condition;

        return $this;
    }

    /**
     * Maps CompanyData property paths to form state paths relative to this field's container:
     * `['name' => 'company_name', 'bankAccounts.0' => 'iban']`. Empty values are never written.
     *
     * @param array<string, string>|Closure $map
     */
    public function fill(array|Closure $map): static
    {
        $this->fillMap = $map;

        return $this;
    }

    /**
     * Saves the identifier without spaces and dashes (`PL5260250995`, `5260250995`).
     */
    public function normalized(bool|Closure $condition = true): static
    {
        $this->normalizesState = $condition;

        return $this;
    }

    public function shouldNormalize(): bool
    {
        return (bool) $this->evaluate($this->normalizesState);
    }

    /**
     * @return array<string, string>
     */
    public function getFillMap(): array
    {
        $map = $this->evaluate($this->fillMap);

        return is_array($map) ? $map : [];
    }

    protected function hintSessionKey(): string
    {
        return 'filament-vat-id.hint.'.$this->getStatePath();
    }

    protected function currentNumber(Get $get): ?string
    {
        $state = $get($this->getName());

        return is_string($state) && $state !== '' ? TaxIdValidator::normalize($state, $this->getTaxIdType()) : null;
    }

    /**
     * @internal
     */
    public function warnUnavailable(string $number): void
    {
        Notification::make()
            ->warning()
            ->title(__('filament-vat-id::filament-vat-id.notifications.unavailable_title'))
            ->body(__('filament-vat-id::filament-vat-id.notifications.unavailable_saved'))
            ->send();
    }

    protected function makeLookupAction(): Action
    {
        return Action::make('lookup')
            ->label(__('filament-vat-id::filament-vat-id.lookup.action'))
            ->tooltip(__('filament-vat-id::filament-vat-id.lookup.action'))
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->visible(fn (): bool => (bool) $this->evaluate($this->hasLookup))
            ->action(function (Get $get, Set $set): void {
                $this->runLookup($get, $set);
            });
    }

    protected function runLookup(Get $get, Set $set): void
    {
        $type = $this->getTaxIdType();
        $raw = $get($this->getName());
        $raw = is_string($raw) || is_int($raw) ? trim((string) $raw) : '';

        if ($raw === '') {
            $this->notifyLookup('warning', 'lookup.empty');

            return;
        }

        $country = $this->getTaxIdCountry($get);

        if (!TaxIdValidator::isValid($raw, $type, $country)) {
            $this->notifyLookup('danger', 'lookup.invalid', ['type' => $type->label()]);

            return;
        }

        $manager = app(RegistryManager::class);

        if (!TaxIdValidator::isEuCountry($country) && $type === TaxIdType::EuVat
            || !$manager->supports($manager->resolve($country ?? '', $type, $raw)[0] ?? '', $type, $this->registryClass)) {
            $this->notifyLookup('warning', 'lookup.no_registry');

            return;
        }

        try {
            $company = $manager->lookup($country ?? '', $type, $raw, $this->registryClass);
        } catch (RegistryUnavailable) {
            $this->notifyLookup('danger', 'lookup.unavailable');

            return;
        } catch (NumberNotSupported) {
            $this->notifyLookup('warning', 'lookup.no_registry');

            return;
        }

        if ($company === null) {
            $this->notifyLookup('warning', 'lookup.not_found');

            return;
        }

        foreach ($this->getFillMap() as $property => $target) {
            $value = $company->get($property);

            if ($value !== null && is_scalar($value)) {
                $set($target, $value);
            }
        }

        session()->put($this->hintSessionKey(), [
            'number' => TaxIdValidator::normalize($raw, $type),
            'name' => $company->name,
        ]);

        Notification::make()
            ->success()
            ->title(__('filament-vat-id::filament-vat-id.lookup.found'))
            ->body(e($company->name))
            ->send();
    }

    /**
     * @param array<string, string> $replace
     */
    protected function notifyLookup(string $level, string $key, array $replace = []): void
    {
        Notification::make()
            ->status($level)
            ->title(__('filament-vat-id::filament-vat-id.'.$key, $replace))
            ->send();
    }
}
