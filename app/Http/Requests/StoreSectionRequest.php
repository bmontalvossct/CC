<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'subject_code' => ['required', 'string', 'max:50'],
            'subject_title' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'term.name' => ['required', 'string', 'max:100'],
            'term.school_year' => ['required', 'string', 'max:20'],
            'term.starts_on' => ['required', 'date'],
            'term.ends_on' => ['required', 'date', 'after_or_equal:term.starts_on'],
            'schedules' => ['array', 'max:7'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'schedules.*.starts_at' => ['required', 'date_format:H:i'],
            'schedules.*.ends_at' => ['required', 'date_format:H:i', 'after:schedules.*.starts_at'],
        ];
    }
}
