<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AssessmentAttachmentController extends AssessmentModuleController
{
    public function __invoke(Request $request, Section $section, Assessment $assessment): BinaryFileResponse|Response
    {
        $this->authorizeAssessment($section, $assessment);

        $path = $assessment->attachment_path;
        abort_unless($path, 404);

        $fullPath = null;
        if (Storage::disk('local')->exists($path)) {
            $fullPath = Storage::disk('local')->path($path);
        } elseif (Storage::disk('public')->exists($path)) {
            $fullPath = Storage::disk('public')->path($path);
        } elseif (file_exists(storage_path('app/'.$path))) {
            $fullPath = storage_path('app/'.$path);
        }

        abort_unless($fullPath && file_exists($fullPath), 404, 'Attachment file not found on disk.');

        $name = $assessment->attachment_name ?: basename($path);
        $mime = $assessment->attachment_mime ?: (File::mimeType($fullPath) ?: 'application/octet-stream');

        if ($request->boolean('download') || $request->has('download')) {
            return response()->download($fullPath, $name, [
                'Content-Type' => $mime,
            ]);
        }

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
        ]);
    }
}
