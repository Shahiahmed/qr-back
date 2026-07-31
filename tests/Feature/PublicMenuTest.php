<?php

use App\Models\Dish;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Support\PublicMenu;
use App\Support\VenueImage;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->venue = Establishment::factory()->create([
        'slug' => 'vostok',
        'name' => 'Восточный дворик',
    ]);
    $this->category = MenuCategory::factory()->for($this->venue)->create([
        'name_ru' => 'Горячее',
    ]);
});

it('serves the menu to a guest with no session at all', function () {
    Dish::factory()->for($this->venue)->for($this->category, 'category')->create([
        'name_ru' => 'Плов ташкентский',
        'price' => 249000,
    ]);

    $this->getJson('/api/public/menu/vostok')
        ->assertOk()
        ->assertJsonPath('data.name', 'Восточный дворик')
        ->assertJsonPath('data.categories.0.name_ru', 'Горячее')
        ->assertJsonPath('data.categories.0.dishes.0.price', 249000)
        // Brand-new venue has no upload — guests still get the stock cover.
        ->assertJsonPath('data.cover_url', VenueImage::DEFAULT_COVER_URL);

    $this->assertGuest();
});

it('answers 404 for an unknown venue', function () {
    $this->getJson('/api/public/menu/net-takogo')->assertNotFound();
});

it('never sends a hidden dish or section', function () {
    Dish::factory()->for($this->venue)->for($this->category, 'category')->create([
        'name_ru' => 'Видимое',
    ]);
    Dish::factory()->for($this->venue)->for($this->category, 'category')->create([
        'name_ru' => 'Черновик',
        'is_visible' => false,
    ]);

    $hidden = MenuCategory::factory()->for($this->venue)->create([
        'name_ru' => 'Скрытый раздел',
        'is_visible' => false,
    ]);
    Dish::factory()->for($this->venue)->for($hidden, 'category')->create();

    $data = $this->getJson('/api/public/menu/vostok')->assertOk()->json('data');

    $sections = collect($data['categories'])->pluck('name_ru');
    $dishes = collect($data['categories'])->flatMap(fn ($c) => collect($c['dishes'])->pluck('name_ru'));

    // Filtered in the query, so it is not merely hidden by the client.
    expect($dishes)->toContain('Видимое')
        ->and($dishes)->not->toContain('Черновик')
        ->and($sections)->not->toContain('Скрытый раздел');
});

it('still shows a dish on the stop list, flagged', function () {
    Dish::factory()->for($this->venue)->for($this->category, 'category')->create([
        'name_ru' => 'Закончился',
        'is_available' => false,
    ]);

    // A guest should see it exists and can ask, rather than wonder.
    $this->getJson('/api/public/menu/vostok')
        ->assertOk()
        ->assertJsonPath('data.categories.0.dishes.0.name_ru', 'Закончился')
        ->assertJsonPath('data.categories.0.dishes.0.is_available', false);
});

it('caches the answer', function () {
    $this->getJson('/api/public/menu/vostok')->assertOk();

    expect(Cache::has(PublicMenu::cacheKey('vostok')))->toBeTrue();
});

it('drops the cache when a dish changes', function () {
    $dish = Dish::factory()->for($this->venue)->for($this->category, 'category')->create([
        'name_ru' => 'Старое название',
        'price' => 100000,
    ]);

    $this->getJson('/api/public/menu/vostok')->assertOk();

    $dish->update(['price' => 199000, 'name_ru' => 'Новое название']);

    // A stale price on a guest's screen is the worst kind of bug here.
    $this->getJson('/api/public/menu/vostok')
        ->assertOk()
        ->assertJsonPath('data.categories.0.dishes.0.price', 199000)
        ->assertJsonPath('data.categories.0.dishes.0.name_ru', 'Новое название');
});

it('drops the cache when a dish goes on the stop list', function () {
    $dish = Dish::factory()->for($this->venue)->for($this->category, 'category')->create();

    $this->getJson('/api/public/menu/vostok')->assertOk();

    $dish->update(['is_available' => false]);

    $this->getJson('/api/public/menu/vostok')
        ->assertOk()
        ->assertJsonPath('data.categories.0.dishes.0.is_available', false);
});

it('drops the cache when a section is added or removed', function () {
    $this->getJson('/api/public/menu/vostok')->assertOk();

    MenuCategory::factory()->for($this->venue)->create(['name_ru' => 'Напитки']);

    $names = fn () => collect(
        $this->getJson('/api/public/menu/vostok')->json('data.categories')
    )->pluck('name_ru');

    expect($names())->toContain('Напитки');

    $this->category->delete();

    expect($names())->not->toContain('Горячее');
});

it('clears the old address when a venue is renamed', function () {
    $this->getJson('/api/public/menu/vostok')->assertOk();

    $this->venue->update(['slug' => 'vostok-new']);

    // The old key would otherwise keep answering after the address moved.
    $this->getJson('/api/public/menu/vostok')->assertNotFound();
    $this->getJson('/api/public/menu/vostok-new')->assertOk();
});
