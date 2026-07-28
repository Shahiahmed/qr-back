<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-uploaded venue images: a wide cover behind the header and a logo shown
 * centred over it. We store the relative disk path, not a URL — the public URL
 * is built from it at read time, so moving the storage host never rewrites rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('theme');
            $table->string('logo_path')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'logo_path']);
        });
    }
};
