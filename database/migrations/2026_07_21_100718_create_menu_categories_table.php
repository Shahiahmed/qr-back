<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu sections — «Горячее», «Салаты», «Напитки».
 *
 * Bilingual by column rather than by row: a guest switching language must not
 * cause a second query, and the public menu is read-heavy.
 *
 * The Kazakh suffix is `kk`, matching `establishments.default_locale`, so code
 * can address a column as "name_{$locale}". (`.cursorrules` writes `name_kz`,
 * but `kz` is a country code and would need a mapping at every call site.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();

            $table->string('name_ru');
            // Optional: a venue may launch in Russian and translate later.
            $table->string('name_kk')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);

            $table->timestamps();

            // Every read is scoped by tenant and ordered by position.
            $table->index(['establishment_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_categories');
    }
};
