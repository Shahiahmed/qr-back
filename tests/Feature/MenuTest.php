<?php

use App\Models\Dish;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->venue = Establishment::factory()->for($this->owner)->create();
    $this->actingAs($this->owner);
});

it('returns the whole menu as categories carrying their dishes', function () {
    $hot = MenuCategory::factory()->for($this->venue)->create(['name_ru' => 'Горячее']);
    Dish::factory()->for($this->venue)->for($hot, 'category')->create(['name_ru' => 'Плов']);

    $response = $this->getJson("/api/establishments/{$this->venue->id}/menu")->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name_ru'))->toBe('Горячее')
        ->and($response->json('data.0.dishes.0.name_ru'))->toBe('Плов');
});

it('loads dishes without an N+1', function () {
    $categories = MenuCategory::factory()->count(4)->for($this->venue)->create();
    foreach ($categories as $category) {
        Dish::factory()->count(3)->for($this->venue)->for($category, 'category')->create();
    }

    DB::enableQueryLog();
    $this->getJson("/api/establishments/{$this->venue->id}/menu")->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Session and user lookups aside, categories and dishes are two queries —
    // not one per category.
    expect($queries)->toBeLessThan(8);
});

it('orders categories by position', function () {
    MenuCategory::factory()->for($this->venue)->create(['name_ru' => 'Второе', 'position' => 2]);
    MenuCategory::factory()->for($this->venue)->create(['name_ru' => 'Первое', 'position' => 1]);

    $names = collect($this->getJson("/api/establishments/{$this->venue->id}/menu")->json('data'))
        ->pluck('name_ru')
        ->all();

    expect($names)->toBe(['Первое', 'Второе']);
});

it('creates a category and puts it last', function () {
    MenuCategory::factory()->for($this->venue)->create(['position' => 5]);

    $this->postJson("/api/establishments/{$this->venue->id}/categories", [
        'name_ru' => 'Напитки',
        'name_kk' => 'Сусындар',
    ])->assertCreated()->assertJsonPath('data.position', 6);
});

it('lets a venue launch without the Kazakh name', function () {
    $this->postJson("/api/establishments/{$this->venue->id}/categories", [
        'name_ru' => 'Салаты',
    ])->assertCreated()->assertJsonPath('data.name_kk', null);
});

it('stores the price in minor units exactly as sent', function () {
    $category = MenuCategory::factory()->for($this->venue)->create();

    // 2 490 ₸ = 249 000 тиын.
    $this->postJson("/api/establishments/{$this->venue->id}/dishes", [
        'menu_category_id' => $category->id,
        'name_ru' => 'Плов ташкентский',
        'price' => 249000,
    ])->assertCreated()->assertJsonPath('data.price', 249000);

    expect(Dish::sole()->price)->toBe(249000);
});

it('refuses a fractional or negative price', function () {
    $category = MenuCategory::factory()->for($this->venue)->create();

    foreach ([2490.5, -100, 'дорого'] as $bad) {
        $this->postJson("/api/establishments/{$this->venue->id}/dishes", [
            'menu_category_id' => $category->id,
            'name_ru' => 'Плов',
            'price' => $bad,
        ])->assertStatus(422)->assertJsonValidationErrors('price');
    }
});

it('toggles the stop list in one call', function () {
    $category = MenuCategory::factory()->for($this->venue)->create();
    $dish = Dish::factory()->for($this->venue)->for($category, 'category')->create();

    $this->patchJson("/api/establishments/{$this->venue->id}/dishes/{$dish->id}", [
        'is_available' => false,
    ])->assertOk()->assertJsonPath('data.is_available', false);

    expect($dish->fresh()->is_available)->toBeFalse();
});

it('refuses a category belonging to another venue', function () {
    $foreignCategory = MenuCategory::factory()->create();

    // The id is real, just not this tenant's — the rule has to check both.
    $this->postJson("/api/establishments/{$this->venue->id}/dishes", [
        'menu_category_id' => $foreignCategory->id,
        'name_ru' => 'Подсадное',
        'price' => 1000,
    ])->assertStatus(422)->assertJsonValidationErrors('menu_category_id');
});

