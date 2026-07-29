<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-menu free trial window. A newly created venue is publicly available for
 * one week; an active subscription (see `subscriptions.ends_at`) overrides this
 * for the whole account. Nullable so existing venues stay grandfathered — the
 * limit only applies to menus created from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn('trial_ends_at');
        });
    }
};
