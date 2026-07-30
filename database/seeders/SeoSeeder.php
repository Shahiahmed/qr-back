<?php

namespace Database\Seeders;

use App\Models\SeoSetting;
use Illuminate\Database\Seeder;

/**
 * Initial landing SEO for the singleton `seo_settings` row. Fills only the
 * BLANK columns, so re-running never clobbers what an admin later edited in
 * `/admin → SEO` (unlike PlanSeeder, which overwrites by key). `og_image_path`
 * is left untouched on purpose — the OG image is uploaded through the panel,
 * the seeder has no file to point at. `canonical_host` is left to its default
 * (canonical falls back to the deploy env on the front).
 *
 * Run MANUALLY on the server (`php artisan db:seed --class=SeoSeeder`), never
 * from deploy.sh — same reasoning as PlanSeeder.
 */
class SeoSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'title_ru' => 'Qmenu — QR-меню для ресторанов и кафе в Казахстане',
            'title_kk' => 'Qmenu — мейрамханалар мен кафелерге арналған QR-мәзір',

            'description_ru' => 'Гости сканируют QR-код на столе и открывают меню '
                .'без установки приложения — на русском и казахском. Запуск за пару '
                .'минут, стоп-лист и цены меняются в один тап.',
            'description_kk' => 'Қонақтар үстелдегі QR-кодты сканерлеп, мәзірді '
                .'қосымшасыз ашады — орысша және қазақша. Іске қосу бірнеше минутта, '
                .'стоп-парақ пен бағалар бір түртумен өзгереді.',

            'keywords_ru' => 'qr меню, электронное меню, меню по qr коду, '
                .'qr меню для ресторана, цифровое меню, меню для кафе, qr menu, Казахстан',
            'keywords_kk' => 'qr мәзір, электронды мәзір, qr код арқылы мәзір, '
                .'мейрамханаға qr мәзір, цифрлық мәзір, кафеге мәзір, qr menu, Қазақстан',
        ];

        $seo = SeoSetting::current();

        // Only touch empty fields — an admin edit must survive a re-seed.
        foreach ($defaults as $column => $value) {
            if (blank($seo->{$column})) {
                $seo->{$column} = $value;
            }
        }

        $seo->save();
    }
}
