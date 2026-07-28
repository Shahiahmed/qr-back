<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MenuCategoryResource;
use App\Models\Establishment;
use App\Support\MenuStarter;
use Illuminate\Http\JsonResponse;

/**
 * Fills an empty venue with the starter bilingual menu.
 * Refuses if sections already exist — never clobber owner data.
 */
class ApplyMenuStarterController extends Controller
{
    use ScopesToOwner;

    public function __invoke(Establishment $establishment): JsonResponse
    {
        $this->authorizeOwner($establishment);

        if (! MenuStarter::apply($establishment)) {
            return response()->json([
                'message' => 'Menu already has sections.',
            ], 409);
        }

        $categories = $establishment->categories()->with('dishes')->get();

        return MenuCategoryResource::collection($categories)
            ->response()
            ->setStatusCode(201);
    }
}
