<?php

namespace App\Http\Controllers;

use App\Support\PublicPlans;
use Illuminate\Http\JsonResponse;

/**
 * Public plan catalogue for the landing page. No auth; served from cache and
 * dropped when a plan changes.
 */
class PublicPlansController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => PublicPlans::all()]);
    }
}
