<?php

namespace App\Http\Requests;

class UpdateSectionRequest extends StoreSectionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('section')) ?? false;
    }
}
