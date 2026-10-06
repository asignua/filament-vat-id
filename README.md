# Filament VAT ID

[![Tests](https://img.shields.io/github/actions/workflow/status/asignua/filament-vat-id/tests.yml?branch=main&label=tests)](https://github.com/asignua/filament-vat-id/actions/workflows/tests.yml)

Validate and look up tax identifiers in Filament forms. Offline rules check the format and the checksum of EU VAT numbers
(all 27 member states and Northern Ireland), Polish NIP and REGON, Czech IČO and DIČ and Ukrainian ЄДРПОУ and РНОКПП. An
optional "Look up" button asks a company registry (VIES, the Polish white list, GUS, ARES) and fills the name, address, REGON
and bank accounts into the sibling fields. No API key is needed except for GUS.

## Screenshots

TODO: add images to `art/` (cover.jpg first) and reference them here.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 5

## Installation

```bash
composer require asignua/filament-vat-id
```

Registering the plugin is optional (the fields and rules work without it); it only gives the panel provider a place to add
registries:

```php
use Asignua\FilamentVatId\VatIdPlugin;

$panel->plugin(VatIdPlugin::make());
```

Publish the config when you need to change anything: `php artisan vendor:publish --tag=filament-vat-id-config`.

## Usage

### The input

```php
use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Forms\Components\TaxIdInput;

TaxIdInput::make('vat_number')
    ->countryField('country')        // a sibling field holding the ISO country; or ->country('PL')
    ->type(TaxIdType::EuVat)         // the default
    ->vies()                         // verify against a registry on save (network)
    ->lookup()                       // "Look up" button
    ->fill([                         // CompanyData path => form state path (relative to the field's container)
        'name' => 'company_name',
        'address' => 'address',
        'regon' => 'regon',
        'bankAccounts.0' => 'iban',
    ]);
```

An EU VAT number carries its country prefix (`PL5260250995`) or takes the country from `country()` / `countryField()`
(`5260250995` + `PL`); a prefix that contradicts the country is an error. Greece is `EL` in VIES; `GR` works as the country.
Separators (spaces, dots, dashes) are ignored. `->normalized()` saves the value without them.

The other types have a fixed country, so they need no country:

```php
TaxIdInput::make('nip')->type(TaxIdType::PlNip)->lookup()->fill(['name' => 'company', 'address' => 'address']);
TaxIdInput::make('edrpou')->type(TaxIdType::UaEdrpou);
```

`->type()` keeps Filament's meaning for strings (`->type('tel')` is still the HTML input type); only a `TaxIdType` (or a
closure returning one) sets the identifier type.

What the button does: it validates the number offline, asks the registries that support the country and type (in the
configured order, the first with data wins), fills the mapped fields, shows the company name under the field and sends a
notification. "Not found", "registry unavailable" and "no registry for this country" are notifications, not exceptions.
Mapped values that are empty are skipped, so a lookup never wipes what the user typed.

`->vies()` adds a remote check on save, after the offline rule. What happens when no registry answers is decided by
`on_unavailable` (see Configuration). `->registry(Vies::class)` pins the check and the lookup to one registry.

### Rules

```php
use Asignua\FilamentVatId\Rules\TaxId;

['required', new TaxId(TaxIdType::PlNip)]
['nullable', TaxId::make(TaxIdType::EuVat)->country(fn () => request('country'))]
```

`TaxId` works offline; a blank value passes (add `required`). `Rules\RegisteredTaxId` is the remote counterpart. To use
the checks outside validation:

```php
use Asignua\FilamentVatId\Support\TaxIdValidator;

TaxIdValidator::isValid('PL 526-025-09-95', TaxIdType::EuVat);       // true
TaxIdValidator::isValid('45274649', TaxIdType::EuVat, 'CZ');         // true
TaxIdValidator::normalize('526-025-09-95', TaxIdType::PlNip);        // "5260250995"
TaxIdValidator::format('5260250995', TaxIdType::PlNip);              // "526-025-09-95"
```

### Offline checks

| Type | Checked |
| --- | --- |
| EU VAT | format of every member state + `XI`; checksum for AT, BE, CZ, DE, DK, EE, EL, ES, FI, FR, HR, HU, IE, IT, LT, LU, LV, MT, NL, PL, PT, RO, SE, SI, SK, XI. Format only: BG, CY (and French numbers with a letter in the key, Latvian natural persons) |
| `PlNip` | 10 digits, weights 6 5 7 2 3 4 5 6 7, mod 11 |
| `PlRegon` | 9 digits (weights 8 9 2 3 4 5 6 7) or 14 digits (plus weights 2 4 8 5 0 9 7 3 6 1 2 4 8) |
| `CzIco` | 8 digits, weights 8 7 6 5 4 3 2, mod 11 |
| `CzDic` | 8 digits = IČO; 9-10 digits = birth number (date + mod 11); 9 digits starting with 6 = format only |
| `UaEdrpou` | 8 digits, the official two-pass weighting (1..7, then 3..9; 7 1 2 3 4 5 6 for numbers 30 000 000 - 60 000 000) |
| `UaRnokpp` | 10 digits, weights -1 5 7 9 4 6 10 5 7, mod 11, mod 10 |

A checksum proves the number is well-formed, not that the company exists. Use the registries for that.

### The entry

```php
use Asignua\FilamentVatId\Infolists\Components\TaxIdEntry;

TaxIdEntry::make('vat_number')->countryField('country');      // "PL 5260250995", copyable
TaxIdEntry::make('nip')->type(TaxIdType::PlNip);                // "526-025-09-95"
```

### With asignua/filament-iban

There is no dependency in either direction. The lookup writes plain strings, so map a returned IBAN into any field:

```php
use Asignua\FilamentIban\Forms\Components\IbanInput; // asignua/filament-iban

TaxIdInput::make('nip')->type(TaxIdType::PlNip)->lookup()->fill(['name' => 'company', 'bankAccounts.0' => 'iban']),
IbanInput::make('iban'),
```

Only the Polish white list returns bank accounts (as IBANs, `PL` + the 26-digit account number). A company can have
dozens; `bankAccounts.0` is simply the first one the registry lists.

### Registries

| Country | Source | Class | Key | Returns |
| --- | --- | --- | --- | --- |
| EU + XI | [VIES](https://ec.europa.eu/taxation_customs/vies/) REST | `Vies` | no | valid flag; name and address where the member state shares them (`---` becomes empty) |
| PL | MF white list ([Biała lista](https://wl-api.mf.gov.pl)) | `BialaLista` | no | name, address (+ street / postcode / city), VAT status, NIP, REGON, KRS, bank accounts |
| PL | GUS BIR 1.1 (SOAP) | `GusBir` | yes (`FILAMENT_VAT_ID_GUS_KEY`) | name, address, NIP, REGON, whether the business was closed |
| CZ | [ARES](https://ares.gov.cz) REST | `Ares` | no | name, address (+ street / postcode / city), DIČ, IČO, ended or not. IČO (8 digits) only; a birth-number DIČ is not found |
| UA | none free | - | - | validation only |

Each registry answers `supports($country, $type)` without touching the network. `CompanyData` holds `name`, `address`,
`street`, `city`, `postcode`, `country`, `vatNumber`, `regon`, `ico`, `registryId` (KRS for Poland), `active`, `status`,
`bankAccounts` (IBANs), `source` and the registry's own record in `raw`.

#### Ukraine and other countries

There is no free, stable, key-less registry for Ukrainian companies, so `UaEdrpou` and `UaRnokpp` are validated offline only.
To add one (a paid provider, your own database, a different country), implement the contract and list the class:

```php
use Asignua\FilamentVatId\Contracts\CompanyRegistry;

class MyUkrainianRegistry implements CompanyRegistry
{
    public function supports(string $country, TaxIdType $type): bool
    {
        return $country === 'UA' && $type === TaxIdType::UaEdrpou;
    }

    public function lookup(string $country, string $number): ?CompanyData   // null = not found
    {
        // ... throw Asignua\FilamentVatId\Exceptions\RegistryUnavailable when the provider cannot answer
    }
}
```

```php
// config/filament-vat-id.php: 'registries' => [..., MyUkrainianRegistry::class]
// or on the panel:
$panel->plugin(VatIdPlugin::make()->registry(new MyUkrainianRegistry));
```

## Configuration

```bash
php artisan vendor:publish --tag=filament-vat-id-config
```

| Key | Default | |
| --- | --- | --- |
| `registries` | white list, GUS, ARES, VIES | Registry classes in order of preference; resolved from the container |
| `timeouts.connect` / `timeouts.request` | 5 / 10 s | |
| `cache.enabled` / `ttl` / `store` / `prefix` | on / 3600 s / default store | "Found" and "not found" are cached; "unavailable" never |
| `on_unavailable` | `warn` | `allow` accepts silently; `warn` accepts and shows a notification; `fail` rejects |
| `gus.key` | `FILAMENT_VAT_ID_GUS_KEY` | GUS is disabled while empty |
| `gus.environment` | `FILAMENT_VAT_ID_GUS_ENV` = `prod` | `test` uses the public test endpoint and key `abcde12345abcde12345` (scrambled data) |

## Gotchas

- **VIES is only as available as the member state behind it.** `MS_UNAVAILABLE` (DE and ES are frequent offenders, usually
  at night and weekends) means "try later", not "invalid". It surfaces as `RegistryUnavailable`, and `on_unavailable` decides
  whether the form still saves. For a hard requirement use `fail`; for a form people must be able to finish use `warn`.
- **The Polish white list is rate limited** (per IP, with a daily quota); HTTP 429 is reported as "unavailable". Keep the
  cache on, and do not run bulk imports through it. When it is down or throttled the manager falls through to GUS (if a key
  is configured) and then VIES.
- **GUS needs a key**: register at [api.stat.gov.pl](https://api.stat.gov.pl) and set `FILAMENT_VAT_ID_GUS_KEY`. The test
  environment works with the public key but returns made-up addresses. The session id is cached for 50 minutes and renewed
  when GUS drops it. A wrong key is answered with an empty session and shows up as "unavailable".
- **No ext-soap**: GUS is spoken as raw SOAP 1.2 over the HTTP client, the MTOM answer is parsed by cutting out the
  envelope. If GUS changes the wrapper, `GusBir::extractResult()` is the one place to look.
- **One registry saying "not found" does not hide another one being down**: with `[BialaLista, Vies]`, "not found" +
  "unavailable" is reported as unavailable, because the second might have known the company.
- **Ukraine has no free registry** (see above); the lookup button reports "no registry for this country".
- **The hint under the field** (company name after a lookup) lives in the form state under `__vat_id_<field>` next to the
  form data; it is not saved and disappears when the number changes.
- **Rules run on submit.** The remote check runs after the offline one and is skipped for a malformed number, so a typo
  never costs a network call.

## Data and privacy

A lookup or a `->vies()` check sends the identifier (and nothing else) to the registry: VIES (European Commission), the
Ministry of Finance white list, GUS or ARES, depending on the country. Those are public registers, but you are still
transmitting a number your user typed, so mention it in your privacy notice when the number identifies a sole proprietor
(a Polish NIP, a Czech birth-number DIČ, a Ukrainian РНОКПП). Results are kept in your cache for `cache.ttl` seconds and are
never written to a database by this package. Set `cache.enabled` to `false` to keep nothing. The package sends a
`User-Agent: asignua-filament-vat-id` header and no tracking of any kind.

## Translations

The interface ships in English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese and Turkish
under the `filament-vat-id::filament-vat-id` namespace. A test keeps every language in step with the English keys. Override a
string by publishing the translations (`--tag=filament-vat-id-translations`) and editing the copy in
`lang/vendor/filament-vat-id`.

## AI agents

The package ships [Laravel Boost](https://laravel.com/docs/boost) guidelines (`resources/boost/guidelines/core.blade.php`) so a
coding agent wires it up correctly.

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
