<?php

use App\Filament\Resources\SubscriptionRequests\Pages\ListSubscriptionRequests;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * The moderation flow lives in the Filament table actions; these drive them the
 * way the admin does, verifying they route through SubscriptionService.
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);
});

it('approves a pending request from the panel and activates a subscription', function () {
    $owner = User::factory()->create();
    $plan = Plan::factory()->create();
    $request = SubscriptionRequest::factory()->for($owner)->create(['plan_id' => $plan->id]);

    Livewire::test(ListSubscriptionRequests::class)
        ->callAction(TestAction::make('approve')->table($request));

    expect($request->fresh()->status)->toBe(SubscriptionRequest::STATUS_APPROVED)
        ->and($request->fresh()->reviewed_by)->toBe($this->admin->id)
        ->and($owner->subscriptions()->where('status', Subscription::STATUS_ACTIVE)->count())->toBe(1);
});

it('rejects a pending request from the panel without granting a subscription', function () {
    $owner = User::factory()->create();
    $plan = Plan::factory()->create();
    $request = SubscriptionRequest::factory()->for($owner)->create(['plan_id' => $plan->id]);

    Livewire::test(ListSubscriptionRequests::class)
        ->callAction(TestAction::make('reject')->table($request));

    expect($request->fresh()->status)->toBe(SubscriptionRequest::STATUS_REJECTED)
        ->and($owner->subscriptions()->count())->toBe(0);
});

it('hides approve and reject once a request is decided', function () {
    $owner = User::factory()->create();
    $request = SubscriptionRequest::factory()->for($owner)->create([
        'plan_id' => Plan::factory()->create()->id,
        'status' => SubscriptionRequest::STATUS_APPROVED,
    ]);

    Livewire::test(ListSubscriptionRequests::class)
        // The list defaults to the "new" filter; switch to approved so the row shows.
        ->filterTable('status', SubscriptionRequest::STATUS_APPROVED)
        ->assertActionHidden(TestAction::make('approve')->table($request))
        ->assertActionHidden(TestAction::make('reject')->table($request));
});
