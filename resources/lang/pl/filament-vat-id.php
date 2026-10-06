<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'Numer VAT UE',
        'pl_nip' => 'Polski NIP',
        'pl_regon' => 'Polski REGON',
        'cz_ico' => 'Czeski IČO',
        'cz_dic' => 'Czeski DIČ',
        'ua_edrpou' => 'Ukraiński EDRPOU',
        'ua_rnokpp' => 'Ukraiński RNTRC (РНОКПП)',
    ],
    'validation' => [
        'invalid' => 'Pole :attribute nie jest prawidłowe (:type).',
        'not_registered' => 'Wartość pola :attribute nie jest zarejestrowana (:type).',
        'unavailable' => 'Nie udało się zweryfikować pola :attribute, ponieważ rejestr jest niedostępny. Spróbuj ponownie później.',
    ],
    'lookup' => [
        'action' => 'Wyszukaj',
        'empty' => 'Najpierw wpisz numer.',
        'invalid' => 'Numer jest nieprawidłowy (:type).',
        'no_registry' => 'Brak dostępnego rejestru dla tego kraju.',
        'not_found' => 'Nie znaleziono firmy dla tego numeru.',
        'unavailable' => 'Rejestr jest niedostępny. Spróbuj ponownie później.',
        'found' => 'Znaleziono firmę',
    ],
    'notifications' => [
        'unavailable_title' => 'Rejestr niedostępny',
        'unavailable_saved' => 'Nie udało się zweryfikować numeru online, został zapisany w podanej postaci.',
    ],
    'entry' => [
        'copied' => 'Skopiowano',
    ],
];
