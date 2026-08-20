<?php

namespace App\Http\Controllers;

use App\Support\PublicPromo;
use Illuminate\Http\JsonResponse;

/**
 * Public promo pop-up for the landing. No auth; served from cache and dropped
 * when the admin saves the promo settings page in /admin.
 */
class PublicPromoController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => PublicPromo::all()]);
    }
}
