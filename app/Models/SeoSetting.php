<?php

namespace App\Models;

use App\Support\PublicSeo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Site-wide SEO settings — a singleton (one row, id = 1). Managed only in the
 * admin panel; there is no owner-facing HTTP write path, so every column is
 * fillable. The public landing reads it via the cached PublicSeo endpoint.
 */
#[Fillable([
    'title_ru', 'title_kk',
    'description_ru', 'description_kk',
    'keywords_ru', 'keywords_kk',
    'og_image_path', 'canonical_host',
])]
class SeoSetting extends Model
{
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
        // reasoning as PublicPlans / PublicMenu — invalidate on the model).
        static::saved(fn () => PublicSeo::forget());
    }
}
