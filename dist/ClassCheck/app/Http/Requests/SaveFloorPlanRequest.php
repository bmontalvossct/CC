<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveFloorPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('section')) ?? false;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'integer', 'between:1,20'],
            'columns' => ['required', 'integer', 'between:1,20'],
            'aisle_after_rows' => ['present', 'array'],
            'aisle_after_rows.*' => ['integer', 'min:1', 'distinct'],
            'aisle_after_columns' => ['present', 'array'],
            'aisle_after_columns.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rows = (int) $this->input('rows');
                $columns = (int) $this->input('columns');

                foreach ($this->input('aisle_after_rows', []) as $position) {
                    if ((int) $position >= $rows) {
                        $validator->errors()->add('aisle_after_rows', 'A horizontal aisle must sit between two chair rows.');
                    }
                }

                foreach ($this->input('aisle_after_columns', []) as $position) {
                    if ((int) $position >= $columns) {
                        $validator->errors()->add('aisle_after_columns', 'A vertical aisle must sit between two chair columns.');
                    }
                }
            },
        ];
    }
}
