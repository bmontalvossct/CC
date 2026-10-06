<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rubric_data') && is_string($this->rubric_data)) {
            $decoded = json_decode($this->rubric_data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge(['rubric_data' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(Project::TYPES)],
            'format' => ['nullable', Rule::in(Project::FORMATS)],
            'project_number' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'conducted_on' => ['nullable', 'date'],
            'max_points' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'group_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'group_size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'randomize' => ['nullable', 'boolean'],
            'source_project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'copy_topics' => ['nullable', 'boolean'],
            'copy_names' => ['nullable', 'boolean'],
            'rubric_type' => ['nullable', 'string', 'in:percentage,answer_key,file,custom'],
            'rubric_data' => ['nullable', 'array'],
            'attachment' => ['nullable', 'file', 'max:51200', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,rtf,odt,ods,odp,svg,gif,bmp,heic,pages,numbers,key,json,sql,db,sqlite,sqlite3'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.max' => 'The attachment must not be larger than 50MB.',
            'attachment.extensions' => 'The attachment must be a valid file type (PDF, Word, Excel, PowerPoint, Text, Image, Zip, JSON, Database).',
            'attachment.file' => 'The uploaded file is invalid or could not be processed.',
        ];
    }
}
