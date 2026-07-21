<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\Establishment;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    protected $model = Dish::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'menu_category_id' => MenuCategory::factory(),
            'name_ru' => 'Плов ташкентский',
            'name_kk' => 'Ташкент палауы',
            'description_ru' => 'Рассыпчатый рис, мраморная говядина.',
            // 2 490 ₸ in тиыны.
            'price' => 249000,
            'position' => 0,
            'is_visible' => true,
            'is_available' => true,
        ];
    }
}
