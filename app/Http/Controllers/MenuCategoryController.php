<?php

namespace App\Http\Controllers;

use App\Http\Requests\Menu\StoreCategoryRequest;
use App\Http\Requests\Menu\UpdateCategoryRequest;
use App\Http\Resources\MenuCategoryResource;
use App\Models\Establishment;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuCategoryController extends Controller
{
    use ScopesToOwner;

    /** The whole menu in one call: categories with their dishes. */
    public function index(Establishment $establishment): AnonymousResourceCollection
    {
        $this->authorizeOwner($establishment);

        // Eager loaded — a category per query would be an N+1 on every render.
        $categories = $establishment->categories()->with('dishes')->get();

        return MenuCategoryResource::collection($categories);
    }

    public function store(
        StoreCategoryRequest $request,
        Establishment $establishment,
    ): JsonResponse {
        $this->authorizeOwner($establishment);

        $category = $establishment->categories()->create([
            ...$request->validated(),
            // New sections go to the end unless a position is given.
            'position' => $request->integer('position')
                ?: (int) $establishment->categories()->max('position') + 1,
        ]);

        return MenuCategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function update(
        UpdateCategoryRequest $request,
        Establishment $establishment,
        MenuCategory $category,
    ): MenuCategoryResource {
        $this->authorizeOwner($establishment);
        $this->authorizeBelongsTo($category, $establishment);

        $category->update($request->validated());

        return MenuCategoryResource::make($category);
    }

    public function destroy(
        Establishment $establishment,
        MenuCategory $category,
    ): JsonResponse {
        $this->authorizeOwner($establishment);
        $this->authorizeBelongsTo($category, $establishment);

        // Dishes go with it — the migration cascades.
        $category->delete();

        return response()->json(status: 204);
    }
}
