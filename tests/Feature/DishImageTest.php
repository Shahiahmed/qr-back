<?php

use App\Models\Dish;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->venue = Establishment::factory()->for($this->owner)->create();
    $this->category = MenuCategory::factory()->for($this->venue)->create();
    $this->dish = Dish::factory()->for($this->venue)->for($this->category, 'category')->create();
    $this->actingAs($this->owner);
});

function uploadDishPhoto(Dish $dish, UploadedFile $file)
{
    return test()->postJson(
        "/api/establishments/{$dish->establishment_id}/dishes/{$dish->id}/image",
        ['file' => $file],
    );
}

it('stores a dish photo as webp and exposes its url', function () {
    uploadDishPhoto($this->dish, UploadedFile::fake()->image('plov.jpg', 1200, 900))
        ->assertOk()
        ->assertJsonPath('data.image_url', fn ($url) => is_string($url) && str_contains($url, '.webp'));

    $path = $this->dish->fresh()->image_path;
    expect($path)->not->toBeNull()->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

it('replaces the previous photo, deleting the old file', function () {
    uploadDishPhoto($this->dish, UploadedFile::fake()->image('one.jpg'))->assertOk();
    $first = $this->dish->fresh()->image_path;

    uploadDishPhoto($this->dish, UploadedFile::fake()->image('two.jpg'))->assertOk();
    $second = $this->dish->fresh()->image_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('removes a dish photo and clears its column', function () {
    uploadDishPhoto($this->dish, UploadedFile::fake()->image('plov.jpg'))->assertOk();
    $path = $this->dish->fresh()->image_path;

    $this->deleteJson("/api/establishments/{$this->venue->id}/dishes/{$this->dish->id}/image")
        ->assertOk()
        ->assertJsonPath('data.image_url', null);

    expect($this->dish->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects a non-image upload', function () {
    uploadDishPhoto($this->dish, UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('will not let an owner add a photo to another tenant’s dish', function () {
    $foreignVenue = Establishment::factory()->create();
    $foreignCategory = MenuCategory::factory()->for($foreignVenue)->create();
    $foreignDish = Dish::factory()->for($foreignVenue)->for($foreignCategory, 'category')->create();

    // Route scoped by the attacker's own venue but a foreign dish id → 404.
    $this->postJson(
        "/api/establishments/{$this->venue->id}/dishes/{$foreignDish->id}/image",
        ['file' => UploadedFile::fake()->image('x.jpg')],
    )->assertNotFound();

    expect($foreignDish->fresh()->image_path)->toBeNull();
});

it('serves the dish photo url in the public menu', function () {
    uploadDishPhoto($this->dish, UploadedFile::fake()->image('plov.jpg'))->assertOk();

    $this->getJson("/api/public/menu/{$this->venue->slug}")
        ->assertOk()
        ->assertJsonPath(
            'data.categories.0.dishes.0.image_url',
            fn ($url) => is_string($url) && str_contains($url, '.webp'),
        );
});
