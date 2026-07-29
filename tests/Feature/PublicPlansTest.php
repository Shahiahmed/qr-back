<?php

use App\Models\Plan;
use App\Support\PublicPlans;

it('lists only active plans, ordered', function () {
    Plan::factory()->create(['name_ru' => 'Второй', 'sort' => 2, 'is_active' => true]);
    Plan::factory()->create(['name_ru' => 'Первый', 'sort' => 1, 'is_active' => true]);
    Plan::factory()->inactive()->create(['name_ru' => 'Скрытый', 'sort' => 0]);

    $response = $this->getJson('/api/plans')->assertOk();

    $names = collect($response->json('data'))->pluck('name_ru');

    expect($names)->toEqual(collect(['Первый', 'Второй']));
});

it('returns the discounted price alongside the original', function () {
    Plan::factory()->create([
        'price' => 1_000_000, // 10 000 ₸
        'discount_percent' => 20,
    ]);

    $plan = $this->getJson('/api/plans')->json('data.0');

    expect($plan['price'])->toBe(1_000_000)
        ->and($plan['price_final'])->toBe(800_000)
        ->and($plan['discount_percent'])->toBe(20);
});

it('caches the catalogue and drops it when a plan changes', function () {
    $plan = Plan::factory()->create(['name_ru' => 'Старт']);

    // Warm the cache.
    expect(PublicPlans::all())->toHaveCount(1);

    // A rename must be reflected — the model event clears the cache.
    $plan->update(['name_ru' => 'Бизнес']);

    expect(PublicPlans::all()[0]['name_ru'])->toBe('Бизнес');
});
