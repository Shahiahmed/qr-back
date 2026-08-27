<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'referral_credit' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Every owner gets an invite code at creation. It is out of #[Fillable]
        // (no form may set or change it), so it is stamped here in trusted code.
        static::creating(function (self $user) {
            if ($user->referral_code === null) {
                $user->referral_code = self::mintReferralCode();
            }
        });
    }

    /**
     * Mint and persist an invite code if this owner has none — backfills
     * accounts created before the referral feature (the `creating` hook only
     * fired for new signups). Direct assignment, so it bypasses #[Fillable]
     * the same way the creating hook does; a no-op once a code exists.
     */
    public function ensureReferralCode(): string
    {
        if ($this->referral_code === null) {
            $this->referral_code = self::mintReferralCode();
            $this->save();
        }

        return $this->referral_code;
    }

    /**
     * A short, unambiguous invite code (no 0/O/1/I/L), unique across users.
     * Carried in `?ref=CODE` on the landing and resolved at registration.
     */
    private static function mintReferralCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::query()->where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Only platform staff reach the admin panel. Restaurant owners authenticate
     * against the SPA (Sanctum cookie) and never see Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin === true;
    }

    /** Venues this account owns. The Premium plan allows more than one. */
    public function establishments(): HasMany
    {
        return $this->hasMany(Establishment::class);
    }

    /** Subscription applications this owner has filed. */
    public function subscriptionRequests(): HasMany
    {
        return $this->hasMany(SubscriptionRequest::class);
    }

    /**
     * Subscription grants (active + historical) across all of this owner's
     * menus. Each grant covers one menu — the per-menu window lives on
     * Establishment::currentSubscription, not here.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** The owner who invited this account, if they arrived through a referral. */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    /** Owners who signed up through this account's invite code. */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_id');
    }

    /** Reward ledger rows where this account is the inviter. */
    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'referrer_id');
    }
}
