<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venue header (what the guest sees above the menu) and its colour theme.
 *
 * These fields were already rendered by the guest menu for the built-in demo;
 * this makes them real, editable venue data. Cover/dish photos are deliberately
 * left out — image upload is a separate feature and needs storage of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            // Guest-facing header chips.
            $table->string('wifi_ssid', 60)->nullable()->after('phone');
            $table->string('wifi_password', 60)->nullable()->after('wifi_ssid');
            $table->string('instagram_url')->nullable()->after('wifi_password');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('tiktok_url')->nullable()->after('facebook_url');

            // Colour preset key (see Establishment::THEMES). Only the accent
            // family changes, so a guest can never end up with unreadable text.
            $table->string('theme', 20)->default('classic')->after('tiktok_url');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn([
                'wifi_ssid', 'wifi_password', 'instagram_url',
                'facebook_url', 'tiktok_url', 'theme',
            ]);
        });
    }
};
