<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'menu_category_id', 'name_ru', 'name_kk', 'description_ru', 'description_kk',
    'price', 'position', 'is_visible', 'is_available',
])]
class Dish extends Model
{
    /** @use HasFactory<\Database\Factories\DishFactory> */
    use HasFactory;

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
