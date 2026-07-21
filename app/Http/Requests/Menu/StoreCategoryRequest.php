<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ru' => ['required', 'string', 'max:120'],
            // Kazakh may be filled in later; the venue can launch without it.
            'name_kk' => ['nullable', 'string', 'max:120'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_visible' => ['sometimes', 'boolean'],
        ];
    }
}
