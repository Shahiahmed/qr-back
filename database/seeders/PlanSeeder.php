<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * The three starter tariffs shown on the landing. Idempotent (keyed on the
 * Russian name), so it can be re-run on deploy without duplicating rows.
 *
 * Prices are in tiyn (1/100 ₸) — 25 000 ₸ is 2 500 000, never a float.
 * `max_establishments = null` means unlimited; enforcement (gating) is a
 * later feature, for now the tier just records the limit.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name_ru' => 'Бесплатный',
                'name_kk' => 'Тегін',
                'tagline_ru' => 'Попробовать на месяц',
                'tagline_kk' => 'Бір айға көру',
                'price' => 0,
                'discount_percent' => 0,
                'period' => 'month',
                'features' => [
                    ['ru' => '1 меню (заведение)', 'kk' => '1 мәзір (мекеме)'],
                    ['ru' => 'QR-код на стол', 'kk' => 'Үстелге QR-код'],
                    ['ru' => 'Доступ на 1 месяц', 'kk' => '1 айға қолжетімді'],
                ],
                'max_establishments' => 1,
                'is_active' => true,
                'is_featured' => false,
                'sort' => 1,
            ],
            [
                'name_ru' => 'Стандарт',
                'name_kk' => 'Стандарт',
                'tagline_ru' => 'Оптимальный выбор',
                'tagline_kk' => 'Оңтайлы таңдау',
                'price' => 25_000 * 100, // 25 000 ₸ in tiyn
                'discount_percent' => 0,
                'period' => 'year',
                'features' => [
                    ['ru' => 'Все функции', 'kk' => 'Барлық функциялар'],
                    ['ru' => '1 меню (заведение)', 'kk' => '1 мәзір (мекеме)'],
                    ['ru' => 'QR-коды на столы', 'kk' => 'Үстелдерге QR-кодтар'],
                    ['ru' => 'Оформление и логотип', 'kk' => 'Дизайн және логотип'],
                    ['ru' => 'Стоп-лист и цены', 'kk' => 'Стоп-парақ пен бағалар'],
                ],
                'max_establishments' => 1,
                'is_active' => true,
                'is_featured' => true, // «популярный» — самый оптимальный
                'sort' => 2,
            ],
            [
                'name_ru' => 'Премиум',
                'name_kk' => 'Премиум',
                'tagline_ru' => 'Без ограничений',
                'tagline_kk' => 'Шектеусіз',
                'price' => 100_000 * 100, // 100 000 ₸ in tiyn
                'discount_percent' => 0,
                'period' => 'year',
                'features' => [
                    ['ru' => 'Все функции', 'kk' => 'Барлық функциялар'],
                    ['ru' => 'Неограниченно меню и заведений', 'kk' => 'Шектеусіз мәзір мен мекеме'],
                    ['ru' => 'Приоритетная поддержка', 'kk' => 'Басым қолдау'],
                ],
                'max_establishments' => null, // unlimited
                'is_active' => true,
                'is_featured' => false,
                'sort' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['name_ru' => $plan['name_ru']], $plan);
        }
    }
}
