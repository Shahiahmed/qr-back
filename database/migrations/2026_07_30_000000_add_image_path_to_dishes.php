<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            // Relative path to the processed WebP (never a URL — the public URL
            // is built in the resource). Nullable: a dish may have no photo.
            $table->string('image_path')->nullable()->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
