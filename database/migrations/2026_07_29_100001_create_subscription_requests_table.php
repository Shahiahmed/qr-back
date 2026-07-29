<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request for a subscription (заявка). The owner picks a plan and leaves a
 * contact; an admin approves or rejects it in the panel. Approval activates a
 * row in `subscriptions`. There is no online payment yet — this is the manual
 * flow that stands in for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Keep the request in history even if the plan is later removed.
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 20)->default('new'); // new | approved | rejected

            // How to reach the owner about this request, plus an optional message.
            $table->string('contact_phone')->nullable();
            $table->text('note')->nullable();

            // Who handled it and when (an admin user).
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_requests');
    }
};
