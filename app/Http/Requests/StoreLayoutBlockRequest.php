<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLayoutBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('section')) ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:40'],
            'block_row' => ['required', 'integer', 'between:1,50'],
            'block_column' => ['required', 'integer', 'between:1,50'],
            'internal_rows' => ['required', 'integer', 'between:1,20'],
            'internal_columns' => ['required', 'integer', 'between:1,20'],
            'disabled_positions' => ['array'],
            'disabled_positions.*' => ['string', 'regex:/^\d+:\d+$/'],
        ];
    }
}
