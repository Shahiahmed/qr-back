<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-side block of a venue's guest menu (moderation: unpaid, abuse,
 * owner request). `blocked_at` set = the menu is off for guests; null = normal.
 * `blocked_reason` is an internal admin note — never sent to guests.
 *
 * Written only from the admin panel via forceFill — never mass-assignable, so
 * an owner cannot unblock themselves through the venue form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('trial_ends_at');
            $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'blocked_reason']);
        });
    }
};
