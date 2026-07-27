<?php

namespace App\Models;

use App\Support\PublicMenu;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The tenant. Menu data will be scoped by `establishment_id`.
 */
#[Fillable(['name', 'slug', 'currency', 'default_locale', 'address', 'phone'])]
class Establishment extends Model
{
    /** @use HasFactory<\Database\Factories\EstablishmentFactory> */
    use HasFactory;

    /** Currencies offered in the panel. */
    public const CURRENCIES = ['KZT', 'USD', 'RUB'];

    /** Menu languages. `kk` is Kazakh — `kz` is a country, not a language. */
    public const LOCALES = ['ru', 'kk'];

    /**
     * Slugs the public router needs for itself. Without this a venue could
     * take `/api` or a locale prefix and shadow the app.
     */
    public const RESERVED_SLUGS = [
        'api', 'admin', 'app', 'ru', 'kz', 'kk', 'en',
        'login', 'register', 'logout', 'dashboard', 'panel',
        'sanctum', 'storage', 'assets', 'img', 'menu', 'm', 'p',
        'about', 'contact', 'privacy', 'terms', 'sitemap', 'robots',
        // Built-in sample menu served statically at /m/demo.
        'demo',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $establishment) {
            /*
             * A renamed venue leaves its old address cached, which would keep
             * answering after the slug moved — clear both.
             */
            $original = $establishment->getOriginal('slug');

            if ($original && $original !== $establishment->slug) {
                PublicMenu::forget($original);
            }

            PublicMenu::forget($establishment->slug);
        });

        static::deleted(fn (self $establishment) => PublicMenu::forget($establishment->slug));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(MenuCategory::class)->orderBy('position')->orderBy('id');
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }
}
