<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'aigul@vostok.kz',
        'password' => 'ploV-2490-tashkent',
    ]);
});

it('signs in with the right password', function () {
    $this->postJson('/api/login', [
        'email' => 'aigul@vostok.kz',
        'password' => 'ploV-2490-tashkent',
    ])->assertOk()->assertJsonPath('data.email', 'aigul@vostok.kz');

    $this->assertAuthenticatedAs($this->user);
});

it('accepts the email in any casing', function () {
    $this->postJson('/api/login', [
        'email' => 'AIGUL@Vostok.kz',
        'password' => 'ploV-2490-tashkent',
    ])->assertOk();

    $this->assertAuthenticatedAs($this->user);
});

it('rejects a wrong password without saying which half was wrong', function () {
    $this->postJson('/api/login', [
        'email' => 'aigul@vostok.kz',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('gives an unknown email the same answer as a wrong password', function () {
    $unknown = $this->postJson('/api/login', [
        'email' => 'nobody@vostok.kz',
        'password' => 'wrong-password',
    ]);

    $wrong = $this->postJson('/api/login', [
        'email' => 'aigul@vostok.kz',
        'password' => 'also-wrong',
    ]);

    expect($unknown->json('errors.email'))->toBe($wrong->json('errors.email'));
});

it('throttles after five failed attempts', function () {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/login', [
            'email' => 'aigul@vostok.kz',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    // The sixth is refused even though the password is now correct.
    $this->postJson('/api/login', [
        'email' => 'aigul@vostok.kz',
        'password' => 'ploV-2490-tashkent',
    ])->assertStatus(422);

    $this->assertGuest();
});

it('returns the current user and forgets them after logout', function () {
    /*
     * Driven through the real endpoints rather than `actingAs`: that helper
     * seats the user on the guard object directly, so it survives a logout
     * that only clears the session and the assertion proves nothing.
     */
    $this->postJson('/api/login', [
        'email' => 'aigul@vostok.kz',
        'password' => 'ploV-2490-tashkent',
    ])->assertOk();

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'aigul@vostok.kz');

    $this->postJson('/api/logout')->assertNoContent();

    /*
     * Identity lives under a `login_<guard>_<hash>` session key. What is left
     * behind is the regenerated CSRF token, which is expected.
     */
    $authKeys = array_filter(
        array_keys(session()->all()),
        fn (string $key) => str_starts_with($key, 'login_'),
    );

    expect($authKeys)->toBeEmpty();

    /*
     * Tests reuse one application instance, so the guard still holds the user
     * it resolved while handling the logout request — a browser would simply
     * arrive with the emptied session. Drop the cached guard to model that.
     */
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/user')->assertUnauthorized();
});

it('keeps /api/user closed to guests', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});
