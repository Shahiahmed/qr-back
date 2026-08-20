<?php

namespace App\Support;

use App\Models\PromoSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The public promo payload consumed by the landing's pop-up. Read on every page
 * render but edited rarely, so the content is built once and cached whole; any
 * settings save drops the entry (see PromoSetting::booted). Same pattern as
 * PublicSeo / PublicPlans / PublicMenu.
 *
 * Activeness (the schedule window) is computed live against now() on each read
 * — not baked into the cache — so a promo turns on and off at the right time
 * even while the content is still cached.
 */
class PublicPromo
{
    /** Long, because a save clears the entry rather than waiting it out. */
    private const TTL = 86400;

    private const KEY = 'public-promo';

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $raw = Cache::remember(self::KEY, self::TTL, fn () => self::build());

        $active = self::isLive($raw);

        // Nothing leaks before the window opens: an inactive promo returns empty
        // fields, so a scheduled campaign's text never reaches the client early.
        return [
            'active' => $active,
            // Campaign id — changes on every save, so the front end can show a
            // fresh promo again after a visitor dismissed the previous one.
            'id' => $raw['id'],
            'cta_url' => $active ? $raw['cta_url'] : null,
            'ru' => $active ? $raw['ru'] : self::emptyFields(),
            'kk' => $active ? $raw['kk'] : self::emptyFields(),
        ];
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function isLive(array $raw): bool
    {
        if (! $raw['enabled']) {
            return false;
        }

        $now = now();

        if ($raw['starts_at'] !== null && Carbon::parse($raw['starts_at'])->isAfter($now)) {
            return false;
        }

        if ($raw['ends_at'] !== null && Carbon::parse($raw['ends_at'])->isBefore($now)) {
            return false;
        }

        // No headline → nothing worth interrupting the visitor for.
        return $raw['ru']['title'] !== null || $raw['kk']['title'] !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(): array
    {
        $promo = PromoSetting::current();

        return [
            'enabled' => (bool) $promo->enabled,
            'starts_at' => $promo->starts_at?->toIso8601String(),
            'ends_at' => $promo->ends_at?->toIso8601String(),
            'id' => (string) ($promo->updated_at?->getTimestamp() ?? 0),
            'cta_url' => self::clean($promo->cta_url),
            'ru' => [
                'badge' => self::clean($promo->badge_ru),
                'title' => self::clean($promo->title_ru),
                'body' => self::clean($promo->body_ru),
                'cta_label' => self::clean($promo->cta_label_ru),
            ],
            'kk' => [
                'badge' => self::clean($promo->badge_kk),
                'title' => self::clean($promo->title_kk),
                'body' => self::clean($promo->body_kk),
                'cta_label' => self::clean($promo->cta_label_kk),
            ],
        ];
    }

    /**
     * @return array<string, null>
     */
    private static function emptyFields(): array
    {
        return ['badge' => null, 'title' => null, 'body' => null, 'cta_label' => null];
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
