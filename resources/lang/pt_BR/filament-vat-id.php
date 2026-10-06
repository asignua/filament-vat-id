<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'Número de IVA da UE',
        'pl_nip' => 'NIP polonês',
        'pl_regon' => 'REGON polonês',
        'cz_ico' => 'IČO tcheco',
        'cz_dic' => 'DIČ tcheco',
        'ua_edrpou' => 'EDRPOU ucraniano',
        'ua_rnokpp' => 'RNTRC ucraniano (РНОКПП)',
    ],
    'validation' => [
        'invalid' => 'O campo :attribute não é válido (:type).',
        'not_registered' => 'O campo :attribute não está registrado (:type).',
        'unavailable' => 'Não foi possível verificar o campo :attribute porque o registro está indisponível. Tente novamente mais tarde.',
        'inactive' => 'O campo :attribute pertence a uma empresa que não está mais ativa (:type).',
    ],
    'lookup' => [
        'action' => 'Consultar',
        'empty' => 'Informe um número primeiro.',
        'invalid' => 'O número não é válido (:type).',
        'no_registry' => 'Nenhum registro disponível para este país.',
        'not_found' => 'Nenhuma empresa encontrada para este número.',
        'unavailable' => 'O registro está indisponível. Tente novamente mais tarde.',
        'found' => 'Empresa encontrada',
    ],
    'notifications' => [
        'unavailable_title' => 'Registro indisponível',
        'unavailable_saved' => 'Não foi possível verificar o número on-line e ele foi aceito como informado.',
    ],
    'entry' => [
        'copied' => 'Copiado',
    ],
];
