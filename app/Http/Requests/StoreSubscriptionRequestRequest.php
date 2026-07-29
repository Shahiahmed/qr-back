<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The menu this request pays for — must belong to the caller, so an
            // owner cannot file against someone else's venue.
            'establishment_id' => [
                'required',
                Rule::exists('establishments', 'id')->where('user_id', $this->user()->id),
            ],
            // Must be a real, currently orderable plan.
            'plan_id' => [
                'required',
                Rule::exists('plans', 'id')->where('is_active', true),
            ],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
