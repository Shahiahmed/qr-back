<?php

use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Plan;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\PublicMenu;
use App\Support\SubscriptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

it('stamps a one-week trial on a new venue', function () {
    $venue = Establishment::factory()->create();

    expect($venue->trial_ends_at)->not->toBeNull()
        ->and($venue->trial_ends_at->isFuture())->toBeTrue()
        // A week out, to the day — stamped at creation via Establishment::TRIAL_DAYS.
        ->and($venue->trial_ends_at->toDateString())
        ->toBe(Carbon::now()->addDays(Establishment::TRIAL_DAYS)->toDateString());
});

it('exposes the trial window on the establishment resource', function () {
    $owner = User::factory()->create();
    Establishment::factory()->for($owner)->create(['slug' => 'trialed']);

    $data = $this->actingAs($owner)
        ->getJson('/api/establishments')
        ->assertOk()
        ->json('data.0');

    expect($data['access_source'])->toBe('trial')
        ->and($data['is_expired'])->toBeFalse()
        ->and($data['days_left'])->toBeGreaterThanOrEqual(6)
        ->and($data['access_ends_at'])->not->toBeNull();
});

it('lets an active subscription override the per-menu trial', function () {
    $owner = User::factory()->create();
    $venue = Establishment::factory()->for($owner)->create(['slug' => 'subbed']);

    // Year plan → the window should stretch far past the 7-day trial.
    $plan = Plan::factory()->create(['period' => 'year']);
    $request = SubscriptionRequest::factory()->for($owner)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $plan->id,
    ]);
    SubscriptionService::approve($request);

    $venue->refresh()->load('currentSubscription');

    expect($venue->accessSource())->toBe('subscription')
        ->and($venue->daysLeft())->toBeGreaterThan(300);
});

it('does not extend a second menu when only the first is subscribed', function () {
    // The bug this feature fixes: a per-menu grant must not leak to sibling menus.
    $owner = User::factory()->create();
    $subscribed = Establishment::factory()->for($owner)->create(['slug' => 'paid-menu']);
    $sibling = Establishment::factory()->for($owner)->create(['slug' => 'free-menu']);

    $plan = Plan::factory()->create(['period' => 'year']);
    $request = SubscriptionRequest::factory()->for($owner)->create([
        'establishment_id' => $subscribed->id,
        'plan_id' => $plan->id,
    ]);
    SubscriptionService::approve($request);

    $subscribed->refresh()->load('currentSubscription');
    $sibling->refresh()->load('currentSubscription');

    // Paid menu → a year; the sibling stays on its own 7-day trial.
    expect($subscribed->accessSource())->toBe('subscription')
        ->and($subscribed->daysLeft())->toBeGreaterThan(300)
        ->and($sibling->accessSource())->toBe('trial')
        ->and($sibling->daysLeft())->toBeLessThanOrEqual(7);
});

it('sets the subscription window by plan period', function () {
    $owner = User::factory()->create();
    $monthly = Plan::factory()->create(['period' => 'month']);

    $request = SubscriptionRequest::factory()->for($owner)->create(['plan_id' => $monthly->id]);
    $subscription = SubscriptionService::approve($request);

    expect($subscription->ends_at->toDateString())
        ->toBe(Carbon::now()->addMonth()->toDateString());

    $yearly = Plan::factory()->create(['period' => 'year']);
    $request2 = SubscriptionRequest::factory()->for($owner)->create(['plan_id' => $yearly->id]);
    $subscription2 = SubscriptionService::approve($request2);

    expect($subscription2->ends_at->toDateString())
        ->toBe(Carbon::now()->addYear()->toDateString());
});

it('serves a guest menu while the trial is live', function () {
    $venue = Establishment::factory()->create(['slug' => 'live-venue']);
    MenuCategory::factory()->for($venue)->create(['name_ru' => 'Горячее']);

    $this->getJson('/api/public/menu/live-venue')->assertOk();
});

it('hides an expired guest menu behind a 404', function () {
    $venue = Establishment::factory()->create([
        'slug' => 'lapsed-venue',
        'trial_ends_at' => Carbon::now()->subDay(),
    ]);
    MenuCategory::factory()->for($venue)->create(['name_ru' => 'Горячее']);

    // An expired window must 404 for guests even if the menu itself has data.
    $this->getJson('/api/public/menu/lapsed-venue')->assertNotFound();
});

it('treats a grandfathered venue (no trial) as unlimited', function () {
    // Older venues predate trials: null window means no limit, keep serving.
    $venue = Establishment::factory()->create(['slug' => 'legacy-venue']);
    $venue->forceFill(['trial_ends_at' => null])->save();
    MenuCategory::factory()->for($venue)->create(['name_ru' => 'Горячее']);

    $venue->refresh();

    expect($venue->accessSource())->toBeNull()
        ->and($venue->isExpired())->toBeFalse()
        ->and($venue->daysLeft())->toBeNull();

    $this->getJson('/api/public/menu/legacy-venue')->assertOk();
});

it('drops the owner\'s menu caches when a subscription is approved', function () {
    $owner = User::factory()->create();
    // Already expired on the trial — cached now, it should 404.
    $venue = Establishment::factory()->for($owner)->create([
        'slug' => 'revived-venue',
        'trial_ends_at' => Carbon::now()->subDay(),
    ]);
    MenuCategory::factory()->for($venue)->create(['name_ru' => 'Горячее']);

    // The first hit builds and caches the payload (with its now-past window),
    // then 404s. If approval did not invalidate that cache, the stale window
    // would keep 404-ing below — so an OK response proves the drop happened.
    $this->getJson('/api/public/menu/revived-venue')->assertNotFound();
    expect(Cache::has(PublicMenu::cacheKey('revived-venue')))->toBeTrue();

    $plan = Plan::factory()->create(['period' => 'year']);
    $request = SubscriptionRequest::factory()->for($owner)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $plan->id,
    ]);
    SubscriptionService::approve($request);

    // The extended window takes effect immediately, not after the day-long cache.
    $this->getJson('/api/public/menu/revived-venue')->assertOk();
});
