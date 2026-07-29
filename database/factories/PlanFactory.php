<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ru' => fake()->randomElement(['Старт', 'Бизнес', 'Премиум']),
            'name_kk' => null,
            'tagline_ru' => 'для одной точки',
            'tagline_kk' => null,
            // Whole tenge stored in tiyn.
            'price' => fake()->numberBetween(10, 200) * 100 * 100,
            'discount_percent' => 0,
            'period' => 'month',
            'features' => [
                ['ru' => 'QR-меню', 'kk' => 'QR-мәзір'],
                ['ru' => 'Две локали', 'kk' => 'Екі тіл'],
            ],
            'max_establishments' => 1,
            'is_active' => true,
            'is_featured' => false,
            'sort' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
