<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The new order of a venue's menu sections, as a list of category ids.
 *
 * Ownership is not checked here — the controller only repositions ids that
 * actually belong to the venue, so a stray id from another tenant is ignored
 * rather than trusted.
 */
class ReorderCategoriesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ];
    }
}
