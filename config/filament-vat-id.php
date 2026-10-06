<?php

declare(strict_types=1);

use Asignua\FilamentVatId\Registries\Ares;
use Asignua\FilamentVatId\Registries\BialaLista;
use Asignua\FilamentVatId\Registries\GusBir;
use Asignua\FilamentVatId\Registries\Vies;

return [
    /*
    | Registries used for lookups and remote verification, in order of preference. For a lookup the manager asks
    | every registry that supports the country and type, in this order, and takes the first answer that has data.
    | Verification (`TaxIdInput::vies()`, `RegisteredTaxId`) uses only registries that are authoritative for the
    | type (CompanyRegistry::canVerify()): VIES for EU VAT numbers, ARES for Czech IČO / DIČ, GUS for Polish
    | NIP / REGON. The Polish white list is for lookups only. Add your own class (it must implement CompanyRegistry; it is resolved from the
    | container) to plug in a paid provider, for example for Ukraine.
    */
    'registries' => [
        BialaLista::class,
        GusBir::class,
        Ares::class,
        Vies::class,
    ],

    'timeouts' => [
        'connect' => 3,
        'request' => 6,
    ],

    /*
    | Upper bound, in seconds, for one lookup or verification across all registries (a registry that is already
    | running still finishes within its own timeouts). Verification also stops at the first unavailable registry.
    */
    'total_timeout' => 12,

    /*
    | Lookup results are cached by registry + country + number. "Not found" is cached too; an unavailable
    | registry never is. `store` null = the default cache store.
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'store' => null,
        'prefix' => 'filament-vat-id',
    ],

    /*
    | What to do when every registry is unavailable during remote validation (`TaxIdInput::vies()`):
    |   allow - accept the value silently;
    |   warn  - accept it and show a warning notification;
    |   fail  - reject it with a validation error.
    */
    'on_unavailable' => 'warn',

    'vies' => [
        'url' => 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number',
    ],

    'biala_lista' => [
        'url' => 'https://wl-api.mf.gov.pl/api/search',
    ],

    'ares' => [
        'url' => 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty',
    ],

    /*
    | GUS BIR 1.1 (Polish REGON registry). Register for a key at https://api.stat.gov.pl. The registry is
    | disabled while the key is empty. `environment` = prod | test; the test environment works with the public
    | key abcde12345abcde12345 (and returns scrambled addresses).
    */
    'gus' => [
        'key' => env('FILAMENT_VAT_ID_GUS_KEY'),
        'environment' => env('FILAMENT_VAT_ID_GUS_ENV', 'prod'),
        'endpoints' => [
            'prod' => 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc',
            'test' => 'https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc',
        ],
        'test_key' => 'abcde12345abcde12345',
    ],
];
