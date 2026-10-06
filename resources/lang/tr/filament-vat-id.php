<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'AB KDV numarası',
        'pl_nip' => 'Polonya NIP',
        'pl_regon' => 'Polonya REGON',
        'cz_ico' => 'Çek IČO',
        'cz_dic' => 'Çek DIČ',
        'ua_edrpou' => 'Ukrayna EDRPOU',
        'ua_rnokpp' => 'Ukrayna RNTRC (РНОКПП)',
    ],
    'validation' => [
        'invalid' => ':attribute alanı geçerli değil (:type).',
        'not_registered' => ':attribute alanı kayıtlı değil (:type).',
        'unavailable' => ':attribute alanı, sicil erişilemediği için doğrulanamadı. Daha sonra tekrar deneyin.',
        'inactive' => ':attribute alanı artık faal olmayan bir şirkete ait (:type).',
    ],
    'lookup' => [
        'action' => 'Sorgula',
        'empty' => 'Önce bir numara girin.',
        'invalid' => 'Numara geçerli değil (:type).',
        'no_registry' => 'Bu ülke için kullanılabilir sicil yok.',
        'not_found' => 'Bu numara için şirket bulunamadı.',
        'unavailable' => 'Sicil erişilemiyor. Daha sonra tekrar deneyin.',
        'found' => 'Şirket bulundu',
    ],
    'notifications' => [
        'unavailable_title' => 'Sicil erişilemiyor',
        'unavailable_saved' => 'Numara çevrimiçi doğrulanamadı ve girildiği gibi kabul edildi.',
    ],
    'entry' => [
        'copied' => 'Kopyalandı',
    ],
];
