<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

const VALID = [
    'name' => 'Айгүл Серікова',
    'email' => 'aigul@vostok.kz',
    'password' => 'ploV-2490-tashkent',
    'password_confirmation' => 'ploV-2490-tashkent',
];

it('creates the account and signs it in', function () {
    $response = $this->postJson('/api/register', VALID);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'aigul@vostok.kz')
        ->assertJsonPath('data.name', 'Айгүл Серікова');

    $user = User::firstWhere('email', 'aigul@vostok.kz');

    expect($user)->not->toBeNull()
        ->and(Hash::check(VALID['password'], $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('never exposes the password hash', function () {
    $this->postJson('/api/register', VALID)
        ->assertCreated()
        ->assertJsonMissingPath('data.password');
});

it('stores the email lowercased so a duplicate cannot slip through casing', function () {
    $this->postJson('/api/register', [...VALID, 'email' => 'Aigul@Vostok.KZ'])
        ->assertCreated();

    expect(User::firstWhere('email', 'aigul@vostok.kz'))->not->toBeNull();

    $this->postJson('/api/register', VALID)
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'aigul@vostok.kz']);

    $this->postJson('/api/register', VALID)
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('requires the password to be confirmed', function () {
    $this->postJson('/api/register', [...VALID, 'password_confirmation' => 'something-else'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    expect(User::count())->toBe(0);
});

it('rejects a short password', function () {
    $this->postJson('/api/register', [
        ...VALID,
        'password' => 'kort',
        'password_confirmation' => 'kort',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('requires name, email and password', function () {
    $this->postJson('/api/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});
