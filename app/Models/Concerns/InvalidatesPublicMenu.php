<?php

namespace App\Models\Concerns;

use App\Support\PublicMenu;

/**
 * Drops the cached guest menu whenever menu data changes.
 *
 * Wired through model events rather than called from the controllers: a new
 * endpoint or a console command that edits a dish would otherwise leave the
 * public menu serving stale prices, and nothing would say so.
 */
trait InvalidatesPublicMenu
{
    protected static function bootInvalidatesPublicMenu(): void
    {
        $forget = function ($model) {
            // `saved` covers create and update alike.
            $slug = $model->establishment?->slug;

            if ($slug) {
                PublicMenu::forget($slug);
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }
}
