<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google (OAuth) sign-in. `google_id` is the stable Google account subject —
 * unique so one Google account maps to one user; nullable because password
 * accounts have none. `avatar` holds the Google profile picture URL. Password
 * becomes nullable: an account created purely through Google never sets one
 * (password login simply fails the hash check for such users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar']);
            // Restore NOT NULL. Any Google-only rows must be handled before
            // rolling back; there is no password to backfill.
            $table->string('password')->nullable(false)->change();
        });
    }
};
