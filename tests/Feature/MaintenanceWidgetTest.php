<?php

use App\Filament\Widgets\MaintenanceCommands;
use App\Models\Plan;
use App\Models\PromoSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('renders the maintenance widget for an admin', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(MaintenanceCommands::class)->assertOk();
});

it('exposes five health checks and flags an empty catalogue for the seed confirm', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $widget = new MaintenanceCommands();

    expect($widget->getChecks())->toHaveCount(5)
        ->and($widget->canSeedPlans())->toBeTrue();

    Plan::factory()->create();

    expect($widget->canSeedPlans())->toBeFalse();
});

it('seeds the promo copy without enabling it', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $widget = new MaintenanceCommands();
    expect($widget->promoHasContent())->toBeFalse();

    Livewire::test(MaintenanceCommands::class)
        ->call('runSeedPromo')
        ->assertOk();

    $promo = PromoSetting::current();

    expect($promo->title_ru)->not->toBeEmpty()
        ->and($promo->title_kk)->not->toBeEmpty()
        // Seeding must never switch a live promo on by itself.
        ->and($promo->enabled)->toBeFalse()
        ->and($widget->promoHasContent())->toBeTrue();
});

it('runs a whitelisted command for an admin', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(MaintenanceCommands::class)
        ->call('runClearCache')
        ->assertOk()
        // The command's output lands on screen.
        ->assertSet('lastLabel', 'optimize:clear');
});

it('refuses to run a command for a non-admin', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]));

    Livewire::test(MaintenanceCommands::class)
        ->call('runClearCache')
        ->assertForbidden();
});

it('seeds the default plans even when others already exist', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    // A pre-existing unrelated plan — the seeder keys on name_ru, so it adds the
    // three defaults alongside it rather than skipping (the loud confirm is in
    // the UI; the action itself always runs).
    Plan::factory()->create(['name_ru' => 'Свой тариф']);

    Livewire::test(MaintenanceCommands::class)
        ->call('runSeedPlans')
        ->assertOk();

    expect(Plan::query()->where('name_ru', 'На год')->exists())->toBeTrue()
        ->and(Plan::query()->where('name_ru', 'Свой тариф')->exists())->toBeTrue();
});
