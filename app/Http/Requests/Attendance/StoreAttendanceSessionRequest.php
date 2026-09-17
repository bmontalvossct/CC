<?php

namespace App\Http\Requests\Attendance;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            && (int) $section->user_id === (int) $this->user()?->id;
    }

    public function rules(): array
    {
        /** @var Section $section */
        $section = $this->route('section');

        return [
            'session_date' => ['required', 'date'],
            'starts_at' => [
                'required',
                'date_format:H:i',
                Rule::unique('attendance_sessions', 'starts_at')->where(fn ($query) => $query
                    ->where('section_id', $section->id)
                    ->where('session_date', $this->input('session_date'))),
            ],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
