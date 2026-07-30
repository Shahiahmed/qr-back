<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the `noindex` kill-switch: the live landing must always stay indexable,
 * so the admin no longer has a toggle that could hide the whole site from search
 * (a stray click cost up to a day of the cached payload). The site is now
 * unconditionally crawlable — see the SeoSettings page and robots.ts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_settings', function (Blueprint $table) {
            $table->dropColumn('noindex');
        });
    }

    public function down(): void
    {
        Schema::table('seo_settings', function (Blueprint $table) {
            $table->boolean('noindex')->default(false);
        });
    }
};
