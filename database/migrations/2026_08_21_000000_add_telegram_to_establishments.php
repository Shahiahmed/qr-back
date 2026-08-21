<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-venue Telegram binding for staff notifications (waiter calls, later
 * orders). One shared bot; each venue binds its own chat via a deep link.
 *
 * `telegram_chat_id` is the chat the bot posts to; `telegram_link_token` is the
 * one-time token carried in `t.me/<bot>?start=<token>` and cleared once the
 * chat is bound. Both are set only in trusted server code (owner endpoint +
 * webhook) — never mass-assignable, so no form can bind an arbitrary chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('show_logo');
            $table->string('telegram_link_token')->nullable()->unique()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token']);
        });
    }
};
