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
            $establishment = $request->establishment;

            // One active grant per MENU: retire the previous one for this menu.
            // (`= null` matches nothing, so a legacy request without a menu
            // simply retires nothing.)
            Subscription::query()
                ->where('establishment_id', $establishment?->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_CANCELLED]);

            $starts = Carbon::now();
            $ends = match ($plan?->period) {
                'year' => $starts->copy()->addYear(),
                'halfyear' => $starts->copy()->addMonths(6),
                default => $starts->copy()->addMonth(),
            };

            // forceCreate / forceFill: Subscription and the review columns are
            // intentionally not fillable (never writable from HTTP), but this is
            // the trusted path that owns them.
            $subscription = $user->subscriptions()->forceCreate([
                'establishment_id' => $establishment?->id,
                'plan_id' => $plan?->id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'request_id' => $request->id,
            ]);

            // Spend whatever discount the owner committed on this request, clamped
            // to their live wallet, and record what actually came off the price.
            $applied = ReferralService::consume($user, (int) $request->referral_credit_applied);
            if ($applied > 0) {
                $subscription->forceFill(['referral_credit_applied' => $applied])->save();
            }

            $request->forceFill([
                'status' => SubscriptionRequest::STATUS_APPROVED,
                'reviewed_at' => $starts,
                'reviewed_by' => $reviewer?->id,
            ])->save();

            // A real (paid) approval is the moment the inviter earns their reward.
            ReferralService::grantConversionReward($subscription);

            /*
             * The new grant lengthens exactly this one menu's window, which is
             * baked into its cached guest payload — drop that cache now, else an
             * expired menu would stay dark until its day-long cache lapsed.
             */
            if ($establishment) {
                PublicMenu::forget($establishment->slug);
            }

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
