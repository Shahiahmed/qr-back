<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    /** Hidden field only a bot fills in — real visitors never see it. */
    public const HONEYPOT = 'contact_me_by_fax_only';

    /**
     * Milliseconds the form was open before submit, reported by the page. A
     * human needs a few seconds to type four fields; scripts post instantly
     * or skip the page entirely and send no timing at all.
     */
    public const ELAPSED = 'form_time';

    public const MIN_FORM_MS = 2000;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // A WhatsApp number, so a manager can reach a new owner the same day.
            'phone' => ['required', 'string', 'regex:'.Phone::PATTERN],
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

    /**
     * Bot trap. Answers with one neutral form-level error rather than a silent
     * fake success: a human who autofilled and clicked within two seconds just
     * presses the button again (by then enough time has passed), while a bot
     * learns nothing about which check tripped.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $trap = $this->input(self::HONEYPOT);
                $elapsed = $this->input(self::ELAPSED);

                $reason = match (true) {
                    filled($trap) => 'honeypot',
                    ! is_numeric($elapsed) => 'no-timing',
                    (int) $elapsed < self::MIN_FORM_MS => 'too-fast',
                    default => null,
                };

                if ($reason === null) {
                    return;
                }

                // Logged so we can tell later whether the trap actually catches
                // the random-name signups — without storing the password.
                Log::info('Signup rejected as bot', [
                    'reason' => $reason,
                    'email' => $this->input('email'),
                    'ip' => $this->ip(),
                ]);

                $validator->errors()->add('form', __('auth.bot'));
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->email)) {
            $merge['email'] = mb_strtolower(trim($this->email));
        }

        if (is_string($this->phone)) {
            $merge['phone'] = Phone::normalize($this->phone);
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
