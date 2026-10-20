<?php

use App\Filament\Resources\Establishments\Pages\EditEstablishment;
use App\Filament\Resources\Establishments\Pages\ListEstablishments;
use App\Models\Dish;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\User;
use App\Support\PublicMenu;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);

    $this->venue = Establishment::factory()->for(User::factory())->create();
    $category = MenuCategory::factory()->create(['establishment_id' => $this->venue->id]);
    Dish::factory()->create(['establishment_id' => $this->venue->id, 'menu_category_id' => $category->id]);
});

it('blocks a menu from the table and guests get 423 without the menu body', function () {
    // Warm the cache first — the block must drop it, not wait a day.
    $this->getJson("/api/public/menu/{$this->venue->slug}")->assertOk();

    Livewire::test(ListEstablishments::class)
        ->callTableAction('blockMenu', $this->venue, ['blocked_reason' => 'Неоплата'])
        ->assertHasNoTableActionErrors();

    $this->venue->refresh();
    expect($this->venue->isBlocked())->toBeTrue()
        ->and($this->venue->blocked_reason)->toBe('Неоплата');

    $this->getJson("/api/public/menu/{$this->venue->slug}")
        ->assertStatus(423)
        ->assertExactJson(['blocked' => true]);
});

it('unblocks a menu from the edit page and it opens again', function () {
    $this->venue->forceFill(['blocked_at' => now(), 'blocked_reason' => 'x'])->save();
    $this->getJson("/api/public/menu/{$this->venue->slug}")->assertStatus(423);

    Livewire::test(EditEstablishment::class, ['record' => $this->venue->getRouteKey()])
        ->callAction('unblockMenu')
        ->assertHasNoActionErrors();

    expect($this->venue->refresh()->isBlocked())->toBeFalse()
        ->and($this->venue->blocked_reason)->toBeNull();

    $this->getJson("/api/public/menu/{$this->venue->slug}")
        ->assertOk()
        ->assertJsonMissingPath('data.is_blocked');
});

it('shows only the matching action for the current state', function () {
    Livewire::test(ListEstablishments::class)
        ->assertTableActionVisible('blockMenu', $this->venue)
        ->assertTableActionHidden('unblockMenu', $this->venue);

    $this->venue->forceFill(['blocked_at' => now()])->save();

    Livewire::test(ListEstablishments::class)
        ->assertTableActionHidden('blockMenu', $this->venue)
        ->assertTableActionVisible('unblockMenu', $this->venue);
});

it('filters blocked venues in the table', function () {
    $blocked = Establishment::factory()->for(User::factory())->create();
    $blocked->forceFill(['blocked_at' => now()])->save();

    Livewire::test(ListEstablishments::class)
        ->filterTable('blocked', true)
        ->assertCanSeeTableRecords([$blocked])
        ->assertCanNotSeeTableRecords([$this->venue]);
});

it('does not let the owner unblock through the venue form', function () {
    $this->venue->forceFill(['blocked_at' => now()])->save();
    $owner = $this->venue->user;

    $this->actingAs($owner)
        ->putJson("/api/establishments/{$this->venue->id}", [
            'name' => $this->venue->name,
            'slug' => $this->venue->slug,
            'currency' => $this->venue->currency,
            'default_locale' => $this->venue->default_locale,
            'blocked_at' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.is_blocked', true);

    expect($this->venue->refresh()->isBlocked())->toBeTrue();
});

it('rejects waiter calls and orders on a blocked menu', function () {
    $this->venue->forceFill(['blocked_at' => now(), 'telegram_chat_id' => '123'])->save();
    $dishId = $this->venue->dishes()->value('id');

    $this->postJson("/api/public/menu/{$this->venue->slug}/waiter-call", ['reason' => 'waiter'])->assertNotFound();
    $this->postJson("/api/public/menu/{$this->venue->slug}/order", ['items' => [['dish_id' => $dishId, 'qty' => 1]]])->assertNotFound();
});

it('keeps the cache key versioned so stale entries without the flag are skipped', function () {
    expect(PublicMenu::cacheKey('x'))->toBe('public-menu:v5:x');
    Cache::put(PublicMenu::cacheKey($this->venue->slug), ['stale' => true]);
    $this->venue->forceFill(['blocked_at' => now()])->save();
    expect(Cache::has(PublicMenu::cacheKey($this->venue->slug)))->toBeFalse();
});
