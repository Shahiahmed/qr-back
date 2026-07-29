<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An owner's request for a plan. Reviewed by an admin; approval activates a
 * Subscription. Contact/note are owner-supplied; status/reviewed_* are set by
 * the admin flow, so they are deliberately kept out of #[Fillable].
 */
#[Fillable(['establishment_id', 'plan_id', 'contact_phone', 'note'])]
class SubscriptionRequest extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionRequestFactory> */
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** A request still awaiting a decision. */
    public const PENDING = [self::STATUS_NEW];

    // Mirror the DB default so a freshly created model reports its status
    // without a round-trip refresh.
    protected $attributes = [
        'status' => self::STATUS_NEW,
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The menu this request asks to cover — subscriptions are per menu. */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
