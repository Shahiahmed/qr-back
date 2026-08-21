<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Support\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound Telegram webhook: how a venue binds its chat to the bot.
 *
 * The owner opens `t.me/<bot>?start=<token>` and presses Start; Telegram POSTs
 * that update here as `/start <token>`. We match the token to the venue and
 * store the chat id, so the bot can later post waiter calls to that chat.
 *
 * No auth/session: Telegram calls this server-to-server. The path carries a
 * shared secret instead (see routes/api.php). We always answer 200 — a non-2xx
 * makes Telegram retry the same update — and never trust the payload beyond the
 * one-time token match.
 */
class TelegramWebhookController extends Controller
{
    /** Telegram's own cap on a bot command's argument; ignore anything longer. */
    private const MAX_TOKEN = 64;

    public function __invoke(Request $request, string $secret): JsonResponse
    {
        // Guard the path with the configured secret (constant-time compare).
        // If unset, refuse everything rather than accept an open webhook.
        $expected = config('services.telegram.webhook_secret');

        if (! $expected || ! hash_equals((string) $expected, $secret)) {
            return response()->json(['ok' => true]);
        }

        $message = $request->input('message', []);
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = $message['chat']['id'] ?? null;

        if ($chatId !== null && preg_match('/^\/start(?:@\w+)?\s+(\S+)$/', $text, $m)) {
            $this->bind($m[1], (string) $chatId);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Bind the chat to the venue named by a still-valid link token. The token is
     * cleared on use, so a leaked deep link can't rebind later. Both columns are
     * out of #[Fillable]; written with forceFill in this trusted server path.
     */
    private function bind(string $token, string $chatId): void
    {
        if (strlen($token) > self::MAX_TOKEN) {
            return;
        }

        $establishment = Establishment::query()
            ->where('telegram_link_token', $token)
            ->first();

        if (! $establishment) {
            return;
        }

        $establishment->forceFill([
            'telegram_chat_id' => $chatId,
            'telegram_link_token' => null,
        ])->save();

        // Confirm in the chat so staff know it worked. Best-effort.
        TelegramNotifier::notify(
            $chatId,
            "✅ «{$establishment->name}» подключено. Сюда будут приходить вызовы официанта.",
        );
    }
}
