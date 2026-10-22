<?php

use App\Models\User;

it('lets an owner set a phone they did not have (Google sign-in)', function () {
    $user = User::factory()->create(['phone' => null]);

    $this->actingAs($user)
        ->putJson('/api/user/phone', ['phone' => '8 (701) 123-45-67'])
        ->assertOk()
        ->assertJsonPath('data.phone', '77011234567');

    expect($user->fresh()->phone)->toBe('77011234567');
});

it('changes an existing phone', function () {
    $user = User::factory()->create(['phone' => '77011234567']);

    $this->actingAs($user)
        ->putJson('/api/user/phone', ['phone' => '+7 777 000 11 22'])
        ->assertOk();

    expect($user->fresh()->phone)->toBe('77770001122');
});

it('refuses to clear or garble the phone', function (?string $typed) {
    $user = User::factory()->create(['phone' => '77011234567']);

    $this->actingAs($user)
        ->putJson('/api/user/phone', ['phone' => $typed])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    expect($user->fresh()->phone)->toBe('77011234567');
})->with([null, '', '+7 701 12']);

it('requires a session', function () {
    $this->putJson('/api/user/phone', ['phone' => '+7 701 123 45 67'])
        ->assertUnauthorized();
});
