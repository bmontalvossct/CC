<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\Section;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentAttachmentController extends AssessmentModuleController
{
    public function __invoke(Section $section, Assessment $assessment): StreamedResponse
    {
        $this->authorizeAssessment($section, $assessment);
        abort_unless($assessment->attachment_path && Storage::disk('local')->exists($assessment->attachment_path), 404);

        return Storage::disk('local')->download(
            $assessment->attachment_path,
            $assessment->attachment_name ?? basename($assessment->attachment_path),
            ['Content-Type' => $assessment->attachment_mime ?? 'application/octet-stream'],
        );
    }
}
