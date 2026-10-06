# Changelog

All notable changes to `asignua/filament-vat-id` are documented here.

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
