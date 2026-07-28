<?php

namespace App\Http\Resources;

use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Establishment
 */
class EstablishmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'currency' => $this->currency,
            'default_locale' => $this->default_locale,
            'address' => $this->address,
            'phone' => $this->phone,
            // Guest-facing header + colour theme.
            'wifi_ssid' => $this->wifi_ssid,
            'wifi_password' => $this->wifi_password,
            'instagram_url' => $this->instagram_url,
            'facebook_url' => $this->facebook_url,
            'tiktok_url' => $this->tiktok_url,
            'theme' => $this->theme,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