it('hides another owner\'s menu behind a 404', function () {
    $foreign = Establishment::factory()->create();
    $foreignCategory = MenuCategory::factory()->for($foreign)->create();
    $foreignDish = Dish::factory()->for($foreign)->for($foreignCategory, 'category')->create();

    $this->getJson("/api/establishments/{$foreign->id}/menu")->assertNotFound();

    $this->patchJson("/api/establishments/{$foreign->id}/categories/{$foreignCategory->id}", [
        'name_ru' => 'Захвачено',
    ])->assertNotFound();

    $this->deleteJson("/api/establishments/{$foreign->id}/dishes/{$foreignDish->id}")
        ->assertNotFound();

    expect($foreignCategory->fresh()->name_ru)->not->toBe('Захвачено')
        ->and($foreignDish->fresh())->not->toBeNull();
});

it('refuses to touch a dish through a venue that does not hold it', function () {
    $foreign = Establishment::factory()->create();
    $foreignCategory = MenuCategory::factory()->for($foreign)->create();
    $foreignDish = Dish::factory()->for($foreign)->for($foreignCategory, 'category')->create();

    // Own venue in the URL, someone else's dish id — the pairing must be checked.
    $this->patchJson("/api/establishments/{$this->venue->id}/dishes/{$foreignDish->id}", [
        'name_ru' => 'Угон',
    ])->assertNotFound();

    expect($foreignDish->fresh()->name_ru)->not->toBe('Угон');
});

it('removes the dishes when a category goes', function () {
    $category = MenuCategory::factory()->for($this->venue)->create();
    Dish::factory()->count(2)->for($this->venue)->for($category, 'category')->create();

    $this->deleteJson("/api/establishments/{$this->venue->id}/categories/{$category->id}")
        ->assertNoContent();

    expect(Dish::count())->toBe(0);
});

it('reorders sections to match the ids sent', function () {
    $first = MenuCategory::factory()->for($this->venue)->create(['name_ru' => 'Первое', 'position' => 1]);
    $second = MenuCategory::factory()->for($this->venue)->create(['name_ru' => 'Второе', 'position' => 2]);
    $third = MenuCategory::factory()->for($this->venue)->create(['name_ru' => 'Третье', 'position' => 3]);

    $names = collect(
        $this->patchJson("/api/establishments/{$this->venue->id}/categories/reorder", [
            'ids' => [$third->id, $first->id, $second->id],
        ])->assertOk()->json('data')
    )->pluck('name_ru')->all();

    expect($names)->toBe(['Третье', 'Первое', 'Второе']);
    expect($third->fresh()->position)->toBe(1);
});

it('ignores a reorder id from another tenant', function () {
    $mine = MenuCategory::factory()->for($this->venue)->create(['position' => 1]);
    $foreignCategory = MenuCategory::factory()->create(); // another owner's venue

    $this->patchJson("/api/establishments/{$this->venue->id}/categories/reorder", [
        'ids' => [$foreignCategory->id, $mine->id],
    ])->assertOk();

    // The stray id must not have been repositioned into my venue's order.
    expect($foreignCategory->fresh()->position)->toBe($foreignCategory->position);
});

it('will not reorder another owner\'s sections', function () {
    $foreign = Establishment::factory()->create();
    $foreignCategory = MenuCategory::factory()->for($foreign)->create();

    $this->patchJson("/api/establishments/{$foreign->id}/categories/reorder", [
        'ids' => [$foreignCategory->id],
    ])->assertNotFound();
});

it('saves the guest-facing header and colour theme', function () {
    $this->patchJson("/api/establishments/{$this->venue->id}", [
        'wifi_ssid' => 'Guest_Net',
        'wifi_password' => 'open2024',
        'instagram_url' => 'instagram.com/venue',
        'theme' => 'forest',
    ])->assertOk()
        ->assertJsonPath('data.wifi_ssid', 'Guest_Net')
        ->assertJsonPath('data.theme', 'forest')
        // A bare handle is stored as a full URL so the guest link works.
        ->assertJsonPath('data.instagram_url', 'https://instagram.com/venue');
});

it('rejects an unknown colour theme', function () {
    $this->patchJson("/api/establishments/{$this->venue->id}", [
        'theme' => 'neon-chaos',
    ])->assertStatus(422)->assertJsonValidationErrors('theme');
});

it('keeps the menu closed to guests', function () {
    auth()->logout();

    $this->getJson("/api/establishments/{$this->venue->id}/menu")->assertUnauthorized();
});
