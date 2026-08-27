<?php

namespace App\Support;

use App\Models\ReferralReward;
use App\Models\ReferralSetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The trusted referral logic in one place. The wallet (`users.referral_credit`)
 * and the ledger (`referral_rewards`) are never mass-assignable — only this
 * service writes them, from registration and the approval flow.
 *
 * Idempotency lives in the ledger's unique (referred_id, kind): each new user
 * grants at most one signup reward and triggers at most one conversion reward,
 * however often these methods run.
 */
class ReferralService
{
    /**
     * Bind an inviter to a freshly registered owner and grant that owner their
     * welcome discount. Safe to call with a stale/self code — it no-ops.
     */
    public static function attachReferrer(User $referred, User $referrer): void
    {
        // A user cannot invite themselves, and an inviter is set once.
        if ($referred->id === $referrer->id || $referred->referred_by_id !== null) {
            return;
        }

        $referred->forceFill(['referred_by_id' => $referrer->id])->save();

        $settings = ReferralSetting::current();

        if (! $settings->enabled || $settings->referred_reward <= 0) {
            return;
        }

        self::award(
            referredId: $referred->id,
            referrerId: $referrer->id,
            kind: ReferralReward::KIND_SIGNUP,
            amount: $settings->referred_reward,
            beneficiaryId: $referred->id,
            subscriptionId: null,
        );
    }

    /**
     * Reward the inviter once their referral first pays for a real plan. A
     * free/trial grant earns nothing; a second paid menu no longer re-triggers.
     */
    public static function grantConversionReward(Subscription $subscription): void
    {
        $referred = $subscription->user;
        $referrerId = $referred?->referred_by_id;

        if (! $referrerId) {
            return;
        }

        // Only a paid plan converts — the whole point is a real sale.
        if ((int) ($subscription->plan?->price ?? 0) <= 0) {
            return;
        }

        $settings = ReferralSetting::current();

        if (! $settings->enabled || $settings->referrer_reward <= 0) {
            return;
        }

        self::award(
            referredId: $referred->id,
            referrerId: $referrerId,
            kind: ReferralReward::KIND_CONVERSION,
            amount: $settings->referrer_reward,
            beneficiaryId: $referrerId,
            subscriptionId: $subscription->id,
        );
    }

    /**
     * Spend up to `$requested` tiyn of a user's wallet, clamped to their live
     * balance so two over-committed pending requests can never overdraw.
     * Returns what was actually applied.
     */
    public static function consume(User $user, int $requested): int
    {
        $applied = max(0, min($requested, (int) $user->referral_credit));

        if ($applied > 0) {
            $user->forceFill(['referral_credit' => $user->referral_credit - $applied])->save();
        }

        return $applied;
    }

    /**
     * Write one ledger row (idempotent on referred_id + kind) and credit the
     * beneficiary's wallet only when the row was newly created — so a repeat call
     * neither duplicates the ledger nor double-credits the wallet.
     */
    private static function award(
        int $referredId,
        int $referrerId,
        string $kind,
        int $amount,
        int $beneficiaryId,
        ?int $subscriptionId,
    ): void {
        DB::transaction(function () use (
            $referredId, $referrerId, $kind, $amount, $beneficiaryId, $subscriptionId
        ) {
            // Idempotent: the ledger's unique (referred_id, kind) means a second
            // attempt finds the row and stops before crediting anything again.
            $exists = ReferralReward::query()
                ->where('referred_id', $referredId)
                ->where('kind', $kind)
                ->exists();

            if ($exists) {
                return;
            }

            // forceCreate: the ledger is intentionally not mass-assignable.
            ReferralReward::query()->forceCreate([
                'referred_id' => $referredId,
                'referrer_id' => $referrerId,
                'subscription_id' => $subscriptionId,
                'kind' => $kind,
                'amount' => $amount,
            ]);

            $beneficiary = User::find($beneficiaryId);

            if ($beneficiary) {
                $beneficiary->forceFill([
                    'referral_credit' => $beneficiary->referral_credit + $amount,
                ])->save();
            }
        });
    }
}
