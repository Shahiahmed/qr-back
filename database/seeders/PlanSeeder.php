<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * The starter tariffs shown on the landing. Billing is per menu, so the tiers
 * differ ONLY by how long one menu stays live: a free month, half a year, or a
 * full year. There is no «unlimited menus» tier anymore — each menu is paid
 * separately.
 *
 * Idempotent (keyed on the Russian name), so it can be re-run without
 * duplicating rows. Prices are in tiyn (1/100 ₸) — 25 000 ₸ is 2 500 000,
 * never a float. `max_establishments` stays at 1: a plan grants one menu.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Every paid tier unlocks the same feature set — the only variable is
        // duration — so they share this list.
        $fullFeatures = [
            ['ru' => 'Все функции меню', 'kk' => 'Мәзірдің барлық функциялары'],
            ['ru' => 'QR-коды на столы', 'kk' => 'Үстелдерге QR-кодтар'],
            ['ru' => 'Оформление и логотип', 'kk' => 'Дизайн және логотип'],
            ['ru' => 'Стоп-лист и цены', 'kk' => 'Стоп-парақ пен бағалар'],
        ];

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
                    ['ru' => '1 меню на 1 месяц', 'kk' => '1 мәзір 1 айға'],
                    ['ru' => 'QR-код на стол', 'kk' => 'Үстелге QR-код'],
                    ['ru' => 'Все функции меню', 'kk' => 'Мәзірдің барлық функциялары'],
                ],
                'max_establishments' => 1,
                'is_active' => true,
                'is_featured' => false,
                'sort' => 1,
            ],
            [
                'name_ru' => 'На 6 месяцев',
                'name_kk' => '6 айға',
                'tagline_ru' => 'Одно меню на полгода',
                'tagline_kk' => 'Бір мәзір жарты жылға',
                'price' => 15_000 * 100, // 15 000 ₸ in tiyn — placeholder, editable in /admin
                'discount_percent' => 0,
                'period' => 'halfyear',
                'features' => $fullFeatures,
                'max_establishments' => 1,
                'is_active' => true,
                'is_featured' => false,
                'sort' => 2,
            ],
            [
                'name_ru' => 'На год',
                'name_kk' => 'Бір жылға',
                'tagline_ru' => 'Одно меню на год — выгоднее',
                'tagline_kk' => 'Бір мәзір бір жылға — тиімді',
                'price' => 25_000 * 100, // 25 000 ₸ in tiyn — placeholder, editable in /admin
                'discount_percent' => 0,
                'period' => 'year',
                'features' => $fullFeatures,
                'max_establishments' => 1,
                'is_active' => true,
                'is_featured' => true, // «популярный» — лучшая цена за меню
                'sort' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['name_ru' => $plan['name_ru']], $plan);
        }
    }
}
