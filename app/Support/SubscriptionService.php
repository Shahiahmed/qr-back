<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The trusted approval flow. Turning a request into an active subscription
 * touches three rows (request, old subscription, new subscription), so it runs
 * in a transaction and lives in one place — the admin panel and any future
 * payment webhook both call this rather than reimplementing it.
 */
class SubscriptionService
{
    /**
     * Approve a request: activate a fresh subscription for its owner and close
     * whatever they had before. Returns the new subscription.
     */
    public static function approve(SubscriptionRequest $request, ?User $reviewer = null): Subscription
    {
        return DB::transaction(function () use ($request, $reviewer) {
            $user = $request->user;
            $plan = $request->plan;

            // One active grant per owner: retire the previous one first.
            $user->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_CANCELLED]);

            $starts = Carbon::now();
            $ends = $plan?->period === 'year'
                ? $starts->copy()->addYear()
                : $starts->copy()->addMonth();

            // forceCreate / forceFill: Subscription and the review columns are
            // intentionally not fillable (never writable from HTTP), but this is
            // the trusted path that owns them.
            $subscription = $user->subscriptions()->forceCreate([
                'plan_id' => $plan?->id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'request_id' => $request->id,
            ]);

            $request->forceFill([
                'status' => SubscriptionRequest::STATUS_APPROVED,
                'reviewed_at' => $starts,
                'reviewed_by' => $reviewer?->id,
            ])->save();

            /*
             * The new grant lengthens every one of this owner's menu windows.
             * That window is baked into the cached guest payload, so drop those
             * caches now — otherwise an expired menu would stay dark until its
             * day-long cache lapsed on its own.
             */
            $user->establishments()->pluck('slug')
                ->each(fn (string $slug) => PublicMenu::forget($slug));

            return $subscription;
        });
    }

    /** Reject a request. Leaves any existing subscription untouched. */
    public static function reject(SubscriptionRequest $request, ?User $reviewer = null): void
    {
        $request->forceFill([
            'status' => SubscriptionRequest::STATUS_REJECTED,
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => $reviewer?->id,
        ])->save();
    }
}
