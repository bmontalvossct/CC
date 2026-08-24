<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Section;

abstract class AssessmentModuleController extends Controller
{
    protected function authorizeSection(Section $section): void
    {
        abort_unless((int) $section->user_id === (int) request()->user()->id, 403);
    }

    protected function authorizeAssessment(Section $section, Assessment $assessment): void
    {
        $this->authorizeSection($section);
        abort_unless((int) $assessment->section_id === (int) $section->id, 404);
    }
}
