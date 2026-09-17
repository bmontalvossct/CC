<?php

namespace App\Http\Requests\Assessments;

use App\Models\Assessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(Assessment::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'conducted_on' => ['required', 'date'],
            'max_points' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'attendance_session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx'],
        ];
    }
}
