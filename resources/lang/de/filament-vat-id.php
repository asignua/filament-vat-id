<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'EU-USt-IdNr.',
        'pl_nip' => 'Polnische NIP',
        'pl_regon' => 'Polnische REGON',
        'cz_ico' => 'Tschechische IČO',
        'cz_dic' => 'Tschechische DIČ',
        'ua_edrpou' => 'Ukrainische EDRPOU',
        'ua_rnokpp' => 'Ukrainische RNTRC (РНОКПП)',
    ],
    'validation' => [
        'invalid' => 'Das Feld :attribute ist keine gültige Angabe (:type).',
        'not_registered' => 'Das Feld :attribute ist nicht registriert (:type).',
        'unavailable' => 'Das Feld :attribute konnte nicht geprüft werden, da das Register nicht erreichbar ist. Bitte später erneut versuchen.',
    ],
    'lookup' => [
        'action' => 'Abfragen',
        'empty' => 'Bitte zuerst eine Nummer eingeben.',
        'invalid' => 'Die Nummer ist ungültig (:type).',
        'no_registry' => 'Für dieses Land ist kein Register verfügbar.',
        'not_found' => 'Zu dieser Nummer wurde kein Unternehmen gefunden.',
        'unavailable' => 'Das Register ist nicht erreichbar. Bitte später erneut versuchen.',
        'found' => 'Unternehmen gefunden',
    ],
    'notifications' => [
        'unavailable_title' => 'Register nicht erreichbar',
        'unavailable_saved' => 'Die Nummer konnte online nicht geprüft werden und wurde wie eingegeben übernommen.',
    ],
    'entry' => [
        'copied' => 'Kopiert',
    ],
];
