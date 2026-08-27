<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referral program. Owners invite other owners; both sides get a fixed money
 * discount off a tariff (amounts editable in /admin).
 *
 * The reward is a wallet in tiyn on the user (`referral_credit`), spent on a
 * subscription request to lower its charge and consumed at approval. It is set
 * only by trusted server code (registration + the approval flow), never
 * mass-assignable — the same discipline as is_admin / telegram_chat_id.
 *
 * `referral_rewards` is an append-only ledger for audit and idempotency: the
 * unique (referred_id, kind) guarantees each new user grants at most one signup
 * reward and triggers at most one conversion reward, however many times the
 * approval flow runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Each owner's own invite code, carried in `?ref=CODE`. Minted on
            // create; unique so a link resolves to exactly one inviter.
            $table->string('referral_code')->nullable()->unique()->after('email');
            // Who invited this owner. Set once at registration, then immutable.
            $table->foreignId('referred_by_id')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
            // Spendable discount balance, in tiyn (1/100 ₸).
            $table->unsignedBigInteger('referral_credit')->default(0)->after('referred_by_id');
        });

        // How much of the wallet the owner asked to apply to this request.
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('referral_credit_applied')->default(0)->after('note');
        });

        // What was actually discounted at approval (clamped to the live balance).
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('referral_credit_applied')->default(0)->after('ends_at');
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            // The inviter and the invited owner of this pair.
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
            // The paid subscription that triggered a conversion reward; null for
            // the signup reward, which is granted before any payment.
            $table->foreignId('subscription_id')->nullable()
                ->constrained('subscriptions')->nullOnDelete();
            // 'signup'      → referred owner's welcome discount (beneficiary = referred)
            // 'conversion'  → inviter's reward for a paid referral (beneficiary = referrer)
            $table->string('kind', 20);
            // Amount credited, in tiyn.
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            // One signup reward per new user, one conversion reward per referral.
            $table->unique(['referred_id', 'kind']);
        });

        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            // Master switch — the program can be paused without code changes.
            $table->boolean('enabled')->default(true);
            // Reward amounts in tiyn. Defaults: inviter 10 000 ₸, invited 5 000 ₸.
            $table->unsignedBigInteger('referrer_reward')->default(1_000_000);
            $table->unsignedBigInteger('referred_reward')->default(500_000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
        Schema::dropIfExists('referral_rewards');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('referral_credit_applied');
        });

        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropColumn('referral_credit_applied');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_id');
            $table->dropColumn(['referral_code', 'referral_credit']);
        });
    }
};
