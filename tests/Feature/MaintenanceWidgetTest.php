<?php

use App\Filament\Widgets\MaintenanceCommands;
use App\Models\Plan;
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

it('exposes four health checks and offers seeding only on an empty catalogue', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $widget = new MaintenanceCommands();

    expect($widget->getChecks())->toHaveCount(4)
        ->and($widget->canSeedPlans())->toBeTrue();

    Plan::factory()->create();

    expect($widget->canSeedPlans())->toBeFalse();
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

it('skips plan seeding when plans already exist', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Plan::factory()->create();

    Livewire::test(MaintenanceCommands::class)
        ->call('runSeedPlans')
        ->assertOk();

    // The guard kept the catalogue at exactly the one pre-existing plan.
    expect(Plan::query()->count())->toBe(1);
});
