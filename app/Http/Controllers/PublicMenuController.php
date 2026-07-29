<?php

namespace App\Http\Controllers;

use App\Support\PublicMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class PublicMenuController extends Controller
{
    /** No auth: this is what the QR code on the table points at. */
    public function __invoke(string $slug): JsonResponse
    {
        $menu = PublicMenu::forSlug($slug);

        abort_if($menu === null, 404);

        /*
         * Expiry is checked live, not baked — the payload is cached for a day
         * but the trial/subscription may lapse inside that day. Comparing the
         * baked absolute timestamp to "now" each request lets the menu go dark
         * on time without waiting for the cache to expire. 404, like a missing
         * venue: the owner is warned by a cabinet countdown before this hits.
         */
        $endsAt = $menu['access_ends_at'] ?? null;
        abort_if($endsAt !== null && Carbon::parse($endsAt)->isPast(), 404);

        return response()->json(['data' => $menu]);
    }
}
