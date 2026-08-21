<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaiterCallRequest;
use App\Models\Establishment;
use App\Support\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A guest calls the waiter from the public menu. No auth — this is the same
 * table QR the menu is served from. The venue's bound Telegram chat gets a
 * best-effort message; nothing is stored server-side (Phase 1).
 */
class WaiterCallController extends Controller
{
    /** Human labels for the fixed reason enum, in the message to staff. */
    private const REASON_LABELS = [
        'waiter' => 'Позвать официанта',
        'bill' => 'Счёт',
        'help' => 'Нужна помощь',
    ];

    public function __invoke(WaiterCallRequest $request, string $slug): JsonResponse
    {
        // Resolve fresh (not via the cached payload) — we need the chat id, which
        // is never exposed in the cache. Expired/hidden menus can't be called.
        $establishment = Establishment::query()->where('slug', $slug)->first();

        abort_if($establishment === null || $establishment->isExpired(), 404);
        abort_unless($establishment->telegramConnected(), 404);

        // Throttle per venue so a bored guest can't spam the staff chat. Keyed by
        // venue, not IP: many guests share one venue's Wi-Fi behind one NAT.
        $key = "waiter-call:{$establishment->id}";
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 3)) {
            return response()->json(['ok' => true, 'throttled' => true]);
        }
        RateLimiter::hit($key, decaySeconds: 30);

        TelegramNotifier::notify(
            $establishment->telegram_chat_id,
            $this->message($request->validated('reason'), $request->validated('table')),
        );

        return response()->json(['ok' => true]);
    }

    private function message(string $reason, ?string $table): string
    {
        $label = self::REASON_LABELS[$reason] ?? self::REASON_LABELS['waiter'];

        $line = "🔔 {$label}";

        if ($table !== null && trim($table) !== '') {
            $line .= "\nСтол: " . trim($table);
        }

        return $line;
    }
}
