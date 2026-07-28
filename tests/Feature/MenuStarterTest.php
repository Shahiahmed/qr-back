<?php

use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\User;
use App\Support\MenuStarter;

it('fills a new venue with the starter menu on create', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->postJson('/api/establishments', [
            'name' => 'Восточный дворик',
            'slug' => 'vostok-starter',
            'currency' => 'KZT',
            'default_locale' => 'ru',
        ])
        ->assertCreated();

    $venue = Establishment::query()->where('slug', 'vostok-starter')->firstOrFail();

    expect($venue->categories)->toHaveCount(count(MenuStarter::blueprint()))
        ->and($venue->dishes()->count())->toBeGreaterThan(0);

    $names = $venue->categories()->orderBy('position')->pluck('name_ru')->all();
    expect($names)->toBe(['Горячее', 'Салаты', 'Напитки']);
});

it('applies the starter to an empty venue on demand', function () {
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create(['slug' => 'empty-hall']);

    $this->actingAs($owner)
        ->postJson("/api/establishments/{$venue->id}/menu/starter")
        ->assertCreated()
        ->assertJsonCount(3, 'data');

    expect($venue->fresh()->categories)->toHaveCount(3);
});

it('refuses to overwrite a menu that already has sections', function () {
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();
    MenuCategory::factory()->for($venue)->create(['name_ru' => 'Уже есть']);

    $this->actingAs($owner)
        ->postJson("/api/establishments/{$venue->id}/menu/starter")
        ->assertStatus(409);

    expect($venue->fresh()->categories)->toHaveCount(1)
        ->and($venue->fresh()->categories->first()->name_ru)->toBe('Уже есть');
});

it('hides the starter endpoint from other owners', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();

    $this->actingAs($stranger)
        ->postJson("/api/establishments/{$venue->id}/menu/starter")
        ->assertNotFound();
});
