<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A guest places an order from the table QR. Public (no auth): dish ids are
 * checked against this venue and every price is read from the database in the
 * controller, so the request carries only dish id + quantity — nothing the
 * guest sends can set the total. Table label and comment are short free text.
 */
class OrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Table number/name the guest types (auto-filled from a per-table QR).
            'table' => ['nullable', 'string', 'max:30'],
            'comment' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.dish_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
