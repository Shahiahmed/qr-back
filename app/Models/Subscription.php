<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An active or historical subscription grant, attached to the owner account.
 * Written only by the trusted approval flow (SubscriptionService), so nothing
 * here is mass-assignable from HTTP.
 */
#[Fillable([])]
class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** Active and not past its end date. */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && (! $this->ends_at || $this->ends_at->isFuture());
    }

    /** Whole days until the grant ends; 0 if past, null if open-ended. */
    public function daysLeft(): ?int
    {
        if (! $this->ends_at) {
            return null;
        }

        if ($this->ends_at->isPast()) {
            return 0;
        }

        return (int) ceil(Carbon::now()->diffInHours($this->ends_at) / 24);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class, 'request_id');
    }
}
