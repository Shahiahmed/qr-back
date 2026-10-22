<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Same rule as registration: once a number is on file it may be changed but
 * not cleared — it is how a manager reaches the owner.
 */
class UpdatePhoneRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:'.Phone::PATTERN],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->phone)) {
            $this->merge(['phone' => Phone::normalize($this->phone)]);
        }
    }
}
