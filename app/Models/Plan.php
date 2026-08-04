<?php

namespace App\Models;

use App\Support\PublicPlans;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription plan (тариф). Managed in the admin panel, rendered on the
 * public landing, and requested by owners. Price is in tiyn (1/100 ₸).
 */
#[Fillable([
    'name_ru', 'name_kk', 'tagline_ru', 'tagline_kk',
    'price', 'discount_percent', 'period', 'features',
    'max_establishments', 'max_categories', 'max_dishes_per_category',
    'is_active', 'is_featured', 'sort',
])]
class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /** Billing periods offered. Tiers differ only by duration on one menu. */
    public const PERIODS = ['month', 'halfyear', 'year'];

    /** Request-level memo for freeTier() so a venue list resolves it once. */
    private static ?self $freeTier = null;

    private static bool $freeTierResolved = false;

    /**
     * The free tier whose content caps govern a menu with no paid grant — a
     * trial menu or one on the free plan. It is the cheapest active plan (price
     * 0). Memoised for the request so building a list of venues (each asking for
     * its limits) does not re-query per row. `null` when no free plan exists
     * (e.g. an unseeded test DB) → no limits, nothing to enforce.
     */
    public static function freeTier(): ?self
    {
        if (! self::$freeTierResolved) {
            self::$freeTier = static::query()
                ->where('is_active', true)
                ->where('price', 0)
                ->orderBy('sort')
                ->orderBy('id')
                ->first();
            self::$freeTierResolved = true;
        }

        return self::$freeTier;
    }

    /**
     * Drop the free-tier memo. Model events call it after a plan changes; the
     * test suite calls it between examples so one test's resolved tier (a model
     * that RefreshDatabase then rolls back) cannot leak into the next.
     */
    public static function flushFreeTierMemo(): void
    {
        self::$freeTier = null;
        self::$freeTierResolved = false;
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'discount_percent' => 'integer',
            'features' => 'array',
            'max_establishments' => 'integer',
            'max_categories' => 'integer',
            'max_dishes_per_category' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The public catalogue is cached; any edit must drop it (same reasoning
        // as the guest menu — invalidate on the model, not in a controller).
        static::saved(function () {
            PublicPlans::forget();
            self::flushFreeTierMemo(); // limits may have changed
        });
        static::deleted(function () {
            PublicPlans::forget();
            self::flushFreeTierMemo();
        });
    }

    /**
     * Price after the discount, in tiyn. Rounded down so we never charge a
     * fraction of a tiyn.
     */
    public function discountedPrice(): int
    {
        if ($this->discount_percent <= 0) {
            return (int) $this->price;
        }

        return (int) floor($this->price * (100 - $this->discount_percent) / 100);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(SubscriptionRequest::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
