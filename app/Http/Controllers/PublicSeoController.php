<?php

namespace App\Http\Controllers;

use App\Support\PublicSeo;
use Illuminate\Http\JsonResponse;

/**
 * Public SEO settings for the landing's metadata. No auth; served from cache
 * and dropped when the admin saves the settings page.
 */
class PublicSeoController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => PublicSeo::all()]);
    }
}
