<?php

namespace App\Models;

use App\Support\PublicMenu;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The tenant. Menu data will be scoped by `establishment_id`.
 */
#[Fillable([
    'name', 'slug', 'currency', 'default_locale', 'address', 'phone',
    'wifi_ssid', 'wifi_password', 'instagram_url', 'facebook_url', 'tiktok_url',
    'theme', 'layout', 'show_logo',
    // Set only by VenueImage (upload/remove), never from the update form.
    'cover_path', 'logo_path',
])]
class Establishment extends Model
{
    /** @use HasFactory<\Database\Factories\EstablishmentFactory> */
    use HasFactory;

    /** Days a brand-new venue is publicly available before it needs a plan. */
    public const TRIAL_DAYS = 7;

    /** Where every image of this venue lives. */
    private const DISK = 'public';

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
     * Menu layout presets — how the guest menu arranges dishes (separate from
     * the colour `theme`). `classic` is the photo-left card list; `grid` is a
     * photo-forward two-column grid; `compact` is a dense text-first list. Keys
     * must match `MENU_LAYOUTS` on the front end.
     */
    public const LAYOUTS = ['classic', 'grid', 'compact'];

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
            'show_logo' => 'boolean',
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

        // Give the venue its own folder the moment it exists, so the storage
        // tree reads clearly (one folder per venue) even before the first photo.
        static::created(function (self $establishment) {
            Storage::disk(self::DISK)->makeDirectory($establishment->storageFolder());
        });

        // A rename changes the folder name (it carries the slug). Move the whole
        // tree and rewrite every stored path in one go, so a venue's files never
        // end up split across an old and a new folder.
        static::updating(function (self $establishment) {
            $original = $establishment->getOriginal('slug');

            if ($original !== null && $original !== $establishment->slug) {
                $establishment->relocateStorage($original);
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

    /**
     * This venue's own folder on the public disk. Everything the venue owns
     * lives under here — `cover/`, `logo/`, `dishes/{id}/` — so the storage tree
     * reads at a glance. Named `{id}-{slug}`: the id keeps it collision-proof and
     * stable, the slug makes it human-readable when browsing files.
     */
    public function storageFolder(): string
    {
        return "venues/{$this->id}-{$this->slug}";
    }

    /**
     * Move this venue's whole folder from its old slug to the new one and
     * rewrite every stored path to match. Called from `updating` (before the row
     * is written), so the model's own columns are mutated in place and ride the
     * same save; dish paths are updated directly. Keeps all of a venue's files
     * under a single, correctly-named folder after a rename.
     */
    private function relocateStorage(string $oldSlug): void
    {
        $disk = Storage::disk(self::DISK);
        $from = "venues/{$this->id}-{$oldSlug}";
        $to = $this->storageFolder();

        if ($from === $to) {
            return;
        }

        // Local disk: rename the whole subtree in one filesystem move.
        if ($disk->exists($from)) {
            @rename($disk->path($from), $disk->path($to));
        }

        $rebase = fn (?string $path) => $path
            ? Str::replaceStart("{$from}/", "{$to}/", $path)
            : $path;

        $this->cover_path = $rebase($this->cover_path);
        $this->logo_path = $rebase($this->logo_path);

        // Dishes carry their own paths. Update them straight in the DB without
        // firing model events — the menu cache is already cleared by this save.
        $this->dishes()->whereNotNull('image_path')->get(['id', 'image_path'])
            ->each(fn (Dish $dish) => Dish::query()
                ->whereKey($dish->id)
                ->update(['image_path' => $rebase($dish->image_path)]));
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionRequests(): HasMany
    {
        return $this->hasMany(SubscriptionRequest::class);
    }

    /**
     * This menu's live grant as an eager-loadable relation — usable in `with()`
     * to avoid N+1 when a list of venues each needs its own access window.
     * Filter `isActive()` on read to exclude a grant whose end date has passed.
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->latestOfMany();
    }

    /**
     * This menu's live subscription, if any. Reads the eager-loaded relation
     * when present (no N+1 across a list), lazy-loads otherwise.
     */
    private function liveSubscription(): ?Subscription
    {
        $sub = $this->currentSubscription;

        return $sub && $sub->isActive() ? $sub : null;
    }

    /**
     * When this menu stops being publicly available. Its own paid grant wins
     * over its trial. `null` means no limit — either an open-ended grant or a
     * venue created before trials existed (grandfathered).
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
