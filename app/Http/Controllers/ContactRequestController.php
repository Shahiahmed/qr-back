<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequestRequest;
use App\Models\ContactRequest;
use App\Support\Phone;
use App\Support\TelegramNotifier;
use Illuminate\Http\JsonResponse;

/**
 * A visitor leaves a call-back request from the landing. Stored for the admin
 * panel, then pushed to the admin's Telegram (best-effort — the row is what
 * counts if the ping is lost).
 */
class ContactRequestController extends Controller
{
    public function __invoke(StoreContactRequestRequest $request): JsonResponse
    {
        $lead = ContactRequest::create(
            $request->safe()->only(['name', 'phone', 'note', 'locale']),
        );

        TelegramNotifier::notifyAdmin(self::adminMessage($lead));

        return response()->json(['ok' => true], 201);
    }

    /** Human-readable summary of a fresh lead for the admin's Telegram. */
    public static function adminMessage(ContactRequest $lead): string
    {
        $lines = [
            '📩 Новая заявка с сайта',
            "Имя: {$lead->name}",
            'Телефон: '.Phone::format($lead->phone),
            'WhatsApp: '.Phone::whatsappUrl($lead->phone),
        ];

        if ($lead->note) {
            $lines[] = "Комментарий: {$lead->note}";
        }

        return implode("\n", $lines);
    }
}
