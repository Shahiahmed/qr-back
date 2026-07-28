<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Establishment;
use Illuminate\Support\Facades\DB;

/**
 * Default bilingual menu applied to a brand-new venue.
 *
 * Kept in code (not a DB seed) so every environment gets the same skeleton,
 * and so a test can assert the shape without depending on artisan db:seed.
 * Prices are тиыны. Owners edit or delete anything afterwards.
 */
final class MenuStarter
{
    /**
     * @return bool False when the venue already has sections — never overwrite.
     */
    public static function apply(Establishment $establishment): bool
    {
        if ($establishment->categories()->exists()) {
            return false;
        }

        DB::transaction(function () use ($establishment): void {
            foreach (self::blueprint() as $categoryIndex => $section) {
                $category = $establishment->categories()->create([
                    'name_ru' => $section['name_ru'],
                    'name_kk' => $section['name_kk'],
                    'position' => $categoryIndex + 1,
                    'is_visible' => true,
                ]);

                foreach ($section['dishes'] as $dishIndex => $dish) {
                    $establishment->dishes()->create([
                        'menu_category_id' => $category->id,
                        'name_ru' => $dish['name_ru'],
                        'name_kk' => $dish['name_kk'],
                        'description_ru' => $dish['description_ru'],
                        'description_kk' => $dish['description_kk'],
                        'price' => $dish['price'],
                        'position' => $dishIndex + 1,
                        'is_visible' => true,
                        'is_available' => true,
                    ]);
                }
            }
        });

        return true;
    }

    /**
     * @return list<array{
     *   name_ru: string,
     *   name_kk: string,
     *   dishes: list<array{
     *     name_ru: string,
     *     name_kk: string,
     *     description_ru: string|null,
     *     description_kk: string|null,
     *     price: int
     *   }>
     * }>
     */
    public static function blueprint(): array
    {
        return [
            [
                'name_ru' => 'Горячее',
                'name_kk' => 'Ыстық',
                'dishes' => [
                    [
                        'name_ru' => 'Плов ташкентский',
                        'name_kk' => 'Ташкент палауы',
                        'description_ru' => 'Рассыпчатый рис с мясом из казана.',
                        'description_kk' => 'Қазандағы етпен палау.',
                        'price' => 249000,
                    ],
                    [
                        'name_ru' => 'Лагман уйгурский',
                        'name_kk' => 'Ұйғыр лағманы',
                        'description_ru' => 'Ручная лапша, мясо и овощи.',
                        'description_kk' => 'Қол кеспе, ет және көкөніс.',
                        'price' => 210000,
                    ],
                    [
                        'name_ru' => 'Манты (5 шт)',
                        'name_kk' => 'Мәнті (5 дана)',
                        'description_ru' => 'Сочная баранина в тонком тесте.',
                        'description_kk' => 'Жұқа қамырдағы қой еті.',
                        'price' => 189000,
                    ],
                ],
            ],
            [
                'name_ru' => 'Салаты',
                'name_kk' => 'Салаттар',
                'dishes' => [
                    [
                        'name_ru' => 'Цезарь с курицей',
                        'name_kk' => 'Тауық етті «Цезарь»',
                        'description_ru' => 'Ромэн, курица гриль, пармезан.',
                        'description_kk' => 'Ромэн, грильдегі тауық, пармезан.',
                        'price' => 290000,
                    ],
                    [
                        'name_ru' => 'Греческий салат',
                        'name_kk' => 'Грек салаты',
                        'description_ru' => null,
                        'description_kk' => null,
                        'price' => 240000,
                    ],
                ],
            ],
            [
                'name_ru' => 'Напитки',
                'name_kk' => 'Сусындар',
                'dishes' => [
                    [
                        'name_ru' => 'Чай в чайнике',
                        'name_kk' => 'Шайнектегі шай',
                        'description_ru' => 'На выбор: чёрный, зелёный, облепиха.',
                        'description_kk' => 'Қара, жасыл немесе шырғанақ.',
                        'price' => 99000,
                    ],
                    [
                        'name_ru' => 'Компот из сухофруктов',
                        'name_kk' => 'Кептірілген жеміс компоты',
                        'description_ru' => null,
                        'description_kk' => null,
                        'price' => 60000,
                    ],
                    [
                        'name_ru' => 'Айран',
                        'name_kk' => 'Айран',
                        'description_ru' => null,
                        'description_kk' => null,
                        'price' => 50000,
                    ],
                ],
            ],
        ];
    }
}
