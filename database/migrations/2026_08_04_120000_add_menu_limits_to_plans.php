<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-menu content caps carried by a plan. The free tier limits how much menu a
 * trial / free-plan venue may hold (3 sections, 5 dishes per section); paid
 * tiers leave both null = unlimited. Enforced live on the create endpoints
 * (unlike `max_establishments`, which stays advisory for now).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // null = unlimited. Small integers: a menu never needs thousands.
            $table->unsignedSmallInteger('max_categories')->nullable()->after('max_establishments');
            $table->unsignedSmallInteger('max_dishes_per_category')->nullable()->after('max_categories');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_categories', 'max_dishes_per_category']);
        });
    }
};
