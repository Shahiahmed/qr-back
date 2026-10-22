<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

const VALID = [
    'name' => 'Айгүл Серікова',
    'email' => 'aigul@vostok.kz',
    'phone' => '+7 (701) 123-45-67',
    'password' => 'ploV-2490-tashkent',
    'password_confirmation' => 'ploV-2490-tashkent',
    // The page reports how long the form was open; see the bot trap below.
    'form_time' => 5000,
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

it('rejects a signup that filled the hidden honeypot field', function () {
    $this->postJson('/api/register', [...VALID, 'contact_me_by_fax_only' => 'http://spam.example'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('form');

    expect(User::count())->toBe(0);
});

it('rejects a form submitted faster than a human could type it', function () {
    $this->postJson('/api/register', [...VALID, 'form_time' => 400])
        ->assertStatus(422)
        ->assertJsonValidationErrors('form');

    expect(User::count())->toBe(0);
});

it('rejects a direct API signup that skipped the page', function () {
    $payload = VALID;
    unset($payload['form_time']);

    $this->postJson('/api/register', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('form');

    expect(User::count())->toBe(0);
});

it('lets a human through with an empty honeypot after a few seconds', function () {
    $this->postJson('/api/register', [...VALID, 'contact_me_by_fax_only' => '', 'form_time' => 2500])
        ->assertCreated();
});

it('requires a WhatsApp number so a manager can reach the owner', function () {
    $payload = VALID;
    unset($payload['phone']);

    $this->postJson('/api/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    expect(User::count())->toBe(0);
});

it('stores the phone as bare digits whatever way it was typed', function (string $typed) {
    $this->postJson('/api/register', [...VALID, 'phone' => $typed])->assertCreated();

    expect(User::firstWhere('email', 'aigul@vostok.kz')->phone)->toBe('77011234567');
})->with([
    'international' => '+7 (701) 123-45-67',
    'local trunk 8' => '8 701 123 45 67',
    'without code' => '701 123 4567',
]);

it('rejects a phone that is too short or not a number', function (string $typed) {
    $this->postJson('/api/register', [...VALID, 'phone' => $typed])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
})->with(['+7 701 123', 'позвоните мне', '+1 415 555 0132 99']);

it('returns the phone in the user resource', function () {
    $this->postJson('/api/register', VALID)
        ->assertCreated()
        ->assertJsonPath('data.phone', '77011234567');
});
