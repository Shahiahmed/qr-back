<?php

namespace App\Http\Controllers;

use App\Http\Requests\Menu\StoreDishRequest;
use App\Http\Requests\Menu\UpdateDishRequest;
use App\Http\Requests\StoreDishImageRequest;
use App\Http\Resources\DishResource;
use App\Models\Dish;
use App\Models\Establishment;
use App\Support\DishImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DishController extends Controller
{
    use ScopesToOwner;

    public function store(
        StoreDishRequest $request,
        Establishment $establishment,
    ): JsonResponse {
        $this->authorizeOwner($establishment);

        $categoryId = (int) $request->validated('menu_category_id');

        // Free / trial menus cap dishes per section. The editor dims "Add" at the
        // limit; this is the real gate, and it counts the *target* section (the
        // dialog lets the owner move a dish between sections before saving).
        $limit = $establishment->menuLimits()['dishes_per_category'];

        if ($limit !== null) {
            $count = $establishment->dishes()
                ->where('menu_category_id', $categoryId)
                ->count();

            if ($count >= $limit) {
                throw ValidationException::withMessages([
                    'menu_category_id' => [__('menu.dish_limit', ['limit' => $limit])],
                ]);
            }
        }

        $dish = $establishment->dishes()->create([
            ...$request->validated(),
            'position' => $request->integer('position')
                ?: (int) $establishment->dishes()
                    ->where('menu_category_id', $categoryId)
                    ->max('position') + 1,
        ]);

        return DishResource::make($dish)->response()->setStatusCode(201);
    }

    public function update(
        UpdateDishRequest $request,
        Establishment $establishment,
        Dish $dish,
    ): DishResource {
        $this->authorizeOwner($establishment);
        $this->authorizeBelongsTo($dish, $establishment);

        $dish->update($request->validated());

        return DishResource::make($dish);
    }

    public function destroy(Establishment $establishment, Dish $dish): JsonResponse
    {
        $this->authorizeOwner($establishment);
        $this->authorizeBelongsTo($dish, $establishment);

        $dish->delete();

        return response()->json(status: 204);
    }

    /** Upload (or replace) the dish photo. Multipart; cropped + WebP server-side. */
    public function storeImage(
        StoreDishImageRequest $request,
        Establishment $establishment,
        Dish $dish,
    ): DishResource {
        $this->authorizeOwner($establishment);
        $this->authorizeBelongsTo($dish, $establishment);

        DishImage::store($dish, $request->file('file'));

        return DishResource::make($dish->fresh());
    }

    public function destroyImage(Establishment $establishment, Dish $dish): DishResource
    {
        $this->authorizeOwner($establishment);
        $this->authorizeBelongsTo($dish, $establishment);

        DishImage::remove($dish);

        return DishResource::make($dish->fresh());
    }
}
