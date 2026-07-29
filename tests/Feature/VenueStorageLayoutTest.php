<?php

use App\Models\Dish;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\User;
use App\Support\DishImage;
use App\Support\VenueImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->venue = Establishment::factory()->for($this->owner)->create(['slug' => 'vostok']);
    $this->actingAs($this->owner);
});

it('keeps every image of a venue under one named folder', function () {
    VenueImage::store($this->venue, 'cover', UploadedFile::fake()->image('c.jpg'));
    VenueImage::store($this->venue, 'logo', UploadedFile::fake()->image('l.png'));

    $category = MenuCategory::factory()->for($this->venue)->create();
    $dish = Dish::factory()->for($this->venue)->for($category, 'category')->create();
    DishImage::store($dish, UploadedFile::fake()->image('plov.jpg'));

    $folder = "venues/{$this->venue->id}-vostok";

    expect($this->venue->fresh()->cover_path)->toStartWith("{$folder}/cover/");
    expect($this->venue->fresh()->logo_path)->toStartWith("{$folder}/logo/");
    expect($dish->fresh()->image_path)->toStartWith("{$folder}/dishes/{$dish->id}/");
});

it('creates the venue folder as soon as the venue exists', function () {
    $fresh = Establishment::factory()->for($this->owner)->create(['slug' => 'newby']);

    Storage::disk('public')->assertExists("venues/{$fresh->id}-newby");
});

it('relocates the whole folder and rewrites paths when the slug changes', function () {
    VenueImage::store($this->venue, 'cover', UploadedFile::fake()->image('c.jpg'));
    $category = MenuCategory::factory()->for($this->venue)->create();
    $dish = Dish::factory()->for($this->venue)->for($category, 'category')->create();
    DishImage::store($dish, UploadedFile::fake()->image('plov.jpg'));

    $oldCover = $this->venue->fresh()->cover_path;
    $oldDish = $dish->fresh()->image_path;

    $this->venue->update(['slug' => 'east-court']);

    $newFolder = "venues/{$this->venue->id}-east-court";

    // Paths were rebased onto the new folder…
    $newCover = $this->venue->fresh()->cover_path;
    $newDish = $dish->fresh()->image_path;
    expect($newCover)->toStartWith("{$newFolder}/cover/");
    expect($newDish)->toStartWith("{$newFolder}/dishes/");

    // …the files moved with them, and nothing is left behind in the old folder.
    Storage::disk('public')->assertExists($newCover);
    Storage::disk('public')->assertExists($newDish);
    Storage::disk('public')->assertMissing($oldCover);
    Storage::disk('public')->assertMissing($oldDish);
    Storage::disk('public')->assertMissing("venues/{$this->venue->id}-vostok");
});
