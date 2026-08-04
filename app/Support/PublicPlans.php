<?php

namespace App\Support;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;

/**
 * The public plan catalogue shown on the landing. Read-heavy and rarely edited,
 * so it is rendered once and cached whole; any plan edit drops the entry (see
 * Plan::booted). Same pattern as PublicMenu.
 */
class PublicPlans
{
    /** Long, because an edit clears the entry rather than waiting it out. */
    private const TTL = 86400;

    private const KEY = 'public-plans';

    /**
     * @return array<int, array<string, mixed>>
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
     * @return array<int, array<string, mixed>>
     */
    private static function build(): array
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (Plan $plan) => self::present($plan))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name_ru' => $plan->name_ru,
            'name_kk' => $plan->name_kk,
            'tagline_ru' => $plan->tagline_ru,
            'tagline_kk' => $plan->tagline_kk,
            // Both prices in tiyn: original and after discount, so the front end
            // can strike through the old one.
            'price' => (int) $plan->price,
            'price_final' => $plan->discountedPrice(),
            'discount_percent' => $plan->discount_percent,
            'period' => $plan->period,
            // Normalise to a plain list of { ru, kk } lines.
            'features' => collect($plan->features ?? [])
                ->map(fn ($f) => [
                    'ru' => $f['ru'] ?? '',
                    'kk' => $f['kk'] ?? '',
                ])
                ->values()
                ->all(),
            'max_establishments' => $plan->max_establishments,
            // Content caps (null = unlimited). The free tier carries them; paid
            // tiers leave them open.
            'max_categories' => $plan->max_categories,
            'max_dishes_per_category' => $plan->max_dishes_per_category,
            'is_featured' => $plan->is_featured,
        ];
    }
}
