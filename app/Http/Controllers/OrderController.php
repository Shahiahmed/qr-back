<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Models\Establishment;
use App\Support\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A guest places an order from the public menu. No auth — this is the same
 * table QR the menu is served from. The venue's bound Telegram chat gets a
 * best-effort message with the order; nothing is stored server-side (Phase 1).
 *
 * Prices are never taken from the request: only dish id + quantity arrive, and
 * every line's price is read from this venue's own dishes, so the total can't
 * be forged and hidden/other-venue dishes can't be ordered.
 */
class OrderController extends Controller
{
    /** Currency symbols for the staff message; falls back to the code. */
    private const SYMBOLS = ['KZT' => '₸', 'USD' => '$', 'RUB' => '₽'];

    public function __invoke(OrderRequest $request, string $slug): JsonResponse
    {
        // Resolve fresh (not via the cached payload) — we need the chat id, which
        // is never exposed in the cache. Expired/hidden menus can't be ordered.
        $establishment = Establishment::query()->where('slug', $slug)->first();

        abort_if($establishment === null || $establishment->isExpired(), 404);
        abort_unless($establishment->telegramConnected(), 404);

        // Throttle per venue so nobody can flood the staff chat. Generous enough
        // for a real table (mains, then dessert) but stops a bored guest cold.
        $key = "order:{$establishment->id}";
        abort_if(RateLimiter::tooManyAttempts($key, maxAttempts: 10), 429);
        RateLimiter::hit($key, decaySeconds: 60);

        $items = collect($request->validated('items'));

        // Dishes that actually belong to this venue and are visible. A tampered
        // id, a hidden dish, or another venue's dish simply drops out here.
        $dishes = $establishment->dishes()
            ->where('is_visible', true)
            ->whereIn('id', $items->pluck('dish_id')->all())
            ->get()
            ->keyBy('id');

        $lines = $items
            ->map(function (array $item) use ($dishes) {
                $dish = $dishes->get($item['dish_id']);

                return $dish ? ['dish' => $dish, 'qty' => $item['qty']] : null;
            })
            ->filter()
            ->values();

        // Everything the guest sent was invalid (tampering) — refuse rather than
        // send an empty order to staff.
        abort_if($lines->isEmpty(), 422);

        TelegramNotifier::notify(
            $establishment->telegram_chat_id,
            $this->message(
                $establishment->currency,
                $lines->all(),
                $request->validated('table'),
                $request->validated('comment'),
            ),
        );

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<int, array{dish: \App\Models\Dish, qty: int}>  $lines
     */
    private function message(string $currency, array $lines, ?string $table, ?string $comment): string
    {
        $text = '🧾 Новый заказ';

        if ($table !== null && trim($table) !== '') {
            $text .= "\nСтол: " . trim($table);
        }

        $text .= "\n";
        $total = 0;

        foreach ($lines as $line) {
            $dish = $line['dish'];
            $qty = $line['qty'];
            $sum = $dish->price * $qty;
            $total += $sum;

            $text .= "\n• {$dish->name_ru} × {$qty} — " . $this->money($sum, $currency);
        }

        $text .= "\n\nИтого: " . $this->money($total, $currency);

        if ($comment !== null && trim($comment) !== '') {
            $text .= "\n\nКомментарий: " . trim($comment);
        }

        return $text;
    }

    /** Minor units (тиыны) → a human amount for the staff message. */
    private function money(int $minor, string $currency): string
    {
        $symbol = self::SYMBOLS[$currency] ?? $currency;

        return number_format($minor / 100, 0, '.', ' ') . " {$symbol}";
    }
}
