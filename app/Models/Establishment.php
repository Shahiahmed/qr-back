<?php

namespace App\Models;

use App\Support\PublicMenu;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The tenant. Menu data will be scoped by `establishment_id`.
 */
#[Fillable([
    'name', 'slug', 'currency', 'default_locale', 'address', 'phone',
    'wifi_ssid', 'wifi_password', 'instagram_url', 'facebook_url', 'tiktok_url',
    'theme',
    // Set only by VenueImage (upload/remove), never from the update form.
    'cover_path', 'logo_path',
])]
class Establishment extends Model
{
    /** @use HasFactory<\Database\Factories\EstablishmentFactory> */
    use HasFactory;

    /** Days a brand-new venue is publicly available before it needs a plan. */
    public const TRIAL_DAYS = 7;

    /** Currencies offered in the panel. */
    public const CURRENCIES = ['KZT', 'USD', 'RUB'];

    /** Menu languages. `kk` is Kazakh — `kz` is a country, not a language. */
    public const LOCALES = ['ru', 'kk'];

    /**
     * Colour presets for the guest menu. Only the accent family changes per
     * theme — the background and text stay put — so no choice can make the menu
     * unreadable. Keys must match `MENU_THEMES` on the front end.
     */
    public const THEMES = [
        'classic', 'graphite', 'forest', 'ocean',
        'berry', 'sand', 'rose', 'midnight',
    ];

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

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Stamp the free trial window once, at creation. An active subscription
        // later overrides it, but every menu starts with its own week.
        static::creating(function (self $establishment) {
            if ($establishment->trial_ends_at === null) {
                $establishment->trial_ends_at = Carbon::now()->addDays(self::TRIAL_DAYS);
            }
        });

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

    /**
     * The owner's live subscription, if any. Prefers the eager-loaded relation
     * (no N+1 across a list) and falls back to a query for a lone model.
     */
    private function liveSubscription(): ?Subscription
    {
        $sub = $this->relationLoaded('user') && $this->user
            ? $this->user->currentSubscription
            : $this->user?->currentSubscription;

        return $sub && $sub->isActive() ? $sub : null;
    }

    /**
     * When the public menu stops being available. A subscription (account-wide)
     * wins over the per-menu trial. `null` means no limit — either an
     * open-ended grant or a venue created before trials existed (grandfathered).
     */
    public function accessEndsAt(): ?Carbon
    {
        $sub = $this->liveSubscription();

        if ($sub) {
            return $sub->ends_at; // null = open-ended grant
        }

        return $this->trial_ends_at;
    }

    /** 'subscription' | 'trial' | null (unlimited / grandfathered). */
    public function accessSource(): ?string
    {
        if ($this->liveSubscription()) {
            return 'subscription';
        }

        return $this->trial_ends_at !== null ? 'trial' : null;
    }

    public function isExpired(): bool
    {
        $end = $this->accessEndsAt();

        return $end !== null && $end->isPast();
    }

    /** Whole days until access ends; 0 if already expired, null if unlimited. */
    public function daysLeft(): ?int
    {
        $end = $this->accessEndsAt();

        if ($end === null) {
            return null;
        }

        if ($end->isPast()) {
            return 0;
        }

        return (int) ceil(Carbon::now()->diffInHours($end) / 24);
    }
}
