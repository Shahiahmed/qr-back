<?php

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config([
        'services.telegram.bot_token' => '123456:test-token',
        'services.telegram.bot_username' => 'qmenu_bot',
        'services.telegram.webhook_secret' => 'hook-secret',
    ]);
});

// --- Owner: connect deep link -------------------------------------------------

it('mints a connect deep link for the owner', function () {
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();

    $url = $this->actingAs($owner)
        ->postJson("/api/establishments/{$venue->id}/telegram")
        ->assertOk()
        ->json('url');

    expect($url)->toStartWith('https://t.me/qmenu_bot?start=');
    expect($venue->fresh()->telegram_link_token)->not->toBeNull();
});

it('will not mint a link for another owner\'s venue', function () {
    $venue = Establishment::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson("/api/establishments/{$venue->id}/telegram")
        ->assertNotFound();
});

it('refuses to mint a link when the bot username is unset', function () {
    config(['services.telegram.bot_username' => null]);

    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->postJson("/api/establishments/{$venue->id}/telegram")
        ->assertStatus(409);
});

it('does not expose the chat id or token in the resource', function () {
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();
    $venue->forceFill(['telegram_chat_id' => '42', 'telegram_link_token' => 'tok'])->save();

    $data = $this->actingAs($owner)
        ->getJson("/api/establishments/{$venue->id}")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKey('telegram_connected')
        ->and($data['telegram_connected'])->toBeTrue()
        ->and($data)->not->toHaveKey('telegram_chat_id')
        ->and($data)->not->toHaveKey('telegram_link_token');
});

it('unbinds the chat for the owner', function () {
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create();
    $venue->forceFill(['telegram_chat_id' => '42'])->save();

    $this->actingAs($owner)
        ->deleteJson("/api/establishments/{$venue->id}/telegram")
        ->assertOk()
        ->assertJsonPath('data.telegram_connected', false);

    expect($venue->fresh()->telegram_chat_id)->toBeNull();
});

// --- Webhook: /start binds the chat ------------------------------------------

it('binds the chat when the /start token matches', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $venue = Establishment::factory()->create();
    $venue->forceFill(['telegram_link_token' => 'the-token'])->save();

    $this->postJson('/api/telegram/webhook/hook-secret', [
        'message' => [
            'chat' => ['id' => 555444333],
            'text' => '/start the-token',
        ],
    ])->assertOk();

    $venue->refresh();
    expect($venue->telegram_chat_id)->toBe('555444333')
        // Token is single-use: cleared once bound.
        ->and($venue->telegram_link_token)->toBeNull();
});

it('ignores the webhook when the path secret is wrong', function () {
    $venue = Establishment::factory()->create();
    $venue->forceFill(['telegram_link_token' => 'the-token'])->save();

    $this->postJson('/api/telegram/webhook/WRONG', [
        'message' => ['chat' => ['id' => 1], 'text' => '/start the-token'],
    ])->assertOk();

    expect($venue->fresh()->telegram_chat_id)->toBeNull();
});

it('ignores a /start with an unknown token', function () {
    $this->postJson('/api/telegram/webhook/hook-secret', [
        'message' => ['chat' => ['id' => 1], 'text' => '/start nope'],
    ])->assertOk();

    // Nothing bound — no exception either.
    expect(Establishment::whereNotNull('telegram_chat_id')->count())->toBe(0);
});

// --- Guest: call waiter -------------------------------------------------------

it('sends a waiter call to the venue chat', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $venue = Establishment::factory()->create(['slug' => 'dastarkhan']);
    $venue->forceFill(['telegram_chat_id' => '700700'])->save();

    $this->postJson('/api/public/menu/dastarkhan/waiter-call', [
        'reason' => 'bill',
        'table' => '12',
    ])->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org/bot123456:test-token/sendMessage')
            && (string) $request['chat_id'] === '700700'
            && str_contains($request['text'], 'Счёт')
            && str_contains($request['text'], '12');
    });
});

it('rejects a waiter call for a venue with no chat bound', function () {
    Http::fake();

    Establishment::factory()->create(['slug' => 'no-bot']);

    $this->postJson('/api/public/menu/no-bot/waiter-call', ['reason' => 'waiter'])
        ->assertNotFound();

    Http::assertNothingSent();
});

it('rejects a waiter call for an expired menu', function () {
    Http::fake();

    $venue = Establishment::factory()->create([
        'slug' => 'lapsed',
        'trial_ends_at' => now()->subDay(),
    ]);
    $venue->forceFill(['telegram_chat_id' => '1'])->save();

    $this->postJson('/api/public/menu/lapsed/waiter-call', ['reason' => 'waiter'])
        ->assertNotFound();

    Http::assertNothingSent();
});

it('validates the reason', function () {
    $venue = Establishment::factory()->create(['slug' => 'v']);
    $venue->forceFill(['telegram_chat_id' => '1'])->save();

    $this->postJson('/api/public/menu/v/waiter-call', ['reason' => 'nonsense'])
        ->assertStatus(422);
});

it('throttles repeated waiter calls per venue', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $venue = Establishment::factory()->create(['slug' => 'busy']);
    $venue->forceFill(['telegram_chat_id' => '1'])->save();

    // 3 allowed within the window, then it stops hitting the bot.
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/public/menu/busy/waiter-call', ['reason' => 'waiter'])->assertOk();
    }
    $this->postJson('/api/public/menu/busy/waiter-call', ['reason' => 'waiter'])
        ->assertOk()
        ->assertJsonPath('throttled', true);

    Http::assertSentCount(3);

    RateLimiter::clear("waiter-call:{$venue->id}");
});

it('advertises waiter_call_enabled on the public menu', function () {
    $venue = Establishment::factory()->create(['slug' => 'flagged']);

    $this->getJson('/api/public/menu/flagged')
        ->assertOk()
        ->assertJsonPath('data.waiter_call_enabled', false);

    $venue->forceFill(['telegram_chat_id' => '1'])->save();
    // The saved event drops the cache, so the flag flips on next read.
    $this->getJson('/api/public/menu/flagged')
        ->assertOk()
        ->assertJsonPath('data.waiter_call_enabled', true);
});
