<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            /*
             * `establishment_id` is carried alongside the category rather than
             * reached through it: every menu query is scoped by tenant, and
             * joining a category first to learn the tenant would make that
             * scope easy to forget.
             */
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();

            $table->string('name_ru');
            $table->string('name_kk')->nullable();
            $table->text('description_ru')->nullable();
            $table->text('description_kk')->nullable();

            /*
             * Minor units — тиыны, 1/100 ₸. Never a float: 2490.00 ₸ cannot be
             * held exactly in binary and rounding drift shows up on a bill.
             */
            $table->unsignedBigInteger('price');

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            // The stop list: in stock or run out for today.
            $table->boolean('is_available')->default(true);

            $table->timestamps();

            $table->index(['establishment_id', 'menu_category_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
