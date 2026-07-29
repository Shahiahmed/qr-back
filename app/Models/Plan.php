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
    'max_establishments', 'is_active', 'is_featured', 'sort',
])]
class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /** Billing periods offered. */
    public const PERIODS = ['month', 'year'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'discount_percent' => 'integer',
            'features' => 'array',
            'max_establishments' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The public catalogue is cached; any edit must drop it (same reasoning
        // as the guest menu — invalidate on the model, not in a controller).
        static::saved(fn () => PublicPlans::forget());
        static::deleted(fn () => PublicPlans::forget());
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
