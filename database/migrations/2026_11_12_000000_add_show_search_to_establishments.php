<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner can hide the dish search bar on the guest menu (a short menu does not
 * need it). Defaults to shown so existing venues keep the current look.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->boolean('show_search')->default(true)->after('show_logo');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn('show_search');
        });
    }
};
