<?php

namespace App\Http\Resources;

use App\Models\Establishment;
use App\Support\VenueImage;
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
            'layout' => $this->layout,
            // Public URLs built from the stored path (null when unset).
            'cover_url' => VenueImage::url($this->cover_path),
            'logo_url' => VenueImage::url($this->logo_path),
            'show_logo' => (bool) $this->show_logo,
            // Whether a Telegram chat is bound (enables waiter calls). The chat
            // id and link token are never exposed — only this boolean.
            'telegram_connected' => $this->telegramConnected(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Availability window: when the guest menu stops working, whether it
            // already has, days remaining, and what governs it (trial vs plan).
            'access_ends_at' => $this->accessEndsAt()?->toIso8601String(),
            'access_source' => $this->accessSource(),
            'is_expired' => $this->isExpired(),
            'days_left' => $this->daysLeft(),
            // Content caps from the effective plan (free tier on trial). The menu
            // editor dims "Add" at the cap; the server enforces it regardless.
            'menu_limits' => $this->menuLimits(),
        ];
    }
}
