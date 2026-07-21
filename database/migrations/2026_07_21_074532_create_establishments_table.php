<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venue. This is the tenant every piece of menu data hangs off — menus,
 * categories, dishes and orders will all carry `establishment_id`, and every
 * query for them is scoped by it.
 *
 * One owner can hold several venues from the start: the Premium plan sells
 * «несколько точек», and retrofitting that later would touch every table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            // Lives in the public menu URL, so it stays unique across tenants.
            $table->string('slug')->unique();

            // ISO 4217. Amounts are held in minor units wherever they appear.
            $table->string('currency', 3)->default('KZT');
            // Language the guest is shown first: `ru` or `kk`.
            $table->string('default_locale', 2)->default('ru');

            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishments');
    }
};
