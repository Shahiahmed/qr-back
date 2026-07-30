<?php

namespace App\Support;

use App\Models\SeoSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The public SEO payload consumed by the landing's generateMetadata. Read on
 * every page render but edited rarely, so it is built once and cached whole;
 * any settings save drops the entry (see SeoSetting::booted). Same pattern as
 * PublicPlans / PublicMenu.
 */
class PublicSeo
{
    /** Long, because a save clears the entry rather than waiting it out. */
    private const TTL = 86400;

    private const KEY = 'public-seo';

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return Cache::remember(self::KEY, self::TTL, fn () => self::build());
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(): array
    {
        $seo = SeoSetting::current();

        return [
            // Robots + canonical are global, not per-locale.
            'noindex' => (bool) $seo->noindex,
            'canonical_host' => self::clean($seo->canonical_host),
            // Absolute URL so the front end can hand it straight to OG/Twitter.
            'og_image_url' => $seo->og_image_path
                ? Storage::disk('public')->url($seo->og_image_path)
                : null,
            // Per-locale meta. Empty strings are normalised to null so the
            // front end knows to fall back to its built-in copy.
            'ru' => [
                'title' => self::clean($seo->title_ru),
                'description' => self::clean($seo->description_ru),
                'keywords' => self::clean($seo->keywords_ru),
            ],
            'kk' => [
                'title' => self::clean($seo->title_kk),
                'description' => self::clean($seo->description_kk),
                'keywords' => self::clean($seo->keywords_kk),
            ],
        ];
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
