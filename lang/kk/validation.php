<?php

/*
 * Only the rules the API actually uses; the rest falls back to English.
 */
return [
    'required' => 'Бұл өрісті толтырыңыз.',
    'email' => 'Дұрыс email енгізіңіз.',
    'confirmed' => 'Құпиясөздер сәйкес келмейді.',
    'unique' => 'Бұл email тіркелген.',
    'boolean' => 'Жарамсыз мән.',
    'string' => 'Мән мәтін болуы керек.',

    'min' => [
        'string' => 'Кемінде :min таңба.',
    ],

    'max' => [
        'string' => ':max таңбадан аспауы керек.',
    ],

    'attributes' => [],
];
