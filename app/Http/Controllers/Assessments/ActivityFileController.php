<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\Project;
use App\Models\Section;
use App\Services\Autochecker\ExamDocxExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ActivityFileController extends AssessmentModuleController
{
    public function assessment(Request $request, Section $section, Assessment $assessment)
    {
        $this->authorizeAssessment($section, $assessment);

        return $this->handleFile($request, $section, $assessment);
    }

    public function project(Request $request, Section $section, Project $project)
    {
        $this->authorizeSection($section);
        abort_unless((int) $project->section_id === (int) $section->id, 404);

        return $this->handleFile($request, $section, $project);
    }

    private function handleFile(Request $request, Section $section, Assessment|Project $activity)
    {
        if ($request->isMethod('get')) {
            abort_unless($activity->activity_file_path && Storage::disk('local')->exists($activity->activity_file_path), 404);
            $path = Storage::disk('local')->path($activity->activity_file_path);
            $name = $activity->activity_file_name;

            if ($request->get('format') === 'docx' || $request->has('docx')) {
                if (str_ends_with(strtolower($activity->activity_file_name ?? ''), '.docx')) {
                    return response()->download($path, $name);
                }

                $dir = dirname($activity->activity_file_path);
                $files = Storage::disk('local')->files($dir);
                foreach ($files as $f) {
                    if (str_ends_with(strtolower($f), '.docx')) {
                        return response()->download(Storage::disk('local')->path($f), basename($f));
                    }
                }

                $content = Storage::disk('local')->get($activity->activity_file_path);
                $docxService = app(ExamDocxExportService::class);
                $docPath = $docxService->generateDocx([
                    'title' => $activity->title,
                    'subject_code' => $section->subject_code,
                    'subject_title' => $section->subject_title,
                    'section_name' => $section->name,
                    'max_points' => $activity->max_points ?? null,
                    'exam_content' => $content,
                ]);
                $downloadName = (Str::slug($activity->title) ?: 'assessment').'.docx';

                return response()->download($docPath, $downloadName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])->deleteFileAfterSend(true);
            }

            if ($request->has('download')) {
                return response()->download($path, $name);
            }

            return response()->file($path, [
                'Content-Type' => $activity->activity_file_mime ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $request->validate([
            'attachment' => ['required', 'file', 'max:51200', 'extensions:pdf,docx,txt,md,csv'],
        ], ['attachment.extensions' => 'Upload a PDF, DOCX, TXT, Markdown, or CSV instructions file.']);

        $file = $request->file('attachment');
        $kind = $activity instanceof Assessment ? 'assessment' : 'project';
        $path = $file->storeAs(
            'sections/section-'.$section->id.'/'.$kind.'-'.$activity->id.'/instructions',
            Str::uuid().'.'.strtolower($file->getClientOriginalExtension()),
            'local',
        );
        abort_unless($path, 500, 'The activity instructions could not be stored. Please retry.');
        $activity->forceFill([
            'activity_file_path' => $path,
            'activity_file_name' => $file->getClientOriginalName(),
            'activity_file_mime' => $file->getMimeType(),
        ])->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Activity instructions uploaded successfully.']);
        }

        return back()->with('success', 'Activity instructions uploaded successfully.');
    }
}
