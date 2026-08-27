<?php

use App\Models\Establishment;
use App\Models\Plan;
use App\Models\ReferralReward;
use App\Models\ReferralSetting;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\SubscriptionService;

/** Register a fresh owner, optionally through an invite code. */
function register(array $overrides = []): void
{
    test()->postJson('/api/register', [
        'name' => 'Owner',
        'email' => 'owner-'.fake()->unique()->numberBetween(1, 999999).'@vostok.kz',
        'password' => 'ploV-2490-tashkent',
        'password_confirmation' => 'ploV-2490-tashkent',
        ...$overrides,
    ])->assertCreated();
}

it('mints a unique invite code for every new owner', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    expect($a->referral_code)->not->toBeNull()->toHaveLength(6)
        ->and($b->referral_code)->not->toBe($a->referral_code);
});

it('grants the invited owner their welcome discount at registration', function () {
    $inviter = User::factory()->create();

    register(['email' => 'invited@vostok.kz', 'referral_code' => $inviter->referral_code]);

    $invited = User::firstWhere('email', 'invited@vostok.kz');

    expect($invited->referred_by_id)->toBe($inviter->id)
        // Default invited reward: 5 000 ₸.
        ->and($invited->referral_credit)->toBe(500_000);

    $this->assertDatabaseHas('referral_rewards', [
        'referred_id' => $invited->id,
        'kind' => ReferralReward::KIND_SIGNUP,
        'amount' => 500_000,
    ]);
});

it('accepts the invite code case-insensitively', function () {
    $inviter = User::factory()->create();

    register([
        'email' => 'lower@vostok.kz',
        'referral_code' => strtolower($inviter->referral_code),
    ]);

    expect(User::firstWhere('email', 'lower@vostok.kz')->referred_by_id)->toBe($inviter->id);
});

it('ignores an unknown invite code without blocking signup', function () {
    register(['email' => 'nocode@vostok.kz', 'referral_code' => 'ZZZZZZ']);

    $user = User::firstWhere('email', 'nocode@vostok.kz');

    expect($user)->not->toBeNull()
        ->and($user->referred_by_id)->toBeNull()
        ->and($user->referral_credit)->toBe(0);
});

it('does not let referred_by_id be mass-assigned from the register payload', function () {
    $someone = User::factory()->create();

    register(['email' => 'sneaky@vostok.kz', 'referred_by_id' => $someone->id]);

    expect(User::firstWhere('email', 'sneaky@vostok.kz')->referred_by_id)->toBeNull();
});

it('rewards the inviter when their referral first pays for a paid plan', function () {
    $inviter = User::factory()->create();
    register(['email' => 'buyer@vostok.kz', 'referral_code' => $inviter->referral_code]);
    $buyer = User::firstWhere('email', 'buyer@vostok.kz');

    $venue = Establishment::factory()->for($buyer)->create(['slug' => 'buyer-menu']);
    $plan = Plan::factory()->create(['price' => 25_000 * 100, 'period' => 'year']);

    $request = SubscriptionRequest::factory()->for($buyer)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $plan->id,
    ]);
    SubscriptionService::approve($request);

    // Default inviter reward: 10 000 ₸.
    expect($inviter->refresh()->referral_credit)->toBe(1_000_000);

    $this->assertDatabaseHas('referral_rewards', [
        'referrer_id' => $inviter->id,
        'referred_id' => $buyer->id,
        'kind' => ReferralReward::KIND_CONVERSION,
        'amount' => 1_000_000,
    ]);
});

it('rewards the inviter only once, not on a second paid menu', function () {
    $inviter = User::factory()->create();
    register(['email' => 'buyer2@vostok.kz', 'referral_code' => $inviter->referral_code]);
    $buyer = User::firstWhere('email', 'buyer2@vostok.kz');

    $plan = Plan::factory()->create(['price' => 25_000 * 100, 'period' => 'year']);

    foreach (['menu-a', 'menu-b'] as $slug) {
        $venue = Establishment::factory()->for($buyer)->create(['slug' => $slug]);
        $request = SubscriptionRequest::factory()->for($buyer)->create([
            'establishment_id' => $venue->id,
            'plan_id' => $plan->id,
        ]);
        SubscriptionService::approve($request);
    }

    expect($inviter->refresh()->referral_credit)->toBe(1_000_000)
        ->and(ReferralReward::where('kind', ReferralReward::KIND_CONVERSION)
            ->where('referred_id', $buyer->id)->count())->toBe(1);
});

