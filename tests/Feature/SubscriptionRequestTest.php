<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\SubscriptionService;

it('lets an owner file a request for an active plan', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', [
            'plan_id' => $plan->id,
            'contact_phone' => '+7 701 111 22 33',
            'note' => 'Хочу премиум',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.plan.id', $plan->id);

    expect($user->subscriptionRequests()->count())->toBe(1);
});

it('rejects a request for an inactive plan', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->inactive()->create();

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', ['plan_id' => $plan->id])
        ->assertStatus(422);
});

it('blocks a second pending request', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    SubscriptionRequest::factory()->for($user)->create(['plan_id' => $plan->id]);

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', ['plan_id' => $plan->id])
        ->assertStatus(409);
});

it('reports no subscription for a free-tier owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/subscription')
        ->assertOk()
        ->assertJsonPath('subscription', null)
        ->assertJsonPath('pending_request', null);
});

it('shows the active subscription and pending request', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    $request = SubscriptionRequest::factory()->for($user)->create(['plan_id' => $plan->id]);

    SubscriptionService::approve($request);

    // A fresh pending request on top of the active grant.
    $other = Plan::factory()->create();
    SubscriptionRequest::factory()->for($user)->create(['plan_id' => $other->id]);

    $this->actingAs($user)
        ->getJson('/api/subscription')
        ->assertOk()
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('subscription.plan.id', $plan->id)
        ->assertJsonPath('pending_request.plan.id', $other->id);
});

it('approving activates a subscription and closes the previous one', function () {
    $user = User::factory()->create();
    $first = Plan::factory()->create();
    $second = Plan::factory()->create();

    $r1 = SubscriptionRequest::factory()->for($user)->create(['plan_id' => $first->id]);
    SubscriptionService::approve($r1);

    $r2 = SubscriptionRequest::factory()->for($user)->create(['plan_id' => $second->id]);
    SubscriptionService::approve($r2);

    // Exactly one active grant, on the newest plan.
    $active = $user->subscriptions()->where('status', Subscription::STATUS_ACTIVE)->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->plan_id)->toBe($second->id)
        ->and($r2->fresh()->status)->toBe('approved');
});

it('rejecting marks the request and leaves subscriptions untouched', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    $request = SubscriptionRequest::factory()->for($user)->create(['plan_id' => $plan->id]);

    SubscriptionService::reject($request);

    expect($request->fresh()->status)->toBe('rejected')
        ->and($user->subscriptions()->count())->toBe(0);
});

it('does not let the owner set status through the request payload', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', [
            'plan_id' => $plan->id,
            'status' => 'approved', // must be ignored
        ])
        ->assertCreated();

    expect($user->subscriptionRequests()->first()->status)->toBe('new');
});
