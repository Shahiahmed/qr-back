<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Support\PublicPlans;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            // Full plan card so the cabinet can show what the owner is on.
            'plan' => $this->plan ? PublicPlans::present($this->plan) : null,
        ];
    }
}
