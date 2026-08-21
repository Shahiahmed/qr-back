<?php

use App\Models\Dish;
use App\Models\Establishment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config(['services.telegram.bot_token' => '123456:test-token']);
});

it('sends an order to the venue chat with a server-computed total', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $venue = Establishment::factory()->create(['slug' => 'dastarkhan', 'currency' => 'KZT']);
    $venue->forceFill(['telegram_chat_id' => '700700'])->save();

    $plov = Dish::factory()->for($venue)->create(['name_ru' => 'Плов', 'price' => 150000]);
    $ayran = Dish::factory()->for($venue)->create(['name_ru' => 'Айран', 'price' => 50000]);

    $this->postJson('/api/public/menu/dastarkhan/order', [
        'table' => '12',
        'comment' => 'без лука',
        'items' => [
            ['dish_id' => $plov->id, 'qty' => 2],
            ['dish_id' => $ayran->id, 'qty' => 1],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org/bot123456:test-token/sendMessage')
            && (string) $request['chat_id'] === '700700'
            && str_contains($request['text'], 'Стол: 12')
            && str_contains($request['text'], 'Плов × 2')
            // 2×1500 + 1×500 = 3 500 ₸, computed from the DB, not the request.
            && str_contains($request['text'], 'Итого: 3 500 ₸')
            && str_contains($request['text'], 'без лука');
    });
});

it('ignores dish ids that do not belong to the venue', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $venue = Establishment::factory()->create(['slug' => 'ours']);
    $venue->forceFill(['telegram_chat_id' => '1'])->save();

    $mine = Dish::factory()->for($venue)->create(['name_ru' => 'Мой', 'price' => 100000]);
    $foreign = Dish::factory()->create(['name_ru' => 'Чужой', 'price' => 999900]);

    $this->postJson('/api/public/menu/ours/order', [
        'items' => [
            ['dish_id' => $mine->id, 'qty' => 1],
            ['dish_id' => $foreign->id, 'qty' => 1],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request['text'], 'Мой')
            && ! str_contains($request['text'], 'Чужой')
            && str_contains($request['text'], 'Итого: 1 000 ₸');
    });
});

it('skips a hidden dish', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $venue = Establishment::factory()->create(['slug' => 'hid']);
    $venue->forceFill(['telegram_chat_id' => '1'])->save();

    $hidden = Dish::factory()->for($venue)->create(['is_visible' => false, 'price' => 100000]);

    $this->postJson('/api/public/menu/hid/order', [
        'items' => [['dish_id' => $hidden->id, 'qty' => 1]],
    ])->assertStatus(422);

    Http::assertNothingSent();
});

it('rejects an order for a venue with no chat bound', function () {
    Http::fake();

    $venue = Establishment::factory()->create(['slug' => 'no-bot']);
    $dish = Dish::factory()->for($venue)->create();

    $this->postJson('/api/public/menu/no-bot/order', [
        'items' => [['dish_id' => $dish->id, 'qty' => 1]],
    ])->assertNotFound();

    Http::assertNothingSent();
});

it('rejects an order for an expired menu', function () {
    Http::fake();

    $venue = Establishment::factory()->create([
        'slug' => 'lapsed',
        'trial_ends_at' => now()->subDay(),
    ]);
    $venue->forceFill(['telegram_chat_id' => '1'])->save();
    $dish = Dish::factory()->for($venue)->create();

    $this->postJson('/api/public/menu/lapsed/order', [
        'items' => [['dish_id' => $dish->id, 'qty' => 1]],
    ])->assertNotFound();

    Http::assertNothingSent();
});

it('validates that items are present', function () {
    $venue = Establishment::factory()->create(['slug' => 'v']);
    $venue->forceFill(['telegram_chat_id' => '1'])->save();

    $this->postJson('/api/public/menu/v/order', ['items' => []])
        ->assertStatus(422);
});

it('throttles repeated orders per venue', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $venue = Establishment::factory()->create(['slug' => 'busy']);
    $venue->forceFill(['telegram_chat_id' => '1'])->save();
    $dish = Dish::factory()->for($venue)->create();

    $body = ['items' => [['dish_id' => $dish->id, 'qty' => 1]]];

    // 10 allowed within the window, then the 11th is refused.
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/public/menu/busy/order', $body)->assertOk();
    }
    $this->postJson('/api/public/menu/busy/order', $body)->assertStatus(429);

    Http::assertSentCount(10);

    RateLimiter::clear("order:{$venue->id}");
});
