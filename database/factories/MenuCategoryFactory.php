<?php

namespace Database\Factories;

use App\Models\Establishment;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'name_ru' => 'Горячее',
            'name_kk' => 'Ыстық',
            'position' => 0,
            'is_visible' => true,
        ];
    }
}
