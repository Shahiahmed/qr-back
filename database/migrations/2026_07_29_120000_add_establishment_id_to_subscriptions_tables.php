<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions are billed per menu, not per account: each request and each
 * grant points at one establishment. A menu's access window is driven by its
 * own active grant (falling back to its own trial). Nullable so pre-existing
 * rows survive; cascade so a deleted menu takes its requests/grants with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
            $table->index(['establishment_id', 'status']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
            $table->index(['establishment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('establishment_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('establishment_id');
        });
    }
};
