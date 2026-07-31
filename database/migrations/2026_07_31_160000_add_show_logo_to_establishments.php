<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner can hide the centred logo on the guest cover without deleting the file
 * (or the letter fallback). Defaults to shown so existing venues keep the
 * current look.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->boolean('show_logo')->default(true)->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn('show_logo');
        });
    }
};
