<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config(['app.frontend_url' => 'https://qr-menu.kz']);
});

/** Stub Socialite so the callback resolves a fixed Google identity. */
function fakeGoogleUser(array $attributes): void
{
    $user = (new SocialiteUser())->map([
        'id' => $attributes['id'],
        'name' => $attributes['name'] ?? null,
        'email' => $attributes['email'],
        'avatar' => $attributes['avatar'] ?? null,
    ]);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($user);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('creates and signs in a brand-new Google user', function () {
    fakeGoogleUser([
        'id' => '110000000001',
        'name' => 'Aigul Nurlanovna',
        'email' => 'Aigul@Gmail.com',
        'avatar' => 'https://lh3.googleusercontent.com/a/pic',
    ]);

    $this->withSession(['oauth_locale' => 'kz'])
        ->get('/auth/google/callback')
        ->assertRedirect('https://qr-menu.kz/kz/dashboard');

    $user = User::where('email', 'aigul@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('110000000001')
        ->and($user->avatar)->toBe('https://lh3.googleusercontent.com/a/pic')
        ->and($user->name)->toBe('Aigul Nurlanovna')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('links Google to an existing password account by email', function () {
    $existing = User::factory()->create([
        'email' => 'aigul@vostok.kz',
        'name' => 'Aigul',
        'password' => 'ploV-2490-tashkent',
    ]);

    fakeGoogleUser([
        'id' => '110000000002',
        'name' => 'Aigul from Google',
        'email' => 'aigul@vostok.kz',
        'avatar' => 'https://lh3.googleusercontent.com/a/pic2',
    ]);

    $this->withSession(['oauth_locale' => 'ru'])
        ->get('/auth/google/callback')
        ->assertRedirect('https://qr-menu.kz/ru/dashboard');

    // Same row, now carrying the Google subject — not a duplicate account.
    expect(User::where('email', 'aigul@vostok.kz')->count())->toBe(1);

    $existing->refresh();
    expect($existing->google_id)->toBe('110000000002')
        // The original name and password survive the link.
        ->and($existing->name)->toBe('Aigul')
        ->and($existing->password)->not->toBeNull();

    $this->assertAuthenticatedAs($existing);
});

it('re-signs a returning Google user matched by google_id', function () {
    $existing = User::factory()->create([
        'email' => 'old@gmail.com',
        'password' => null,
    ]);
    $existing->forceFill(['google_id' => '110000000003'])->save();

    // Google now reports a different email for the same subject.
    fakeGoogleUser([
        'id' => '110000000003',
        'name' => 'Renamed',
        'email' => 'new@gmail.com',
        'avatar' => null,
    ]);

    $this->withSession(['oauth_locale' => 'ru'])
        ->get('/auth/google/callback')
        ->assertRedirect('https://qr-menu.kz/ru/dashboard');

    expect(User::where('google_id', '110000000003')->count())->toBe(1);
    $this->assertAuthenticatedAs($existing->fresh());
});

it('bounces back to login when Google sign-in fails', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new RuntimeException('denied'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->withSession(['oauth_locale' => 'kz'])
        ->get('/auth/google/callback')
        ->assertRedirect('https://qr-menu.kz/kz/login?error=google');

    $this->assertGuest();
});

it('does not let google_id be mass-assigned', function () {
    $user = new User();
    $user->fill(['name' => 'X', 'email' => 'x@x.kz', 'google_id' => 'hacked']);

    expect($user->google_id)->toBeNull();
});
