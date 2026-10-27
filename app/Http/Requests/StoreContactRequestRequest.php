<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\RegisterRequest;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The landing's «Оставить заявку» form. Public, so it carries the same bot trap
 * as sign-up (honeypot + time the form was open) — every accepted lead pings
 * the admin's Telegram.
 */
class StoreContactRequestRequest extends FormRequest
{
    /** A name and a number take a couple of seconds to type. */
    public const MIN_FORM_MS = 1500;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:'.Phone::PATTERN],
            'note' => ['nullable', 'string', 'max:500'],
            'locale' => ['nullable', 'string', 'in:ru,kz,kk'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $elapsed = $this->input(RegisterRequest::ELAPSED);

                $isBot = filled($this->input(RegisterRequest::HONEYPOT))
                    || ! is_numeric($elapsed)
                    || (int) $elapsed < self::MIN_FORM_MS;

                if ($isBot) {
                    $validator->errors()->add('form', __('auth.bot'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->name)) {
            $merge['name'] = trim($this->name);
        }

        if (is_string($this->phone)) {
            $merge['phone'] = Phone::normalize($this->phone);
        }

        if (is_string($this->note)) {
            $merge['note'] = trim($this->note) === '' ? null : trim($this->note);
        }

        if ($merge) {
            $this->merge($merge);
        }
    }
}
