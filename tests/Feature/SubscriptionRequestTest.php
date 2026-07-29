<?php

use App\Models\Establishment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\SubscriptionService;

it('lets an owner file a request for one of their menus', function () {
    $user = User::factory()->create();
    $venue = Establishment::factory()->for($user)->create();
    $plan = Plan::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', [
            'establishment_id' => $venue->id,
            'plan_id' => $plan->id,
            'contact_phone' => '+7 701 111 22 33',
            'note' => 'Хочу премиум',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.plan.id', $plan->id)
        ->assertJsonPath('data.establishment.id', $venue->id);

    expect($user->subscriptionRequests()->count())->toBe(1);
});

it('rejects a request for an inactive plan', function () {
    $user = User::factory()->create();
    $venue = Establishment::factory()->for($user)->create();
    $plan = Plan::factory()->inactive()->create();

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', [
            'establishment_id' => $venue->id,
            'plan_id' => $plan->id,
        ])
        ->assertStatus(422);
});

it('rejects a request for a menu the owner does not own', function () {
    $user = User::factory()->create();
    $foreign = Establishment::factory()->create(); // someone else's menu
    $plan = Plan::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', [
            'establishment_id' => $foreign->id,
            'plan_id' => $plan->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('establishment_id');
});

it('blocks a second pending request for the same menu', function () {
    $user = User::factory()->create();
    $venue = Establishment::factory()->for($user)->create();
    $plan = Plan::factory()->create();
    SubscriptionRequest::factory()->for($user)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $plan->id,
    ]);

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', [
            'establishment_id' => $venue->id,
            'plan_id' => $plan->id,
        ])
        ->assertStatus(409);
});

it('allows a pending request on a different menu', function () {
    $user = User::factory()->create();
    $first = Establishment::factory()->for($user)->create();
    $second = Establishment::factory()->for($user)->create();
    $plan = Plan::factory()->create();

    // A live request on the first menu must not block the second.
    SubscriptionRequest::factory()->for($user)->create([
        'establishment_id' => $first->id,
        'plan_id' => $plan->id,
    ]);

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', [
            'establishment_id' => $second->id,
            'plan_id' => $plan->id,
        ])
        ->assertCreated();
});

it('reports every menu on its own trial for a free-tier owner', function () {
    $user = User::factory()->create();
    $venue = Establishment::factory()->for($user)->create();

    $menu = collect(
        $this->actingAs($user)
            ->getJson('/api/subscription')
            ->assertOk()
            ->json('menus')
    )->firstWhere('id', $venue->id);

    expect($menu)->not->toBeNull()
        ->and($menu['access_source'])->toBe('trial')
        ->and($menu['subscription'])->toBeNull()
        ->and($menu['pending_request'])->toBeNull();
});

it('shows a menu\'s active subscription and pending request', function () {
    $user = User::factory()->create();
    $venue = Establishment::factory()->for($user)->create();
    $plan = Plan::factory()->create();
    $request = SubscriptionRequest::factory()->for($user)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $plan->id,
    ]);

    SubscriptionService::approve($request);

    // A fresh pending request on top of the active grant, same menu.
    $other = Plan::factory()->create();
    SubscriptionRequest::factory()->for($user)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $other->id,
    ]);

    $menu = collect(
        $this->actingAs($user)
            ->getJson('/api/subscription')
            ->assertOk()
            ->json('menus')
    )->firstWhere('id', $venue->id);

    expect($menu['subscription']['status'])->toBe('active')
        ->and($menu['subscription']['plan']['id'])->toBe($plan->id)
        ->and($menu['pending_request']['plan']['id'])->toBe($other->id);
});

it('approving activates a menu subscription and closes its previous one', function () {
    $user = User::factory()->create();
    $venue = Establishment::factory()->for($user)->create();
    $first = Plan::factory()->create();
    $second = Plan::factory()->create();

    $r1 = SubscriptionRequest::factory()->for($user)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $first->id,
    ]);
    SubscriptionService::approve($r1);

    $r2 = SubscriptionRequest::factory()->for($user)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $second->id,
    ]);
    SubscriptionService::approve($r2);

    // Exactly one active grant on this menu, on the newest plan.
    $active = $venue->subscriptions()->where('status', Subscription::STATUS_ACTIVE)->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->plan_id)->toBe($second->id)
        ->and($r2->fresh()->status)->toBe('approved');
});

it('keeps a second menu\'s grant when another menu is re-subscribed', function () {
    $user = User::factory()->create();
    $one = Establishment::factory()->for($user)->create();
    $two = Establishment::factory()->for($user)->create();
    $plan = Plan::factory()->create();

    $rOne = SubscriptionRequest::factory()->for($user)->create([
        'establishment_id' => $one->id,
        'plan_id' => $plan->id,
    ]);
    SubscriptionService::approve($rOne);

    // Subscribing the second menu must not retire the first menu's grant.
    $rTwo = SubscriptionRequest::factory()->for($user)->create([
        'establishment_id' => $two->id,
        'plan_id' => $plan->id,
    ]);
    SubscriptionService::approve($rTwo);

    expect($one->subscriptions()->where('status', Subscription::STATUS_ACTIVE)->count())->toBe(1)
        ->and($two->subscriptions()->where('status', Subscription::STATUS_ACTIVE)->count())->toBe(1);
});

it('rejecting marks the request and leaves subscriptions untouched', function () {
    $user = User::factory()->create();
    $venue = Establishment::factory()->for($user)->create();
    $plan = Plan::factory()->create();
    $request = SubscriptionRequest::factory()->for($user)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $plan->id,
    ]);

    SubscriptionService::reject($request);

    expect($request->fresh()->status)->toBe('rejected')
        ->and($venue->subscriptions()->count())->toBe(0);
});

it('does not let the owner set status through the request payload', function () {
    $user = User::factory()->create();
    $venue = Establishment::factory()->for($user)->create();
    $plan = Plan::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/subscription-requests', [
            'establishment_id' => $venue->id,
            'plan_id' => $plan->id,
            'status' => 'approved', // must be ignored
        ])
        ->assertCreated();

    expect($user->subscriptionRequests()->first()->status)->toBe('new');
});
