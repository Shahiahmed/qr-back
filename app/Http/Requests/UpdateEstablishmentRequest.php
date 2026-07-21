<?php

namespace App\Http\Requests;

use App\Models\Establishment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEstablishmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Establishment $establishment */
        $establishment = $this->route('establishment');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Establishment::RESERVED_SLUGS),
                // The venue keeps its own slug — otherwise saving the form
                // untouched would fail on its own value.
                Rule::unique('establishments', 'slug')->ignore($establishment?->id),
            ],
            'currency' => ['sometimes', 'required', Rule::in(Establishment::CURRENCIES)],
            'default_locale' => ['sometimes', 'required', Rule::in(Establishment::LOCALES)],
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
