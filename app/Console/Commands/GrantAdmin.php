<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Flags an existing account as platform staff so it can reach /admin. There is
 * no admin sign-up: an owner registers normally, then someone with shell access
 * runs this. Keeps is_admin out of every HTTP path.
 */
class GrantAdmin extends Command
{
    protected $signature = 'admin:grant {email} {--revoke : Remove admin access instead of granting it}';

    protected $description = 'Grant (or revoke with --revoke) admin-panel access for a user by email';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user with email {$email}.");

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');

        // Not fillable — set it directly.
        $user->is_admin = $grant;
        $user->save();

        $this->info($grant
            ? "{$user->email} can now access the admin panel."
            : "{$user->email} no longer has admin access.");

        return self::SUCCESS;
    }
}
