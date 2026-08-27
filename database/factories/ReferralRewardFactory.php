<?php

namespace Database\Factories;

use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralReward>
 */
class ReferralRewardFactory extends Factory
{
    protected $model = ReferralReward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referrer_id' => User::factory(),
            'referred_id' => User::factory(),
            'subscription_id' => null,
            'kind' => ReferralReward::KIND_SIGNUP,
            'amount' => 500_000,
        ];
    }
}
