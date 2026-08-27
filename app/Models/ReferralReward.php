<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One granted referral reward — an append-only ledger row for audit and
 * idempotency. Written only by the trusted ReferralService (registration and
 * the approval flow), so nothing here is mass-assignable from HTTP.
 *
 * `kind`:
 *  - 'signup'     the invited owner's welcome discount (beneficiary = referred)
 *  - 'conversion' the inviter's reward for a paid referral (beneficiary = referrer)
 */
#[Fillable([])]
class ReferralReward extends Model
{
    /** @use HasFactory<\Database\Factories\ReferralRewardFactory> */
    use HasFactory;

    public const KIND_SIGNUP = 'signup';
    public const KIND_CONVERSION = 'conversion';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /** The owner who sent the invite. */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /** The owner who signed up through the invite. */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
