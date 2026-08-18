<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentAttachmentController extends AssessmentModuleController
{
    public function __invoke(Request $request, Section $section, Assessment $assessment): StreamedResponse|BinaryFileResponse|Response
    {
        $this->authorizeAssessment($section, $assessment);
        abort_unless($assessment->attachment_path && Storage::disk('local')->exists($assessment->attachment_path), 404);

        $name = $assessment->attachment_name ?? basename($assessment->attachment_path);
        $mime = $assessment->attachment_mime ?? Storage::disk('local')->mimeType($assessment->attachment_path) ?? 'application/octet-stream';

        if ($request->boolean('download')) {
            return Storage::disk('local')->download(
                $assessment->attachment_path,
                $name,
                ['Content-Type' => $mime]
            );
        }

        return Storage::disk('local')->response(
            $assessment->attachment_path,
            $name,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            ]
        );
    }
}
