<?php

namespace Database\Factories;

use App\Models\Establishment;
use App\Models\Plan;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionRequest>
 */
class SubscriptionRequestFactory extends Factory
{
    protected $model = SubscriptionRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // A standalone menu by default; tests that assert on ownership pass
            // an establishment belonging to the request's own user.
            'establishment_id' => Establishment::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionRequest::STATUS_NEW,
            'contact_phone' => '+7 700 000 00 00',
            'note' => null,
        ];
    }
}
