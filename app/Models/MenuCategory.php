<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name_ru', 'name_kk', 'position', 'is_visible'])]
class MenuCategory extends Model
{
    /** @use HasFactory<\Database\Factories\MenuCategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class)->orderBy('position')->orderBy('id');
    }
}
