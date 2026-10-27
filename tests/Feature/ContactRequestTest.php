<?php

use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ContactRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.telegram.bot_token' => '123456:test-token',
        'services.telegram.admin_chat_id' => '99',
    ]);
    Http::fake();
});

function lead(array $overrides = []): array
{
    return array_merge([
        'name' => 'Айгерим',
        'phone' => '8 701 123 45 67',
        'note' => 'Кофейня, 2 зала',
        RegisterRequest::ELAPSED => 5000,
    ], $overrides);
}

it('stores a lead and pings the admin telegram', function () {
    $this->postJson('/api/contact-requests', lead())->assertCreated();

    $row = ContactRequest::sole();
    expect($row->phone)->toBe('77011234567')
        ->and($row->status)->toBe(ContactRequest::STATUS_NEW);

    Http::assertSent(fn ($req) => $req['chat_id'] === '99'
        && str_contains($req['text'], 'Айгерим')
        && str_contains($req['text'], '+7 701 123 45 67'));
});

it('rejects a bad phone', function () {
    $this->postJson('/api/contact-requests', lead(['phone' => '123']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    expect(ContactRequest::count())->toBe(0);
});

it('rejects bots by honeypot and timing', function (array $overrides) {
    $this->postJson('/api/contact-requests', lead($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('form');

    expect(ContactRequest::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'honeypot' => [[RegisterRequest::HONEYPOT => 'x']],
    'too fast' => [[RegisterRequest::ELAPSED => 200]],
    'no timing' => [[RegisterRequest::ELAPSED => null]],
]);

it('lists leads in the admin panel and marks one handled', function () {
    $lead = ContactRequest::create(['name' => 'Ерлан', 'phone' => '77011234567']);

    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(ListContactRequests::class)
        ->assertOk()
        ->assertSee('Ерлан')
        ->callTableAction('done', $lead);

    expect($lead->fresh()->status)->toBe(ContactRequest::STATUS_DONE);
});
