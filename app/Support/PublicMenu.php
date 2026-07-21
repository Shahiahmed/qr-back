<?php

namespace App\Support;

use App\Models\Establishment;
use Illuminate\Support\Facades\Cache;

/**
 * The guest-facing menu.
 *
 * This is the read-heavy path: every scan in every venue hits it, and it
 * changes only when an owner edits something. So it is rendered once and
 * cached whole, and any edit drops the entry (see MenuCache).
 */
class PublicMenu
{
    /** Long, because an edit clears the entry rather than waiting it out. */
    private const TTL = 86400;

    public static function cacheKey(string $slug): string
    {
        return "public-menu:{$slug}";
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forSlug(string $slug): ?array
    {
        return Cache::remember(
            self::cacheKey($slug),
            self::TTL,
            fn () => self::build($slug),
        );
    }

    public static function forget(string $slug): void
    {
        Cache::forget(self::cacheKey($slug));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function build(string $slug): ?array
    {
        $establishment = Establishment::query()
            ->where('slug', $slug)
            /*
             * Hidden sections and dishes are filtered in the query, not by the
             * client — anything switched off must never leave the server. A
             * dish on the stop list is still sent, greyed out in the UI, so a
             * guest can see it exists and ask.
             */
            ->with([
                'categories' => fn ($query) => $query->where('is_visible', true),
                'categories.dishes' => fn ($query) => $query->where('is_visible', true),
            ])
            ->first();

        if (! $establishment) {
            return null;
        }

        return [
            'name' => $establishment->name,
            'slug' => $establishment->slug,
            'currency' => $establishment->currency,
            'default_locale' => $establishment->default_locale,
            'address' => $establishment->address,
            'phone' => $establishment->phone,
            'categories' => $establishment->categories
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name_ru' => $category->name_ru,
                    'name_kk' => $category->name_kk,
                    'dishes' => $category->dishes
                        ->map(fn ($dish) => [
                            'id' => $dish->id,
                            'name_ru' => $dish->name_ru,
                            'name_kk' => $dish->name_kk,
                            'description_ru' => $dish->description_ru,
                            'description_kk' => $dish->description_kk,
                            'price' => $dish->price,
                            'is_available' => $dish->is_available,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
