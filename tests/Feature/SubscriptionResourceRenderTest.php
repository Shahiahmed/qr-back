<?php

use App\Filament\Resources\Establishments\Pages\ListEstablishments;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Models\Establishment;
use App\Models\Plan;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\SubscriptionService;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * These pages read model attributes that are not DB columns (accessEndsAt,
 * daysLeft, isActive). Filament's summary/query pipeline is picky about that
 * (see CLAUDE.md §7 on modifyQueryUsing), so a plain render is the cheap guard.
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);
});

it('renders the subscriptions list with an active grant', function () {
    $owner = User::factory()->create();
    $plan = Plan::factory()->create();
    $request = SubscriptionRequest::factory()->for($owner)->create(['plan_id' => $plan->id]);
    SubscriptionService::approve($request);

    Livewire::test(ListSubscriptions::class)->assertOk();
});

it('renders the establishments list with the access column', function () {
    $owner = User::factory()->create();
    Establishment::factory()->for($owner)->create(['slug' => 'render-check']);

    Livewire::test(ListEstablishments::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Establishment::all());
});
