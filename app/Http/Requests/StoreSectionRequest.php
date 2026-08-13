<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'schedules' => ['array', 'max:35'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:1,5'],
            'schedules.*.starts_at' => ['required', 'date_format:H:i'],
            'schedules.*.ends_at' => ['required', 'date_format:H:i', 'after:schedules.*.starts_at'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $seen = [];

            foreach ((array) $this->input('schedules', []) as $index => $schedule) {
                $day = $schedule['day_of_week'] ?? null;
                $startsAt = $schedule['starts_at'] ?? null;

                if (! $day || ! $startsAt) {
                    continue;
                }

                $key = $day.'-'.$startsAt;

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "schedules.$index.day_of_week",
                        'This weekday and start time are already included in another schedule entry.',
                    );
                }

                $seen[$key] = true;
            }
        }];
    }
}
