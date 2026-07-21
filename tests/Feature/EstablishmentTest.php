<?php

use App\Models\Establishment;
use App\Models\User;

const VENUE = [
    'name' => 'Восточный дворик',
    'slug' => 'vostochny-dvorik',
    'currency' => 'KZT',
    'default_locale' => 'ru',
];

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
});

it('creates a venue for the signed-in owner', function () {
    $this->postJson('/api/establishments', VENUE)
        ->assertCreated()
        ->assertJsonPath('data.slug', 'vostochny-dvorik')
        ->assertJsonPath('data.currency', 'KZT');

    expect(Establishment::firstWhere('slug', 'vostochny-dvorik')->user_id)
        ->toBe($this->owner->id);
});

it('lists only the venues that belong to the caller', function () {
    Establishment::factory()->for($this->owner)->create(['slug' => 'mine']);
    Establishment::factory()->create(['slug' => 'someone-else']);

    $response = $this->getJson('/api/establishments')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.slug'))->toBe('mine');
});

it('hides another owner\'s venue behind a 404 rather than a 403', function () {
    $foreign = Establishment::factory()->create();

    // 403 would confirm the id exists; 404 says nothing either way.
    $this->getJson("/api/establishments/{$foreign->id}")->assertNotFound();
    $this->patchJson("/api/establishments/{$foreign->id}", ['name' => 'Угон'])
        ->assertNotFound();
    $this->deleteJson("/api/establishments/{$foreign->id}")->assertNotFound();

    expect($foreign->fresh()->name)->not->toBe('Угон');
});

it('keeps slugs unique across every owner', function () {
    Establishment::factory()->create(['slug' => 'taken']);

    $this->postJson('/api/establishments', [...VENUE, 'slug' => 'taken'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('slug');
});

it('explains a taken slug without talking about email', function () {
    Establishment::factory()->create(['slug' => 'taken']);

    $message = $this->postJson('/api/establishments', [...VENUE, 'slug' => 'taken'])
        ->assertStatus(422)
        ->json('errors.slug.0');

    // The `unique` rule fires for several fields; a shared message once told
    // someone renaming a venue that their email was already registered.
    expect($message)->not->toContain('email')
        ->and($message)->not->toContain('Email');
});

it('refuses a slug that would shadow an app route', function () {
    foreach (['api', 'ru', 'kz', 'login', 'dashboard'] as $reserved) {
        $this->postJson('/api/establishments', [...VENUE, 'slug' => $reserved])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    expect(Establishment::count())->toBe(0);
});

it('refuses a slug that is not url-safe', function () {
    foreach (['Восточный', 'with space', '-lead', 'trail-', 'double--hyphen', 'a'] as $bad) {
        $this->postJson('/api/establishments', [...VENUE, 'slug' => $bad])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }
});

it('lowercases a slug instead of rejecting it', function () {
    // Someone typing "Vostok" means `vostok`, not a mistake worth an error.
    $this->postJson('/api/establishments', [...VENUE, 'slug' => 'Vostok-KZ'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'vostok-kz');
});

it('rejects a currency or language it does not offer', function () {
    $this->postJson('/api/establishments', [...VENUE, 'currency' => 'GBP'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('currency');

    // `kz` is a country code; the language tag is `kk`.
    $this->postJson('/api/establishments', [...VENUE, 'default_locale' => 'kz'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('default_locale');
});

it('lets a venue keep its own slug when saving', function () {
    $venue = Establishment::factory()->for($this->owner)->create(['slug' => 'vostok']);

    $this->patchJson("/api/establishments/{$venue->id}", [
        'name' => 'Восточный дворик 2',
        'slug' => 'vostok',
    ])->assertOk()->assertJsonPath('data.name', 'Восточный дворик 2');
});

it('allows one owner to hold several venues', function () {
    $this->postJson('/api/establishments', VENUE)->assertCreated();
    $this->postJson('/api/establishments', [...VENUE, 'slug' => 'vtoraya-tochka'])
        ->assertCreated();

    expect($this->owner->establishments()->count())->toBe(2);
});

it('keeps venues closed to guests', function () {
    auth()->logout();

    $this->getJson('/api/establishments')->assertUnauthorized();
    $this->postJson('/api/establishments', VENUE)->assertUnauthorized();
});
