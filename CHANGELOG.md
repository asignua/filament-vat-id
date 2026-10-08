# Changelog

All notable changes to `asignua/filament-vat-id` are documented here.

## Unreleased

- Docs: screenshots and a cover in `art/`, wired into the README; a workbench demo (Acme Supply data, `DemoSeeder`) to reproduce them.
- Fix: `TaxIdInput` keeps an acronym label as written in validation messages ("The VAT / Tax ID is not..." instead of "The vAT / Tax ID is not..."); Filament's `lcfirst` still applies to other labels and an explicit `->validationAttribute()` wins.

## v1.0.1 - 2026-10-08

- Fixed: a country given as an enum (a Select backed by an enum, an enum-cast model attribute) is understood by `TaxIdInput` and `TaxIdEntry`.
- Fixed: GUS lookup picks the open record when a NIP lists a closed earlier activity first.
- Fixed: a Greek number typed with the ISO `GR` prefix passes when the country is `GR`.
- Fixed: an EU VAT field with a non-EU country is never verified remotely, as documented.
- Fixed: `TaxIdEntry` copies the number with the country prefix it displays.
- Fixed: the "Look up" button is disabled on a disabled or read-only field.
- Fixed: a transport error carrying an HTTP error response is reported as "registry unavailable" instead of crashing.
- Fixed: ARES no longer confirms a Czech DIČ when the subject has no DIČ issued.

## v1.0.0

- `TaxIdInput` form field: offline format + checksum validation, `->vies()` remote check on save, `->lookup()` button that
  fills sibling fields from a company registry (`->fill([...])`), company name hint after a lookup.
- `TaxIdEntry` infolist entry: formatted and copyable.
- `Rules\TaxId` and `Rules\RegisteredTaxId`, `Support\TaxIdValidator` for use outside forms.
- Offline validation of EU VAT numbers (27 member states + XI; checksums for 26 of them, BG and CY by format), Polish NIP and
  REGON (9 and 14 digits), Czech IČO and DIČ, Ukrainian ЄДРПОУ and РНОКПП.
- `Contracts\CompanyRegistry` and `Data\CompanyData`; registries `Vies`, `BialaLista`, `GusBir` (SOAP without ext-soap) and
  `Ares`; `Support\RegistryManager` with ordered fallback, caching and the `on_unavailable` policy (`allow`, `warn`, `fail`).
- Remote verification is answered only by authoritative registries (`CompanyRegistry::canVerify()`): VIES for EU VAT, ARES for
  Czech IČO / DIČ, GUS for Polish NIP / REGON; inactive companies are rejected; numbers a registry cannot resolve are skipped.
- `CompanyRegistry::lookup()` receives the `TaxIdType`; a total timeout bounds one lookup.
- Translations: de, en, es, fr, it, nl, pl, pt_BR, tr, uk.
- Laravel Boost guidelines.
