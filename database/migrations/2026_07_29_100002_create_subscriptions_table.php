<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An active (or past) subscription grant. Attached to the owner account, not a
 * single venue — the tier governs the whole cabinet. One is made when an admin
 * approves a subscription_request; the previous active one is closed first, so
 * a user has at most one `active` row at a time (enforced in application code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 20)->default('active'); // active | expired | cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable(); // null = open-ended

            // Where this grant came from (an approved request), for the trail.
            $table->foreignId('request_id')->nullable()
                ->constrained('subscription_requests')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
