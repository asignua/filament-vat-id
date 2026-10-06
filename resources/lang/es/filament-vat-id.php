<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'Número de IVA de la UE',
        'pl_nip' => 'NIP polaco',
        'pl_regon' => 'REGON polaco',
        'cz_ico' => 'IČO checo',
        'cz_dic' => 'DIČ checo',
        'ua_edrpou' => 'EDRPOU ucraniano',
        'ua_rnokpp' => 'RNTRC ucraniano (РНОКПП)',
    ],
    'validation' => [
        'invalid' => 'El campo :attribute no es válido (:type).',
        'not_registered' => 'El campo :attribute no está registrado (:type).',
        'unavailable' => 'No se pudo verificar el campo :attribute porque el registro no está disponible. Inténtelo más tarde.',
    ],
    'lookup' => [
        'action' => 'Buscar',
        'empty' => 'Introduzca primero un número.',
        'invalid' => 'El número no es válido (:type).',
        'no_registry' => 'No hay ningún registro disponible para este país.',
        'not_found' => 'No se encontró ninguna empresa con este número.',
        'unavailable' => 'El registro no está disponible. Inténtelo más tarde.',
        'found' => 'Empresa encontrada',
    ],
    'notifications' => [
        'unavailable_title' => 'Registro no disponible',
        'unavailable_saved' => 'No se pudo verificar el número en línea y se aceptó tal como se introdujo.',
    ],
    'entry' => [
        'copied' => 'Copiado',
    ],
];
