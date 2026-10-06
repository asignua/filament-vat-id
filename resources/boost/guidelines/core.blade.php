## Filament VAT ID (asignua/filament-vat-id)

Validates tax identifiers offline (format + checksum) and optionally looks companies up in registries. Namespace `Asignua\FilamentVatId`. The plugin needs no registration for the fields and rules; `VatIdPlugin::make()->registry(...)` only adds custom registries.

- Form field: `TaxIdInput::make('vat_number')->countryField('country')->type(TaxIdType::EuVat)`. `->type()` takes a `TaxIdType` (`EuVat`, `PlNip`, `PlRegon`, `CzIco`, `CzDic`, `UaEdrpou`, `UaRnokpp`); a string still means the HTML input type. EU VAT numbers take the country from their prefix (`PL5260250995`) or from `->country('PL')` / `->countryField('country')`.
- `->vies()` verifies against a registry on save (network). `->lookup()` adds a "Look up" suffix button; `->fill(['name' => 'company_name', 'address' => 'address', 'bankAccounts.0' => 'iban', 'regon' => 'regon'])` maps `CompanyData` property paths to form state paths relative to the field's container. Empty values are never written.
- Plain rule: `new Rules\TaxId(TaxIdType::PlNip)` (blank passes; add `required`), `Rules\RegisteredTaxId` for the remote check. Offline checks only: `Support\TaxIdValidator::isValid($value, $type, $country)`.
- Infolist: `TaxIdEntry::make('vat_number')->countryField('country')` (formatted, copyable).
- Registries (`config/filament-vat-id.php`, `registries` = order of preference): `Vies` (EU VAT), `BialaLista` (PL, has bank accounts), `GusBir` (PL, needs `FILAMENT_VAT_ID_GUS_KEY`; off without it), `Ares` (CZ). Ukraine has no free registry: implement `Contracts\CompanyRegistry` and list the class in the config.
- `on_unavailable` = `allow|warn|fail` decides what happens to a remote check when no registry answers (VIES member-state outages are common; "unavailable" is never "invalid"). Lookup results are cached (`cache.ttl`); never call registries in a loop without the cache.
- Tests must not hit the network: use `Http::fake()` with recorded responses.
