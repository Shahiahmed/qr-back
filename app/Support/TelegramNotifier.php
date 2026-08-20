<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends plain-text notifications to the platform admin through a Telegram bot
 * (created with @BotFather). No verification or templates needed — just a bot
 * token and the admin's chat id.
 *
 * Every send is best-effort: it never throws and never blocks a user action for
 * long. When credentials are absent (local, tests, CI) it silently no-ops, so
 * nothing external is called unless the server is deliberately configured.
 */
class TelegramNotifier
{
    public static function isConfigured(): bool
    {
        return (bool) config('services.telegram.bot_token')
            && (bool) config('services.telegram.admin_chat_id');
    }

    /** Notify the admin. Returns false when unconfigured or the send failed. */
    public static function notifyAdmin(string $message): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        try {
            $response = Http::timeout(8)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                Log::warning('Telegram admin notification rejected', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            // A notification must never break the action that triggered it.
            Log::warning('Telegram admin notification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
