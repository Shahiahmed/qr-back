<?php

namespace Database\Seeders;

use App\Models\PromoSetting;
use Illuminate\Database\Seeder;

/**
 * Starter copy for the landing promo pop-up (the singleton `promo_settings`
 * row). Fills only the BLANK content columns, so re-running never clobbers what
 * an admin later edited in `/admin → Акция` — same discipline as SeoSeeder.
 *
 * Deliberately does NOT touch `enabled`, `starts_at` or `ends_at`: seeding must
 * never switch a live promo on by itself. The admin fills/edits the text, then
 * flips the toggle when ready.
 *
 * Run MANUALLY (`php artisan db:seed --class=PromoSeeder`) or via the one-tap
 * button in `/admin` → Обслуживание. Never from deploy.sh.
 */
class PromoSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'badge_ru' => '−30%',
            'badge_kk' => '−30%',

            'title_ru' => 'Скидка 30% на первый год',
            'title_kk' => 'Бірінші жылға 30% жеңілдік',

            'body_ru' => 'Запустите цифровое меню со скидкой до конца месяца: '
                .'QR-код на стол, стоп-лист и заказы в один тап.',
            'body_kk' => 'Осы ай соңына дейін цифрлық мәзірді жеңілдікпен қосыңыз: '
                .'үстелге QR-код, стоп-парақ және бір түртумен тапсырыс.',

            'cta_label_ru' => 'Получить скидку',
            'cta_label_kk' => 'Жеңілдік алу',

            'cta_url' => 'https://qr-menu.kz/ru/register',
        ];

        $promo = PromoSetting::current();

        // Only touch empty fields — an admin edit must survive a re-seed.
        foreach ($defaults as $column => $value) {
            if (blank($promo->{$column})) {
                $promo->{$column} = $value;
            }
        }

        $promo->save();
    }
}
