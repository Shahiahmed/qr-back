<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Mints an invite code for every owner who has none. The code is normally
 * stamped in the User `creating` hook, so accounts made before the referral
 * feature shipped are left with a null code and an empty share link. Idempotent
 * — accounts that already have a code are skipped.
 */
class BackfillReferralCodes extends Command
{
    protected $signature = 'referral:backfill-codes';

    protected $description = 'Mint referral codes for owners registered before the referral feature';

    public function handle(): int
    {
        $users = User::whereNull('referral_code')->get();

        if ($users->isEmpty()) {
            $this->info('All owners already have a referral code — nothing to do.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $user->ensureReferralCode();
        }

        $this->info("Minted referral codes for {$users->count()} owner(s).");

        return self::SUCCESS;
    }
}
