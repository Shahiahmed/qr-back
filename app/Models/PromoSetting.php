<?php

namespace App\Models;

use App\Support\PublicPromo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Landing promo pop-up settings — a singleton (one row, id = 1). Managed only in
 * the admin panel; there is no owner-facing HTTP write path, so every column is
 * fillable. The public landing reads it via the cached PublicPromo endpoint.
 */
#[Fillable([
    'enabled',
    'badge_ru', 'badge_kk',
    'title_ru', 'title_kk',
    'body_ru', 'body_kk',
    'cta_label_ru', 'cta_label_kk', 'cta_url',
    'starts_at', 'ends_at',
])]
class PromoSetting extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * The one and only settings row, created on first access. Callers get a
     * persisted model they can edit and save.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    protected static function booted(): void
    {
        // The public payload is cached whole; any edit must drop it (same
        // reasoning as PublicSeo / PublicPlans / PublicMenu).
        static::saved(fn () => PublicPromo::forget());
    }
}
