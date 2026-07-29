<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesPublicMenu;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// `image_path` is fillable because DishImage writes it via ->update() (so the
// saved event drops the menu cache) — but it is NOT in Store/UpdateDishRequest,
// so no owner can set it through the JSON endpoints; only the uploader does.
#[Fillable([
    'menu_category_id', 'name_ru', 'name_kk', 'description_ru', 'description_kk',
    'price', 'position', 'is_visible', 'is_available', 'image_path',
])]
class Dish extends Model
{
    /** @use HasFactory<\Database\Factories\DishFactory> */
    use HasFactory;
    use InvalidatesPublicMenu;

    protected function casts(): array
    {
        return [
            // Minor units. Cast to int so a string from the request never
            // reaches the column as one.
            'price' => 'integer',
            'is_visible' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }
}
