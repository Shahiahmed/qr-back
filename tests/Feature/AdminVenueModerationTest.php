<?php

use App\Filament\Resources\Establishments\Pages\EditEstablishment;
use App\Filament\Resources\Establishments\Pages\ListEstablishments;
use App\Filament\Resources\Establishments\RelationManagers\CategoriesRelationManager;
use App\Filament\Resources\Establishments\RelationManagers\DishesRelationManager;
use App\Filament\Widgets\ExpiringVenues;
use App\Filament\Widgets\PlatformStats;
use App\Models\Dish;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\PublicMenu;
use App\Support\SubscriptionService;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);
});

function venueWithMenu(): array
{
    $venue = Establishment::factory()->for(User::factory())->create();
    $category = MenuCategory::factory()->create(['establishment_id' => $venue->id]);
    $dish = Dish::factory()->create([
        'establishment_id' => $venue->id,
        'menu_category_id' => $category->id,
    ]);

    return [$venue, $category, $dish];
}

/* ---------- menu tabs on the venue page ---------- */

it('shows only this venue\'s sections and dishes', function () {
    [$venue, $category, $dish] = venueWithMenu();
    [, $foreignCategory, $foreignDish] = venueWithMenu();

    Livewire::test(CategoriesRelationManager::class, ['ownerRecord' => $venue, 'pageClass' => EditEstablishment::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$category])
        ->assertCanNotSeeTableRecords([$foreignCategory]);

    Livewire::test(DishesRelationManager::class, ['ownerRecord' => $venue, 'pageClass' => EditEstablishment::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$dish])
        ->assertCanNotSeeTableRecords([$foreignDish]);
});

it('renders the venue edit page with the menu tabs', function () {
    [$venue] = venueWithMenu();

    Livewire::test(EditEstablishment::class, ['record' => $venue->getRouteKey()])->assertOk();
});

it('lets an admin edit a dish, converting tenge to tiyn', function () {
    [$venue, $category, $dish] = venueWithMenu();

    Livewire::test(DishesRelationManager::class, ['ownerRecord' => $venue, 'pageClass' => EditEstablishment::class])
        ->callTableAction('edit', $dish, [
            'menu_category_id' => $category->id,
            'name_ru' => 'Плов праздничный',
            'price' => 3200,
            'is_visible' => true,
            'is_available' => false,
        ])
        ->assertHasNoTableActionErrors();

    $dish->refresh();
    expect($dish->name_ru)->toBe('Плов праздничный')
        ->and($dish->price)->toBe(320000)
        ->and($dish->is_available)->toBeFalse();
});

it('drops the cached guest menu when an admin hides a dish', function () {
    [$venue, , $dish] = venueWithMenu();
    PublicMenu::forSlug($venue->slug);
    expect(Cache::has(PublicMenu::cacheKey($venue->slug)))->toBeTrue();

    Livewire::test(DishesRelationManager::class, ['ownerRecord' => $venue, 'pageClass' => EditEstablishment::class])
        ->call('updateTableColumnState', 'is_visible', (string) $dish->getKey(), false);

    expect($dish->refresh()->is_visible)->toBeFalse()
        ->and(Cache::has(PublicMenu::cacheKey($venue->slug)))->toBeFalse();
});

it('lets an admin delete a section', function () {
    [$venue, $category] = venueWithMenu();

    Livewire::test(CategoriesRelationManager::class, ['ownerRecord' => $venue, 'pageClass' => EditEstablishment::class])
        ->callTableAction('delete', $category)
        ->assertHasNoTableActionErrors();

    expect(MenuCategory::find($category->id))->toBeNull();
});

/* ---------- manual trial extension ---------- */

it('extends a trial from the venues table', function () {
    $venue = Establishment::factory()->for(User::factory())->create();
    $target = Carbon::now()->addDays(30)->startOfMinute();

    Livewire::test(ListEstablishments::class)
        ->callTableAction('extendTrial', $venue, ['days' => 30, 'trial_ends_at' => $target->format('Y-m-d H:i:s')])
        ->assertHasNoTableActionErrors();

    expect($venue->refresh()->trial_ends_at->equalTo($target))->toBeTrue();
});

it('revives an expired menu when its trial is extended', function () {
    $venue = Establishment::factory()->for(User::factory())->create();
    $venue->forceFill(['trial_ends_at' => now()->subDay()])->save();
    expect($venue->refresh()->isExpired())->toBeTrue();

    Livewire::test(EditEstablishment::class, ['record' => $venue->getRouteKey()])
        ->callAction('extendTrial', ['days' => 7, 'trial_ends_at' => now()->addDays(7)->format('Y-m-d H:i:s')])
        ->assertHasNoActionErrors();

    expect($venue->refresh()->isExpired())->toBeFalse();
});

it('hides the trial action for grandfathered (unlimited) venues', function () {
    $venue = Establishment::factory()->for(User::factory())->create();
    $venue->forceFill(['trial_ends_at' => null])->save();

    Livewire::test(ListEstablishments::class)->assertTableActionHidden('extendTrial', $venue);
});

it('does not let owners set trial_ends_at by mass assignment', function () {
    $venue = Establishment::factory()->for(User::factory())->create();
    $before = $venue->trial_ends_at;

    $venue->fill(['trial_ends_at' => now()->addYear()])->save();

    expect($venue->refresh()->trial_ends_at->equalTo($before))->toBeTrue();
});

/* ---------- "expiring soon" widget ---------- */

it('lists menus expiring within a week, most urgent first', function () {
    $soon = Establishment::factory()->for(User::factory())->create();
    $soon->forceFill(['trial_ends_at' => now()->addDays(5)])->save();

    $sooner = Establishment::factory()->for(User::factory())->create();
    $sooner->forceFill(['trial_ends_at' => now()->addDay()])->save();

    $later = Establishment::factory()->for(User::factory())->create();
    $later->forceFill(['trial_ends_at' => now()->addDays(20)])->save();

    $lapsed = Establishment::factory()->for(User::factory())->create();
    $lapsed->forceFill(['trial_ends_at' => now()->subDays(3)])->save();

    $unlimited = Establishment::factory()->for(User::factory())->create();
    $unlimited->forceFill(['trial_ends_at' => null])->save();

    Livewire::test(ExpiringVenues::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$sooner, $soon], inOrder: true)
        ->assertCanNotSeeTableRecords([$later, $lapsed, $unlimited])
        ->filterTable('window', 'expired')
        ->assertCanSeeTableRecords([$lapsed])
        ->assertCanNotSeeTableRecords([$sooner, $soon, $later]);
});

it('uses the subscription end, not the trial, for paid menus', function () {
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create(); // trial ends in 7 days
    $plan = Plan::factory()->create(['period' => 'year']);
    SubscriptionService::approve(SubscriptionRequest::factory()->for($owner)
        ->create(['plan_id' => $plan->id, 'establishment_id' => $venue->id]));

    // Paid for a year — must not be flagged by the old trial date.
    Livewire::test(ExpiringVenues::class)->assertCanNotSeeTableRecords([$venue]);

    Subscription::query()->where('establishment_id', $venue->id)->first()
        ->forceFill(['ends_at' => now()->addDays(2)])->save();

    Livewire::test(ExpiringVenues::class)->assertCanSeeTableRecords([$venue]);
});

it('counts expiring menus on the stats widget', function () {
    $venue = Establishment::factory()->for(User::factory())->create(); // trial: +7 days, within the window

    Livewire::test(PlatformStats::class)
        ->assertOk()
        ->assertSee('Истекают за 7 дней');
});
