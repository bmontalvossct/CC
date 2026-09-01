<?php

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score' => ['present', 'nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'include_absent' => ['sometimes', 'boolean'],
        ];
    }
}
