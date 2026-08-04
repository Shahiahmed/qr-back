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
        // The duration tiers (6 months / year) unlock the same feature set — the
        // only variable is how long — so they share this list. They lift the free
        // tier's section/dish caps, so that is spelled out here.
        $fullFeatures = [
            ['ru' => 'Разделы и блюда без ограничений', 'kk' => 'Бөлімдер мен тағамдар шектеусіз'],
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
                    ['ru' => 'До 3 разделов, 5 блюд в разделе', 'kk' => '3 бөлімге дейін, бөлімде 5 тағам'],
                    ['ru' => 'QR-код на стол', 'kk' => 'Үстелге QR-код'],
                ],
                'max_establishments' => 1,
                // Content caps for the free tier — enforced server-side. The trial
                // week shares them (falls back to this plan). Paid tiers leave
                // these null (unlimited).
                'max_categories' => 3,
                'max_dishes_per_category' => 5,
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
            [
                // Bespoke top tier: the client's menu is deployed separately on its
                // own site (not our constructor), with orders, cart and per-dish
                // SEO. Those features are not built yet — for now this is a
                // catalogue entry / sales anchor, activated manually like the rest.
                'name_ru' => 'Премиум',
                'name_kk' => 'Премиум',
                'tagline_ru' => 'Отдельный сайт меню с заказами',
                'tagline_kk' => 'Тапсырыстары бар жеке мәзір сайты',
                'price' => 200_000 * 100, // 200 000 ₸ in tiyn — placeholder, editable in /admin
                'discount_percent' => 0,
                'period' => 'year',
                'features' => [
                    ['ru' => 'Отдельный сайт меню на своём домене', 'kk' => 'Жеке доменде бөлек мәзір сайты'],
                    ['ru' => 'Приём заказов и корзина', 'kk' => 'Тапсырыстарды қабылдау және себет'],
                    ['ru' => 'SEO для каждого блюда', 'kk' => 'Әр тағамға SEO'],
                    ['ru' => 'Разделы и блюда без ограничений', 'kk' => 'Бөлімдер мен тағамдар шектеусіз'],
                    ['ru' => 'Приоритетная поддержка', 'kk' => 'Басым қолдау'],
                ],
                'max_establishments' => 1,
                'is_active' => true,
                'is_featured' => false,
                'sort' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['name_ru' => $plan['name_ru']], $plan);
        }
    }
}
