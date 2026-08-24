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

    protected function prepareForValidation(): void
    {
        if ($this->has('attendance_session_id') && ($this->attendance_session_id === '' || $this->attendance_session_id === 'null')) {
            $this->merge(['attendance_session_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(Assessment::TYPES)],
            'assessment_number' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'conducted_on' => ['required', 'date'],
            'max_points' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'attendance_session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
            'attachment' => ['nullable', 'file', 'max:51200', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,rtf,odt,ods,odp,svg,gif,bmp,heic,pages,numbers,key'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.max' => 'The attachment must not be larger than 50MB.',
            'attachment.extensions' => 'The attachment must be a valid file type (PDF, Word, Excel, PowerPoint, Text, Image, Zip).',
            'attachment.file' => 'The uploaded file is invalid or could not be processed.',
        ];
    }
}
