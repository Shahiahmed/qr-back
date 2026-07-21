<?php

namespace Database\Factories;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Establishment>
 */
class EstablishmentFactory extends Factory
{
    protected $model = Establishment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->words(3, true)),
            'currency' => 'KZT',
            'default_locale' => 'ru',
            'address' => fake()->address(),
            'phone' => '+7 700 000 00 00',
        ];
    }
}
