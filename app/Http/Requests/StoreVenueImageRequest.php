<?php

namespace App\Http\Requests;

use App\Support\VenueImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenueImageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(array_keys(VenueImage::KINDS))],
            // 8 MB of raw phone photo is fine — we downscale it before storing.
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ];
    }
}
