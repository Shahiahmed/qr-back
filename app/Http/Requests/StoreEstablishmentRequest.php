<?php

namespace App\Http\Requests;

use App\Models\Establishment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstablishmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:60',
                // Lowercase latin, digits and single hyphens; no leading or
                // trailing hyphen. This ends up in a URL people type by hand.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Establishment::RESERVED_SLUGS),
                Rule::unique('establishments', 'slug'),
            ],
            'currency' => ['required', Rule::in(Establishment::CURRENCIES)],
            'default_locale' => ['required', Rule::in(Establishment::LOCALES)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->slug)) {
            $this->merge(['slug' => mb_strtolower(trim($this->slug))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('validation.custom.slug.regex'),
            'slug.not_in' => __('validation.custom.slug.reserved'),
        ];
    }
}
