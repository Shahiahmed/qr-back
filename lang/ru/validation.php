<?php

/*
 * Only the rules the API actually uses. Anything missing falls through to the
 * English file via `fallback_locale`, so this stays short instead of carrying
 * a translation of every rule Laravel ships.
 */
return [
    'required' => 'Заполните это поле.',
    'email' => 'Введите корректный email.',
    'confirmed' => 'Пароли не совпадают.',
    'unique' => 'Это значение уже занято.',
    'boolean' => 'Недопустимое значение.',
    'string' => 'Значение должно быть текстом.',

    'min' => [
        'string' => 'Минимум :min символов.',
    ],

    'max' => [
        'string' => 'Не длиннее :max символов.',
    ],

    /*
     * Field names are not interpolated into the messages above, so the
     * attribute list stays empty on purpose — a message like «Заполните это
     * поле» reads better next to its own input than one naming the field.
     */
    /*
     * `unique` fires for several fields, so the generic message above stays
     * neutral and each field says what it actually means.
     */
    'custom' => [
        'email' => [
            'unique' => 'Этот email уже зарегистрирован.',
        ],
        'slug' => [
            'unique' => 'Это короткое имя уже занято, выберите другое.',
            'regex' => 'Только латиница, цифры и дефис.',
            'reserved' => 'Это имя занято сервисом, выберите другое.',
        ],
    ],

    'attributes' => [],
];
