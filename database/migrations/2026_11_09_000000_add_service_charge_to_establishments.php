<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional service charge («обслуживание») the venue adds to the bill, as a
 * percent with one decimal (e.g. 10, 12.5). null = the venue doesn't charge
 * one — no separate on/off flag. Shown to guests in the menu header and added
 * to the cart total and the order sent to Telegram.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->decimal('service_charge_percent', 4, 1)->nullable()->after('show_logo');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn('service_charge_percent');
        });
    }
};
