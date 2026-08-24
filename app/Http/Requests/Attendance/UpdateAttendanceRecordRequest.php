<?php

namespace App\Http\Requests\Attendance;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof AttendanceRecord
            && (int) $record->session->section->user_id === (int) $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_LATE,
            ])],
        ];
    }
}
