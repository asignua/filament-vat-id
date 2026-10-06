<?php

declare(strict_types=1);

use Asignua\FilamentVatId\Registries\Ares;
use Asignua\FilamentVatId\Registries\BialaLista;
use Asignua\FilamentVatId\Registries\GusBir;
use Asignua\FilamentVatId\Registries\Vies;

return [
    /*
    | Registries used for lookups and remote verification, in order of preference. For a given country and
    | identifier type the manager asks every registry that supports it, in this order, and takes the first
    | answer that has data. Add your own class (it must implement CompanyRegistry; it is resolved from the
    | container) to plug in a paid provider, for example for Ukraine.
    */
    'registries' => [
        BialaLista::class,
        GusBir::class,
        Ares::class,
        Vies::class,
    ],

    'timeouts' => [
        'connect' => 5,
        'request' => 10,
    ],

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
