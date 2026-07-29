<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDishImageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 8 MB of raw phone photo is fine — it is cropped and downscaled to
            // an ~800px WebP before storage (DishImage).
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ];
    }
}
