<?php

use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Plan;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\SubscriptionService;

/**
 * Free / trial menus are capped at 3 sections and 5 dishes per section. The cap
 * lives on the free plan (price 0); a paid grant lifts it. Enforcement is
 * server-side — the editor only dims its buttons.
 */

/** A free tier carrying the 3/5 caps, plus a paid year plan with none. */
function seedLimitPlans(): void
{
    Plan::factory()->create([
        'name_ru' => 'Бесплатный',
        'price' => 0,
        'period' => 'month',
        'sort' => 1,
        'max_categories' => 3,
        'max_dishes_per_category' => 5,
    ]);
}

it('resolves the free tier caps for a trial menu', function () {
    seedLimitPlans();
    $venue = Establishment::factory()->create();

    expect($venue->menuLimits())
        ->toBe(['categories' => 3, 'dishes_per_category' => 5]);
});

it('enforces nothing when no free plan is seeded', function () {
    // Unseeded install (like most other test files) → limits are null/null.
    $venue = Establishment::factory()->create();

    expect($venue->menuLimits())
        ->toBe(['categories' => null, 'dishes_per_category' => null]);
});

it('blocks a fourth section on a free menu', function () {
    seedLimitPlans();
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();
    MenuCategory::factory()->count(3)->for($venue)->create();

    $this->actingAs($owner)
        ->postJson("/api/establishments/{$venue->id}/categories", ['name_ru' => 'Ещё'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name_ru');

    expect($venue->categories()->count())->toBe(3);
});

it('allows the third section on a free menu', function () {
    seedLimitPlans();
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();
    MenuCategory::factory()->count(2)->for($venue)->create();

    $this->actingAs($owner)
        ->postJson("/api/establishments/{$venue->id}/categories", ['name_ru' => 'Третий'])
        ->assertCreated();
});

it('blocks a sixth dish in a section on a free menu', function () {
    seedLimitPlans();
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();
    $category = MenuCategory::factory()->for($venue)->create();
    \App\Models\Dish::factory()->count(5)->for($venue)->for($category, 'category')->create();

    $this->actingAs($owner)
        ->postJson("/api/establishments/{$venue->id}/dishes", [
            'menu_category_id' => $category->id,
            'name_ru' => 'Шестое',
            'price' => 100000,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('menu_category_id');

    expect($venue->dishes()->count())->toBe(5);
});

it('lifts the caps for a menu with an active paid subscription', function () {
    seedLimitPlans();
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();

    // A year plan with no content caps.
    $paid = Plan::factory()->create(['period' => 'year', 'price' => 25_000 * 100]);
    $request = SubscriptionRequest::factory()->for($owner)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $paid->id,
    ]);
    SubscriptionService::approve($request);

    $venue->refresh()->load('currentSubscription.plan');

    expect($venue->menuLimits())
        ->toBe(['categories' => null, 'dishes_per_category' => null]);

    // And a fourth section now goes through.
    MenuCategory::factory()->count(3)->for($venue)->create();
    $this->actingAs($owner)
        ->postJson("/api/establishments/{$venue->id}/categories", ['name_ru' => 'Четвёртый'])
        ->assertCreated();
});

it('exposes the menu limits on the establishment resource', function () {
    seedLimitPlans();
    $owner = User::factory()->create();
    Establishment::factory()->for($owner)->create();

    $data = $this->actingAs($owner)
        ->getJson('/api/establishments')
        ->assertOk()
        ->json('data.0');

    expect($data['menu_limits'])
        ->toBe(['categories' => 3, 'dishes_per_category' => 5]);
});
