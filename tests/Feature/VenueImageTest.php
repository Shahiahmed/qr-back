<?php

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->venue = Establishment::factory()->for($this->owner)->create();
    $this->actingAs($this->owner);
});

it('stores an uploaded cover and exposes its url', function () {
    $this->postJson("/api/establishments/{$this->venue->id}/image", [
        'kind' => 'cover',
        'file' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
    ])->assertOk()->assertJsonPath('data.cover_url', fn ($url) => is_string($url) && str_contains($url, '.webp'));

    $path = $this->venue->fresh()->cover_path;
    expect($path)->not->toBeNull();
    // Re-encoded to WebP regardless of the JPEG that came in.
    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

it('stores a logo in its own slot', function () {
    $this->postJson("/api/establishments/{$this->venue->id}/image", [
        'kind' => 'logo',
        'file' => UploadedFile::fake()->image('logo.png', 400, 400),
    ])->assertOk()->assertJsonPath('data.logo_url', fn ($url) => is_string($url));

    expect($this->venue->fresh()->logo_path)->not->toBeNull();
});

it('deletes the previous file when a slot is replaced', function () {
    $this->postJson("/api/establishments/{$this->venue->id}/image", [
        'kind' => 'cover',
        'file' => UploadedFile::fake()->image('one.jpg'),
    ])->assertOk();

    $first = $this->venue->fresh()->cover_path;

    $this->postJson("/api/establishments/{$this->venue->id}/image", [
        'kind' => 'cover',
        'file' => UploadedFile::fake()->image('two.jpg'),
    ])->assertOk();

    $second = $this->venue->fresh()->cover_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('removes an image and clears its column', function () {
    $this->postJson("/api/establishments/{$this->venue->id}/image", [
        'kind' => 'cover',
        'file' => UploadedFile::fake()->image('cover.jpg'),
    ])->assertOk();

    $path = $this->venue->fresh()->cover_path;

    $this->deleteJson("/api/establishments/{$this->venue->id}/image/cover")
        ->assertOk()
        ->assertJsonPath('data.cover_url', null);

    expect($this->venue->fresh()->cover_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects a non-image upload', function () {
    $this->postJson("/api/establishments/{$this->venue->id}/image", [
        'kind' => 'cover',
        'file' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

it('rejects an unknown image slot', function () {
    $this->postJson("/api/establishments/{$this->venue->id}/image", [
        'kind' => 'banner',
        'file' => UploadedFile::fake()->image('x.jpg'),
    ])->assertStatus(422)->assertJsonValidationErrors('kind');
});

it('will not let an owner upload to another venue', function () {
    $foreign = Establishment::factory()->create();

    $this->postJson("/api/establishments/{$foreign->id}/image", [
        'kind' => 'cover',
        'file' => UploadedFile::fake()->image('cover.jpg'),
    ])->assertNotFound();

    expect($foreign->fresh()->cover_path)->toBeNull();
});
