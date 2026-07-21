<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDishRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $establishment = $this->route('establishment');

        return [
            'menu_category_id' => [
                'required',
                // Scoped to this venue: without the `where` an owner could
                // file a dish under another tenant's category by guessing an id.
                Rule::exists('menu_categories', 'id')
                    ->where('establishment_id', $establishment?->id),
            ],
            'name_ru' => ['required', 'string', 'max:150'],
            'name_kk' => ['nullable', 'string', 'max:150'],
            'description_ru' => ['nullable', 'string', 'max:1000'],
            'description_kk' => ['nullable', 'string', 'max:1000'],
            /*
             * Minor units — тиыны. Sent as an integer so no float ever touches
             * a price. Ceiling is ~10 million ₸, well past any dish.
             */
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_visible' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }
}
