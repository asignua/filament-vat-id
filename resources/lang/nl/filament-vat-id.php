<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'EU-btw-nummer',
        'pl_nip' => 'Pools NIP',
        'pl_regon' => 'Pools REGON',
        'cz_ico' => 'Tsjechisch IČO',
        'cz_dic' => 'Tsjechisch DIČ',
        'ua_edrpou' => 'Oekraïens EDRPOU',
        'ua_rnokpp' => 'Oekraïens RNTRC (РНОКПП)',
    ],
    'validation' => [
        'invalid' => 'Het veld :attribute is niet geldig (:type).',
        'not_registered' => 'Het veld :attribute is niet geregistreerd (:type).',
        'unavailable' => 'Het veld :attribute kon niet worden gecontroleerd omdat het register niet beschikbaar is. Probeer het later opnieuw.',
    ],
    'lookup' => [
        'action' => 'Opzoeken',
        'empty' => 'Voer eerst een nummer in.',
        'invalid' => 'Het nummer is niet geldig (:type).',
        'no_registry' => 'Er is geen register beschikbaar voor dit land.',
        'not_found' => 'Geen bedrijf gevonden voor dit nummer.',
        'unavailable' => 'Het register is niet beschikbaar. Probeer het later opnieuw.',
        'found' => 'Bedrijf gevonden',
    ],
    'notifications' => [
        'unavailable_title' => 'Register niet beschikbaar',
        'unavailable_saved' => 'Het nummer kon online niet worden gecontroleerd en is overgenomen zoals ingevoerd.',
    ],
    'entry' => [
        'copied' => 'Gekopieerd',
    ],
];
