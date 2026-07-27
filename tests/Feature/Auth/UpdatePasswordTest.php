<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'aigul@vostok.kz',
        'password' => 'old-password-2490',
    ]);
});

it('changes the password when the current one is correct', function () {
    $this->actingAs($this->user)
        ->putJson('/api/user/password', [
            'current_password' => 'old-password-2490',
            'password' => 'new-password-3900',
            'password_confirmation' => 'new-password-3900',
        ])
        ->assertNoContent();

    expect(Hash::check('new-password-3900', $this->user->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-password-2490', $this->user->fresh()->password))->toBeFalse();
});

it('rejects a wrong current password', function () {
    $this->actingAs($this->user)
        ->putJson('/api/user/password', [
            'current_password' => 'not-the-password',
            'password' => 'new-password-3900',
            'password_confirmation' => 'new-password-3900',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('old-password-2490', $this->user->fresh()->password))->toBeTrue();
});

it('requires the new password to be confirmed', function () {
    $this->actingAs($this->user)
        ->putJson('/api/user/password', [
            'current_password' => 'old-password-2490',
            'password' => 'new-password-3900',
            'password_confirmation' => 'mismatch',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('refuses guests', function () {
    $this->putJson('/api/user/password', [
        'current_password' => 'old-password-2490',
        'password' => 'new-password-3900',
        'password_confirmation' => 'new-password-3900',
    ])->assertUnauthorized();
});
