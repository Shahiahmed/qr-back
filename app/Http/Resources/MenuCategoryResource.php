<?php

namespace App\Http\Resources;

use App\Models\MenuCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuCategory
 */
class MenuCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ru' => $this->name_ru,
            'name_kk' => $this->name_kk,
            'position' => $this->position,
            'is_visible' => $this->is_visible,
            // Only when eager loaded, so listing categories stays one query.
            'dishes' => DishResource::collection($this->whenLoaded('dishes')),
        ];
    }
}
