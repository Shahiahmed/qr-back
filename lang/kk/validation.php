<?php

/*
 * Only the rules the API actually uses; the rest falls back to English.
 */
return [
    'required' => 'Бұл өрісті толтырыңыз.',
    'email' => 'Дұрыс email енгізіңіз.',
    'confirmed' => 'Құпиясөздер сәйкес келмейді.',
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
