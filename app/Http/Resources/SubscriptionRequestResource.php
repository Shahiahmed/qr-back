<?php

namespace App\Http\Resources;

use App\Models\SubscriptionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionRequest
 */
class SubscriptionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'contact_phone' => $this->contact_phone,
            'note' => $this->note,
            'plan' => $this->whenLoaded('plan', fn () => [
                'id' => $this->plan?->id,
                'name_ru' => $this->plan?->name_ru,
                'name_kk' => $this->plan?->name_kk,
            ]),
            'establishment' => $this->whenLoaded('establishment', fn () => [
                'id' => $this->establishment?->id,
                'name' => $this->establishment?->name,
                'slug' => $this->establishment?->slug,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
