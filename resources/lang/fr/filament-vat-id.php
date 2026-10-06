<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'Numéro de TVA de l’UE',
        'pl_nip' => 'NIP polonais',
        'pl_regon' => 'REGON polonais',
        'cz_ico' => 'IČO tchèque',
        'cz_dic' => 'DIČ tchèque',
        'ua_edrpou' => 'EDRPOU ukrainien',
        'ua_rnokpp' => 'RNTRC ukrainien (РНОКПП)',
    ],
    'validation' => [
        'invalid' => 'Le champ :attribute n’est pas valide (:type).',
        'not_registered' => 'Le champ :attribute n’est pas enregistré (:type).',
        'unavailable' => 'Le champ :attribute n’a pas pu être vérifié car le registre est indisponible. Réessayez plus tard.',
    ],
    'lookup' => [
        'action' => 'Rechercher',
        'empty' => 'Saisissez d’abord un numéro.',
        'invalid' => 'Le numéro n’est pas valide (:type).',
        'no_registry' => 'Aucun registre n’est disponible pour ce pays.',
        'not_found' => 'Aucune entreprise trouvée pour ce numéro.',
        'unavailable' => 'Le registre est indisponible. Réessayez plus tard.',
        'found' => 'Entreprise trouvée',
    ],
    'notifications' => [
        'unavailable_title' => 'Registre indisponible',
        'unavailable_saved' => 'Le numéro n’a pas pu être vérifié en ligne et a été accepté tel que saisi.',
    ],
    'entry' => [
        'copied' => 'Copié',
    ],
];
