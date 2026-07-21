<?php

namespace App\Http\Resources;

use App\Models\Dish;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dish
 */
class DishResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_category_id' => $this->menu_category_id,
            'name_ru' => $this->name_ru,
            'name_kk' => $this->name_kk,
            'description_ru' => $this->description_ru,
            'description_kk' => $this->description_kk,
            // Minor units, as stored. The client formats it.
            'price' => $this->price,
            'position' => $this->position,
            'is_visible' => $this->is_visible,
            'is_available' => $this->is_available,
        ];
    }
}
