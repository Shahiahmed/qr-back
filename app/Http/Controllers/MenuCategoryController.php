<?php

namespace App\Http\Controllers;

use App\Http\Requests\Menu\ReorderCategoriesRequest;
use App\Http\Requests\Menu\StoreCategoryRequest;
use App\Http\Requests\Menu\UpdateCategoryRequest;
use App\Http\Resources\MenuCategoryResource;
use App\Models\Establishment;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        // Free / trial menus are capped. The editor dims "Add" at the limit, but
        // this is the real gate — a direct POST must not slip past it.
        $limit = $establishment->menuLimits()['categories'];

        if ($limit !== null && $establishment->categories()->count() >= $limit) {
            throw ValidationException::withMessages([
                'name_ru' => [__('menu.category_limit', ['limit' => $limit])],
            ]);
        }

        $category = $establishment->categories()->create([
            ...$request->validated(),
            // New sections go to the end unless a position is given.
            'position' => $request->integer('position')
                ?: (int) $establishment->categories()->max('position') + 1,
        ]);

        return MenuCategoryResource::make($category)->response()->setStatusCode(201);
    }

    /**
     * Set the section order from a list of ids. Ids that don't belong to this
     * venue are skipped, so a tampered payload can't touch another tenant.
     *
     * Positions are saved through the model (not a bulk query) so the
     * public-menu cache is dropped by the model event — guests see the new
     * order on the next scan.
     */
    public function reorder(
        ReorderCategoriesRequest $request,
        Establishment $establishment,
    ): AnonymousResourceCollection {
        $this->authorizeOwner($establishment);

        $categories = $establishment->categories()->get()->keyBy('id');

        DB::transaction(function () use ($request, $categories) {
            $position = 1;

            foreach ($request->validated()['ids'] as $id) {
                $category = $categories->get($id);

                if ($category) {
                    $category->update(['position' => $position]);
                    $position++;
                }
            }
        });

        // categories() is ordered by position, so this reflects the new order.
        return MenuCategoryResource::collection(
            $establishment->categories()->with('dishes')->get(),
        );
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
