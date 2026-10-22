<?php

/*
 * Only the rules the API actually uses; the rest falls back to English.
 */
return [
    'required' => 'Бұл өрісті толтырыңыз.',
    'email' => 'Дұрыс email енгізіңіз.',
    'confirmed' => 'Құпиясөздер сәйкес келмейді.',
    'current_password' => 'Қазіргі құпиясөз қате.',
    'unique' => 'Бұл мән бос емес.',
    'boolean' => 'Жарамсыз мән.',
    'string' => 'Мән мәтін болуы керек.',

    'min' => [
        'string' => 'Кемінде :min таңба.',
    ],

    'max' => [
        'string' => ':max таңбадан аспауы керек.',
    ],

    'custom' => [
        'phone' => [
            'required' => 'WhatsApp нөмірін енгізіңіз.',
            'regex' => 'Нөмірді толық енгізіңіз, мысалы +7 701 123 45 67.',
        ],
        'email' => [
            'unique' => 'Бұл email тіркелген.',
        ],
        'slug' => [
            'unique' => 'Бұл қысқа атау бос емес, басқасын таңдаңыз.',
            'regex' => 'Тек латын әріптері, сандар және дефис.',
            'reserved' => 'Бұл атауды сервис қолданады, басқасын таңдаңыз.',
        ],
    ],

    'attributes' => [],
];
