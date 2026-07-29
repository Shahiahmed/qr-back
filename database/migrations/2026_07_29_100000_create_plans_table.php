<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans (тарифы). The admin manages the catalogue; the public
 * landing renders it and owners request one. Prices live in minor units
 * (тиыны, 1/100 ₸) like every other amount — never float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            // Bilingual, like the rest of the menu domain. Kazakh is optional so
            // a plan can launch in Russian and be translated later.
            $table->string('name_ru');
            $table->string('name_kk')->nullable();
            $table->string('tagline_ru')->nullable();
            $table->string('tagline_kk')->nullable();

            // Monthly (or yearly, see `period`) price in tiyn. Discount is a
            // percentage off; the effective price is computed on read.
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->string('period', 10)->default('month'); // month | year

            // Feature bullet points: [{ ru, kk }, ...]. JSON so the admin can
            // add/reorder lines without a migration.
            $table->json('features')->nullable();

            // Plan limits. null = unlimited. Enforcement comes later; for now
            // this is recorded so the tier means something.
            $table->unsignedSmallInteger('max_establishments')->nullable();

            $table->boolean('is_active')->default(true);   // shown / orderable
            $table->boolean('is_featured')->default(false); // "популярный" badge
            $table->unsignedInteger('sort')->default(0);    // display order

            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
