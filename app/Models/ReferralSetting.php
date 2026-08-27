<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Referral program settings — a singleton (one row, id = 1). Managed only in the
 * admin panel; there is no owner-facing HTTP write path, so every column is
 * fillable. Reward amounts are in tiyn (1/100 ₸).
 */
#[Fillable(['enabled', 'referrer_reward', 'referred_reward'])]
class ReferralSetting extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'referrer_reward' => 'integer',
            'referred_reward' => 'integer',
        ];
    }

    /**
     * The one and only settings row, created on first access with the migration
     * defaults (inviter 10 000 ₸, invited 5 000 ₸).
     */
    public static function current(): self
    {
        // Defaults are passed explicitly (not left to the DB) so the freshly
        // created instance carries them in memory too — firstOrCreate does not
        // reload DB-side column defaults on the row it just inserted.
        return static::query()->firstOrCreate(['id' => 1], [
            'enabled' => true,
            'referrer_reward' => 1_000_000,
            'referred_reward' => 500_000,
        ]);
    }
}
