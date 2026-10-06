<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'Partita IVA UE',
        'pl_nip' => 'NIP polacco',
        'pl_regon' => 'REGON polacco',
        'cz_ico' => 'IČO ceco',
        'cz_dic' => 'DIČ ceco',
        'ua_edrpou' => 'EDRPOU ucraino',
        'ua_rnokpp' => 'RNTRC ucraino (РНОКПП)',
    ],
    'validation' => [
        'invalid' => 'Il campo :attribute non è valido (:type).',
        'not_registered' => 'Il campo :attribute non è registrato (:type).',
        'unavailable' => 'Impossibile verificare il campo :attribute perché il registro non è disponibile. Riprova più tardi.',
    ],
    'lookup' => [
        'action' => 'Cerca',
        'empty' => 'Inserisci prima un numero.',
        'invalid' => 'Il numero non è valido (:type).',
        'no_registry' => 'Nessun registro disponibile per questo paese.',
        'not_found' => 'Nessuna azienda trovata per questo numero.',
        'unavailable' => 'Il registro non è disponibile. Riprova più tardi.',
        'found' => 'Azienda trovata',
    ],
    'notifications' => [
        'unavailable_title' => 'Registro non disponibile',
        'unavailable_saved' => 'Non è stato possibile verificare il numero online ed è stato accettato così com’è.',
    ],
    'entry' => [
        'copied' => 'Copiato',
    ],
];
