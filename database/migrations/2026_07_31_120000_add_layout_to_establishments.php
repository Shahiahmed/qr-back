<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu layout preset — how the guest menu arranges dishes, separate from the
 * colour `theme`. `classic` keeps the current photo-left cards; other presets
 * (grid, compact) rearrange the same data. Defaults to `classic` so every
 * existing venue keeps its current look until the owner changes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->string('layout', 20)->default('classic')->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