it('does not reward the inviter for a free plan', function () {
    $inviter = User::factory()->create();
    register(['email' => 'freeloader@vostok.kz', 'referral_code' => $inviter->referral_code]);
    $buyer = User::firstWhere('email', 'freeloader@vostok.kz');

    $venue = Establishment::factory()->for($buyer)->create(['slug' => 'free-menu']);
    $free = Plan::factory()->create(['price' => 0, 'period' => 'month']);

    $request = SubscriptionRequest::factory()->for($buyer)->create([
        'establishment_id' => $venue->id,
        'plan_id' => $free->id,
    ]);
    SubscriptionService::approve($request);

    expect($inviter->refresh()->referral_credit)->toBe(0);
    $this->assertDatabaseMissing('referral_rewards', [
        'kind' => ReferralReward::KIND_CONVERSION,
        'referred_id' => $buyer->id,
    ]);
});

it('applies the wallet to a request and consumes it at approval', function () {
    $owner = User::factory()->create();
    $owner->forceFill(['referral_credit' => 500_000])->save();

    $venue = Establishment::factory()->for($owner)->create(['slug' => 'discounted']);
    $plan = Plan::factory()->create(['price' => 25_000 * 100, 'period' => 'year']);

    $this->actingAs($owner)
        ->postJson('/api/subscription-requests', [
            'establishment_id' => $venue->id,
            'plan_id' => $plan->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.referral_credit_applied', 500_000);

    $request = SubscriptionRequest::firstWhere('establishment_id', $venue->id);
    $subscription = SubscriptionService::approve($request);

    expect($subscription->referral_credit_applied)->toBe(500_000)
        // Wallet spent down after approval.
        ->and($owner->refresh()->referral_credit)->toBe(0);
});

it('caps the applied wallet at the plan price', function () {
    $owner = User::factory()->create();
    // A wallet far bigger than a cheap plan.
    $owner->forceFill(['referral_credit' => 5_000_000])->save();

    $venue = Establishment::factory()->for($owner)->create(['slug' => 'cheap']);
    $plan = Plan::factory()->create(['price' => 10_000 * 100, 'discount_percent' => 0, 'period' => 'month']);

    $this->actingAs($owner)
        ->postJson('/api/subscription-requests', [
            'establishment_id' => $venue->id,
            'plan_id' => $plan->id,
        ])
        ->assertCreated()
        // Never discount more than the price.
        ->assertJsonPath('data.referral_credit_applied', 10_000 * 100);
});

it('exposes the referral summary to the cabinet', function () {
    $inviter = User::factory()->create();
    $friend = User::factory()->create();
    $friend->forceFill(['referred_by_id' => $inviter->id])->save();

    $data = $this->actingAs($inviter)->getJson('/api/referral')->assertOk()->json();

    expect($data['code'])->toBe($inviter->referral_code)
        ->and($data['invited_count'])->toBe(1)
        ->and($data['converted_count'])->toBe(0)
        ->and($data['referred_reward'])->toBe(500_000)
        ->and($data['referrer_reward'])->toBe(1_000_000);

    // The invited owner sees who brought them in.
    $this->actingAs($friend)->getJson('/api/referral')
        ->assertOk()
        ->assertJsonPath('referred_by', $inviter->name);
});

it('honours the master switch and stops granting when disabled', function () {
    ReferralSetting::current()->forceFill(['enabled' => false])->save();

    $inviter = User::factory()->create();
    register(['email' => 'noreward@vostok.kz', 'referral_code' => $inviter->referral_code]);

    $invited = User::firstWhere('email', 'noreward@vostok.kz');

    // Still linked, but no discount while the program is off.
    expect($invited->referred_by_id)->toBe($inviter->id)
        ->and($invited->referral_credit)->toBe(0);
});
