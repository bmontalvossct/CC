<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\CourseModule;
use App\Models\Section;
use App\Services\Autochecker\ExamDocxExportService;
use App\Services\Autochecker\ExamGeneratorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamGeneratorController extends AssessmentModuleController
{
    public function __construct(
        protected ExamGeneratorService $examGeneratorService,
        protected ExamDocxExportService $examDocxExportService,
    ) {}

    /**
     * Get the available course modules for this section with file and metadata indicators.
     */
    public function modules(Section $section): JsonResponse
    {
        $this->authorizeSection($section);

        $modules = $section->courseModules()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CourseModule $m) => [
                'id' => $m->id,
                'section_id' => $m->section_id,
                'module_number' => $m->module_number,
                'title' => $m->title,
                'description' => $m->description,
                'link_url' => $m->link_url,
                'has_file' => ! empty($m->file_path) && Storage::disk('local')->exists($m->file_path),
                'file_name' => $m->file_name,
                'file_size' => $m->file_size,
                'formatted_file_size' => $m->formatted_file_size,
                'file_mime' => $m->file_mime,
                'sort_order' => $m->sort_order,
            ]);

        return response()->json([
            'modules' => $modules,
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'subject_code' => $section->subject_code,
                'subject_title' => $section->subject_title,
            ],
        ]);
    }

    /**
     * Check connection status to Ollama and verify Hermes 3 model availability.
     */
    public function status(Section $section): JsonResponse
    {
        $this->authorizeSection($section);

        $status = $this->examGeneratorService->getHermesStatus();

        return response()->json($status);
    }

    /**
     * Stream live exam draft generation using Hermes 3 via NDJSON.
     */
    public function generate(Request $request, Section $section): StreamedResponse|JsonResponse
    {
        $this->authorizeSection($section);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'term_period' => ['nullable', 'string', 'in:midterm,final,prelim'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['integer'],
            'provider' => ['nullable', 'string', 'in:auto,gemini,ollama'],
            'test_types' => ['required', 'array', 'min:1'],
            'test_types.*.type' => ['required', 'string'],
            'test_types.*.label' => ['nullable', 'string', 'max:100'],
            'test_types.*.items_count' => ['required', 'integer', 'min:1', 'max:150'],
            'test_types.*.points_per_item' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'difficulty' => ['nullable', 'string', 'in:balanced,conceptual,rigorous'],
            'instructions' => ['nullable', 'string', 'max:3000'],
        ], [
            'test_types.min' => 'Please configure at least one test section (e.g. Identification, Enumeration).',
        ]);

        // Load modules owned by this section if specified
        $moduleIds = $validated['module_ids'] ?? [];
        if (! empty($moduleIds)) {
            $selectedModules = $section->courseModules()
                ->whereIn('id', $moduleIds)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($selectedModules->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'None of the selected course modules were found in this section.',
                ], 422);
            }
        } else {
            $selectedModules = collect();
        }

        // Calculate total items and total points
        $totalItems = 0;
        $totalPoints = 0.0;
        foreach ($validated['test_types'] as $tt) {
            $count = (int) $tt['items_count'];
            $pts = (float) $tt['points_per_item'];
            $totalItems += $count;
            $totalPoints += ($count * $pts);
        }

        $config = array_merge($validated, [
            'total_items' => $totalItems,
            'total_points' => $totalPoints,
        ]);

        return new StreamedResponse(function () use ($section, $selectedModules, $config) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }

            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            if (function_exists('ob_implicit_flush')) {
                @ob_implicit_flush(true);
            }

            try {
                $generator = $this->examGeneratorService->streamGenerateExam($section, $selectedModules, $config);

                foreach ($generator as $event) {
                    echo json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    @flush();
                }
            } catch (Exception $e) {
                echo json_encode([
                    'type' => 'error',
                    'message' => 'Hermes generation error: '.$e->getMessage(),
                ])."\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Save the generated examination as a new official Section Assessment.
     */
    public function saveAssessment(Request $request, Section $section): JsonResponse
    {
        $this->authorizeSection($section);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'term_period' => ['nullable', 'string', 'in:midterm,final,prelim'],
            'conducted_on' => ['nullable', 'date'],
            'max_points' => ['required', 'numeric', 'min:1', 'max:1000'],
            'exam_content' => ['required', 'string'],
            'answer_key' => ['nullable', 'string'],
            'structured_rubric' => ['nullable', 'array'],
        ]);

        $termPeriod = $validated['term_period'] ?? 'midterm';
        $existingExamsCount = $section->assessments()->where('type', 'exam')->count();
        $assessmentNumber = 'Exam '.($existingExamsCount + 1);

        // Prepare rubric data
        $rubricData = $validated['structured_rubric'] ?? [];
        if (! isset($rubricData['raw_text']) && ! empty($validated['answer_key'])) {
            $rubricData['raw_text'] = $validated['answer_key'];
        }
        if (! isset($rubricData['rubric_type'])) {
            $rubricData['rubric_type'] = 'answer_key';
        }

        // Create the assessment record
        $assessment = $section->assessments()->create([
            'type' => 'exam',
            'term_period' => $termPeriod,
            'assessment_number' => $assessmentNumber,
            'title' => trim($validated['title']),
            'description' => 'Hermes-generated examination with structured questionnaire and answer key.',
            'conducted_on' => $validated['conducted_on'] ?? now()->toDateString(),
            'max_points' => $validated['max_points'],
            'rubric_type' => 'answer_key',
            'rubric_data' => $rubricData,
        ]);

        // Save generated markdown as instruction/activity backup
        $slug = Str::slug($assessment->title) ?: 'examination';
        $folderPath = "sections/section-{$section->id}/assessment-{$assessment->id}/instructions";
        $mdFileName = "{$slug}-questionnaire.md";
        $mdFilePath = "{$folderPath}/{$mdFileName}";

        $examDocumentContent = '# '.$assessment->title."\n\n".trim($validated['exam_content']);
        if (! empty($validated['answer_key'])) {
            $examDocumentContent .= "\n\n---\n\n## Answer Key & Grading Rubric\n\n".trim($validated['answer_key']);
        }

        Storage::disk('local')->put($mdFilePath, $examDocumentContent);

        // Pre-generate official Word (.docx) document alongside markdown
        try {
            $docxTempPath = $this->examDocxExportService->generateDocx([
                'title' => $assessment->title,
                'subject_code' => $section->subject_code,
                'subject_title' => $section->subject_title,
                'section_name' => $section->name,
                'max_points' => $assessment->max_points,
                'exam_content' => $validated['exam_content'],
                'answer_key' => $validated['answer_key'] ?? null,
                'mode' => ! empty($validated['answer_key']) ? 'both' : 'student',
            ]);

            $docxFileName = "{$slug}-examination.docx";
            $docxFilePath = "{$folderPath}/{$docxFileName}";
            Storage::disk('local')->put($docxFilePath, file_get_contents($docxTempPath));
            @unlink($docxTempPath);
        } catch (Exception) {
            // Ignore pre-generation failure
        }

        $assessment->update([
            'activity_file_path' => $mdFilePath,
            'activity_file_name' => $mdFileName,
            'activity_file_mime' => 'text/markdown',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Examination '{$assessment->title}' created successfully.",
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'type' => $assessment->type,
                'max_points' => (float) $assessment->max_points,
                'term_period' => $assessment->term_period,
            ],
            'redirect_url' => route('sections.assessments.show', [$section, $assessment]),
        ]);
    }

    /**
     * Export finalized examination content into a formatted Microsoft Word (.docx) document.
     */
    public function exportDocx(Request $request, Section $section): BinaryFileResponse|JsonResponse
    {
        $this->authorizeSection($section);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'exam_content' => ['required', 'string'],
            'answer_key' => ['nullable', 'string'],
            'mode' => ['nullable', 'string', 'in:student,both,answers'],
            'max_points' => ['nullable', 'numeric'],
        ]);

        $mode = $validated['mode'] ?? 'student';
        $title = trim($validated['title']);

        try {
            $filePath = $this->examDocxExportService->generateDocx([
                'title' => $title,
                'subject_code' => $section->subject_code,
                'subject_title' => $section->subject_title,
                'section_name' => $section->name,
                'max_points' => $validated['max_points'] ?? null,
                'exam_content' => $validated['exam_content'],
                'answer_key' => $validated['answer_key'] ?? null,
                'mode' => $mode,
            ]);

            // Construct clean download filename
            $slug = Str::slug($title) ?: 'examination';
            $suffix = match ($mode) {
                'both' => '-with-answer-key',
                'answers' => '-answer-key',
                default => '-student-questionnaire',
            };
            $downloadName = "{$slug}{$suffix}.docx";

            return response()->download($filePath, $downloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])->deleteFileAfterSend(true);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Word document: '.$e->getMessage(),
            ], 500);
        }
    }
}
