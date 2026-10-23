<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGradingFormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'best_grade' => ['required', 'numeric', 'min:0', 'max:100', 'lt:worst_grade'],
            'worst_grade' => ['required', 'numeric', 'gt:best_grade', 'max:100'],
            'passing_grade' => ['required', 'numeric', 'gte:best_grade', 'lt:worst_grade'],
            'failed_grade' => ['required', 'numeric', 'gt:passing_grade', 'lte:worst_grade'],
            'midterm_weight' => ['required', 'integer', 'min:0', 'max:100'],
            'final_weight' => ['required', 'integer', 'min:0', 'max:100'],
            'decimal_places' => ['required', 'integer', 'between:0,4'],
            'period_rounding' => ['required', Rule::in(['nearest', 'down', 'up'])],
            'final_rounding' => ['required', Rule::in(['nearest', 'down', 'up'])],
            'replace_failing_grade' => ['required', 'boolean'],
            'recitation_mode' => ['required', Rule::in(['bonus', 'weighted'])],
            'reporting_frequency' => ['required', Rule::in(['once_per_sem', 'twice_per_sem'])],
            'weights' => ['required', 'array:activity,laboratory,quiz,exam,project,attendance,recitation'],
            'weights.activity' => ['required', 'numeric', 'between:0,100'],
            'weights.laboratory' => ['required', 'numeric', 'between:0,100'],
            'weights.quiz' => ['required', 'numeric', 'between:0,100'],
            'weights.exam' => ['required', 'numeric', 'between:0,100'],
            'weights.project' => ['required', 'numeric', 'between:0,100'],
            'weights.attendance' => ['required', 'numeric', 'between:0,100'],
            'weights.recitation' => ['required', 'numeric', 'between:0,100'],
            'passing_rates' => ['required', 'array:activity,quiz,exam,project'],
            'passing_rates.activity' => ['required', 'numeric', 'between:0,100'],
            'passing_rates.quiz' => ['required', 'numeric', 'between:0,100'],
            'passing_rates.exam' => ['required', 'numeric', 'between:0,100'],
            'passing_rates.project' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (((int) $this->input('midterm_weight') + (int) $this->input('final_weight')) !== 100) {
                $validator->errors()->add('final_weight', 'Midterm and final-period weights must add up to 100%.');
            }
            $weights = $this->input('weights');
            $total = array_sum($weights) - ($this->input('recitation_mode') === 'bonus' ? $weights['recitation'] : 0);
            if (abs($total - 100) > 0.00001) {
                $validator->errors()->add('weights', 'Weighted components must add up to 100%. Oral bonus points are excluded from the total.');
            }
        }];
    }
}
