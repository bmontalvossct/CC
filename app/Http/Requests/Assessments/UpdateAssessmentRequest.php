<?php

namespace App\Http\Requests\Assessments;

class UpdateAssessmentRequest extends StoreAssessmentRequest
{
    public function rules(): array
    {
        return array_map(fn (array $rules) => array_map(
            fn ($rule) => $rule === 'required' ? 'sometimes' : $rule,
            $rules
        ), parent::rules());
    }
}
