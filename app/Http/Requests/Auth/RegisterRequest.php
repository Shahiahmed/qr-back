<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            /*
             * `confirmed` expects a `password_confirmation` field. Length is
             * the only hard rule — the uncompromised check would call an
             * external API on every signup, which is a dependency the signup
             * path should not carry.
             */
            'password' => ['required', 'confirmed', Password::min(8)],
            /*
             * An invite code carried from `?ref=CODE`. Soft on purpose: a stale
             * or unknown code must never block a signup — the controller resolves
             * it after the account exists and simply ignores a miss.
             */
            'referral_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->email)) {
            $merge['email'] = mb_strtolower(trim($this->email));
        }

        // Codes are minted uppercase; normalise so links are case-insensitive.
        if (is_string($this->referral_code)) {
            $merge['referral_code'] = mb_strtoupper(trim($this->referral_code));
        }

        if ($merge) {
            $this->merge($merge);
        }
    }
}
