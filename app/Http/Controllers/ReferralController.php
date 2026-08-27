<?php

namespace App\Http\Controllers;

use App\Models\ReferralReward;
use App\Models\ReferralSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * Everything the cabinet's "Referral program" panel needs: the owner's own
     * code and wallet, who invited them, and how their invites are doing. All
     * amounts are in tiyn (1/100 ₸).
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // Backfill a code for owners who registered before the referral feature
        // shipped — their `creating` hook never minted one. Idempotent.
        $user->ensureReferralCode();

        $settings = ReferralSetting::current();

        $invitedCount = $user->referrals()->count();
        $convertedCount = $user->referralRewards()
            ->where('kind', ReferralReward::KIND_CONVERSION)
            ->count();

        return response()->json([
            'enabled' => (bool) $settings->enabled,
            'code' => $user->referral_code,
            'credit' => (int) $user->referral_credit,
            // Advertised reward amounts, so the UI stays in sync with /admin.
            'referrer_reward' => (int) $settings->referrer_reward,
            'referred_reward' => (int) $settings->referred_reward,
            // How this owner's own invites are performing.
            'invited_count' => $invitedCount,
            'converted_count' => $convertedCount,
            // Who brought this owner in, if anyone.
            'referred_by' => $user->referrer?->name,
        ]);
    }
}
