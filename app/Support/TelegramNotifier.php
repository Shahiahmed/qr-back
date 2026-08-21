<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends plain-text notifications through a Telegram bot (created with
 * @BotFather). One shared bot serves the whole platform: the admin gets
 * platform notices, and each venue binds its own chat for staff alerts.
 *
 * Every send is best-effort: it never throws and never blocks a user action for
 * long. When the bot token is absent (local, tests, CI) it silently no-ops, so
 * nothing external is called unless the server is deliberately configured.
 */
class TelegramNotifier
{
    /** True when a bot token exists — enough to send to any chat id. */
    public static function hasBot(): bool
    {
        return (bool) config('services.telegram.bot_token');
    }

    /** True when the admin chat is also configured. */
    public static function isConfigured(): bool
    {
        return self::hasBot() && (bool) config('services.telegram.admin_chat_id');
    }

    /** Notify the platform admin. Returns false when unconfigured or failed. */
    public static function notifyAdmin(string $message): bool
    {
        $chatId = config('services.telegram.admin_chat_id');

        if (! $chatId) {
            return false;
        }

        return self::notify($chatId, $message);
    }

    /**
     * Send a message to an arbitrary chat id (e.g. a venue's bound chat).
     * Returns false when there is no bot token or the send failed.
     */
    public static function notify(int|string $chatId, string $message): bool
    {
        if (! self::hasBot()) {
            return false;
        }

        $token = config('services.telegram.bot_token');

        try {
            $response = Http::timeout(8)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                Log::warning('Telegram notification rejected', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            // A notification must never break the action that triggered it.
            Log::warning('Telegram notification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
