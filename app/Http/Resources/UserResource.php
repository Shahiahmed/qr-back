<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            // Referral wallet: the owner's own invite code and spendable balance
            // (tiyn). The cabinet builds the share link and shows the discount.
            'referral_code' => $this->referral_code,
            'referral_credit' => (int) $this->referral_credit,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
