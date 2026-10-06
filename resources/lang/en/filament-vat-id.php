<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'EU VAT number',
        'pl_nip' => 'Polish NIP',
        'pl_regon' => 'Polish REGON',
        'cz_ico' => 'Czech IČO',
        'cz_dic' => 'Czech DIČ',
        'ua_edrpou' => 'Ukrainian EDRPOU',
        'ua_rnokpp' => 'Ukrainian RNTRC (РНОКПП)',
    ],
    'validation' => [
        'invalid' => 'The :attribute is not a valid :type.',
        'not_registered' => 'The :attribute is not registered (:type).',
        'unavailable' => 'The :attribute could not be verified because the registry is unavailable. Try again later.',
    ],
    'lookup' => [
        'action' => 'Look up',
        'empty' => 'Enter a number first.',
        'invalid' => 'The number is not a valid :type.',
        'no_registry' => 'No registry is available for this country.',
        'not_found' => 'No company found for this number.',
        'unavailable' => 'The registry is unavailable. Try again later.',
        'found' => 'Company found',
    ],
    'notifications' => [
        'unavailable_title' => 'Registry unavailable',
        'unavailable_saved' => 'The number could not be verified online and was accepted as entered.',
    ],
    'entry' => [
        'copied' => 'Copied',
    ],
];
