<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A guest tap on "call waiter". Public (no auth) — the reason is a fixed enum,
 * the table label a short free string, so nothing arbitrary reaches Telegram.
 */
class WaiterCallRequest extends FormRequest
{
    /** What the guest can ask for. Kept in sync with the front-end buttons. */
    public const REASONS = ['waiter', 'bill', 'help'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(self::REASONS)],
            // Table number/name the guest types; short and optional.
            'table' => ['nullable', 'string', 'max:30'],
        ];
    }
}
