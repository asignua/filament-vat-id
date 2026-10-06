<?php

declare(strict_types=1);

return [
    'types' => [
        'eu_vat' => 'VAT-номер ЄС',
        'pl_nip' => 'Польський NIP',
        'pl_regon' => 'Польський REGON',
        'cz_ico' => 'Чеський IČO',
        'cz_dic' => 'Чеський DIČ',
        'ua_edrpou' => 'ЄДРПОУ',
        'ua_rnokpp' => 'РНОКПП',
    ],
    'validation' => [
        'invalid' => 'Поле :attribute не є дійсним значенням (:type).',
        'not_registered' => 'Значення поля :attribute не знайдено в реєстрі (:type).',
        'unavailable' => 'Не вдалося перевірити поле :attribute: реєстр недоступний. Спробуйте пізніше.',
    ],
    'lookup' => [
        'action' => 'Знайти',
        'empty' => 'Спочатку введіть номер.',
        'invalid' => 'Номер не є дійсним (:type).',
        'no_registry' => 'Для цієї країни немає доступного реєстру.',
        'not_found' => 'Компанію за цим номером не знайдено.',
        'unavailable' => 'Реєстр недоступний. Спробуйте пізніше.',
        'found' => 'Компанію знайдено',
    ],
    'notifications' => [
        'unavailable_title' => 'Реєстр недоступний',
        'unavailable_saved' => 'Номер не вдалося перевірити онлайн, його збережено як введено.',
    ],
    'entry' => [
        'copied' => 'Скопійовано',
    ],
];
