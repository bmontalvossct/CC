<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use App\Services\Autochecker\DockerSandboxRunner;
use App\Services\Autochecker\OllamaClient;
use App\Services\Autochecker\OllamaService;
use App\Services\Autochecker\TempRunManager;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutocheckerController extends AssessmentModuleController
{
    public function __construct(
        protected OllamaClient $ollamaClient,
        protected OllamaService $ollamaService,
        protected TempRunManager $tempRunManager,
        protected DockerSandboxRunner $sandboxRunner,
    ) {
    }

    /**
     * Get Autochecker status, available models, and Python sandbox availability.
     */
    public function status(Section $section, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($section, $assessment);

        $ping = $this->ollamaClient->ping();
        $models = $ping['online'] ? $this->ollamaClient->getModels() : [];
        $sandboxStatus = $this->sandboxRunner->getStatus();

        return response()->json([
            'ollama' => $ping,
            'models' => $models,
            'active_profiles' => [
                'code_grading' => $this->ollamaClient->resolveProfileModel('code_grading'),
                'general_grading' => $this->ollamaClient->resolveProfileModel('general_grading'),
            ],
            'is_local' => $this->ollamaClient->isLocalEndpoint(),
            'sandbox' => $sandboxStatus,
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'type' => $assessment->type,
                'max_points' => (float) $assessment->max_points,
                'description' => $assessment->description,
            ],
            'students_count' => $section->students()->where('is_active', true)->count(),
        ]);
    }

    /**
     * Inspect bulk uploaded files or zip, create server-side opaque run, and return item previews.
     */
    public function inspectFiles(Request $request, Section $section, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($section, $assessment);

        $request->validate([
            'files' => ['nullable', 'array', 'max:' . config('autochecker.limits.max_direct_files', 20)],
            'files.*' => ['file', 'max:' . config('autochecker.limits.max_file_size_kb', 10240)],
            'zip_file' => ['nullable', 'file', 'mimes:zip', 'max:' . config('autochecker.limits.max_total_expanded_kb', 102400)],
        ]);

        $students = $section->students()->where('is_active', true)->orderBy('last_name')->get();

        try {
            $runData = $this->tempRunManager->createRun(
                userId: $request->user()->id,
                assessmentId: $assessment->id,
                students: $students,
                files: $request->file('files'),
                zipFile: $request->file('zip_file')
            );

            return response()->json($runData);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422;
            return response()->json([
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * Evaluate a single submission item from an active temporary run.
     */
    public function evaluateSingle(Request $request, Section $section, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($section, $assessment);

        $validated = $request->validate([
            'run_id' => ['required', 'string'],
            'item_id' => ['required', 'string'],
            'rubric_criteria' => ['required', 'array', 'min:1'],
            'rubric_criteria.*.id' => ['required', 'string'],
            'rubric_criteria.*.name' => ['required', 'string'],
            'rubric_criteria.*.max_points' => ['required', 'numeric', 'min:0.01'],
            'rubric_criteria.*.description' => ['nullable', 'string'],
            'reference_solution' => ['nullable', 'string'],
            'assessment_instructions' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $runId = $validated['run_id'];
        $itemId = $validated['item_id'];

        $content = $this->tempRunManager->getItemContentForGrading($runId, $itemId, $user->id);
        if ($content === null) {
            return response()->json(['error' => 'Submission item not found in temporary run.'], 404);
        }

        $manifest = $this->tempRunManager->getRun($runId, $user->id);
        $item = $manifest['items'][$itemId] ?? null;
        $filename = $item['filename'] ?? 'submission.txt';

        try {
            $result = $this->ollamaService->evaluateSubmission(
                content: $content,
                filename: $filename,
                maxPoints: (float) $assessment->max_points,
                rubricCriteria: $validated['rubric_criteria'],
                referenceSolution: $validated['reference_solution'] ?? null,
                assessmentInstructions: $validated['assessment_instructions'] ?? $assessment->description
            );

            // Cache proposal in temporary run manifest
            $this->tempRunManager->saveItemProposal($runId, $itemId, $user->id, $result);

            return response()->json([
                'item_id' => $itemId,
                'evaluation' => $result,
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * Run isolated Python tests against an item in the sandbox.
     */
    public function runPythonSandbox(Request $request, Section $section, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($section, $assessment);

        $validated = $request->validate([
            'run_id' => ['required', 'string'],
            'item_id' => ['required', 'string'],
            'test_cases' => ['required', 'array', 'min:1'],
            'test_cases.*.name' => ['required', 'string'],
            'test_cases.*.stdin' => ['nullable', 'string'],
            'test_cases.*.expected_output' => ['nullable', 'string'],
            'test_cases.*.points' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $content = $this->tempRunManager->getItemContentForGrading($validated['run_id'], $validated['item_id'], $user->id);

        if ($content === null) {
            return response()->json(['error' => 'Submission item not found in temporary run.'], 404);
        }

        $results = $this->sandboxRunner->runPythonTests($content, $validated['test_cases']);

        return response()->json($results);
    }

    /**
     * Atomically save teacher-approved final scores into the official assessment grade ledger.
     */
    public function applyScores(Request $request, Section $section, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($section, $assessment);

        $validated = $request->validate([
            'run_id' => ['nullable', 'string'],
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.student_id' => ['required', 'integer'],
            'scores.*.approved' => ['required', 'boolean'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:' . $assessment->max_points],
            'scores.*.remarks' => ['nullable', 'string', 'max:500'],
            'scores.*.overwrite_confirmed' => ['nullable', 'boolean'],
            'scores.*.absence_override_confirmed' => ['nullable', 'boolean'],
        ]);

        $students = $section->students()->where('is_active', true)->get()->keyBy('id');
        $existingScores = $assessment->scores()->get()->keyBy('student_id');

        $appliedCount = 0;
        $rejectedCount = 0;
        $maxPoints = (float) $assessment->max_points;

        DB::beginTransaction();

        try {
            foreach ($validated['scores'] as $item) {
                if (empty($item['approved']) || ! isset($item['score']) || $item['score'] === null) {
                    continue;
                }

                $studentId = (int) $item['student_id'];
                if (! isset($students[$studentId])) {
                    throw new Exception("Student ID {$studentId} does not belong to section {$section->name}.", 422);
                }

                $finalScore = min($maxPoints, max(0.0, round((float) $item['score'], 2)));
                $remarks = ! empty($item['remarks']) ? mb_substr(trim($item['remarks']), 0, 500) : null;

                // Check existing score overwrite
                if (isset($existingScores[$studentId]) && $existingScores[$studentId]->score !== null) {
                    if (empty($item['overwrite_confirmed'])) {
                        // Skip unconfirmed overwrite
                        continue;
                    }
                }

                // Check attendance absences
                if ($assessment->attendance_session_id) {
                    $isAbsent = AttendanceRecord::where('attendance_session_id', $assessment->attendance_session_id)
                        ->where('student_id', $studentId)
                        ->where('status', AttendanceRecord::STATUS_ABSENT)
                        ->exists();

                    if ($isAbsent && empty($item['absence_override_confirmed'])) {
                        continue;
                    }
                }

                AssessmentScore::updateOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'score' => $finalScore,
                        'remarks' => $remarks,
                    ]
                );

                $appliedCount++;
            }

            DB::commit();

            // Clean up temporary run on successful sync
            if (! empty($validated['run_id'])) {
                $this->tempRunManager->deleteRun($validated['run_id']);
            }

            return response()->json([
                'success' => true,
                'applied_count' => $appliedCount,
                'message' => "Successfully synced {$appliedCount} approved scores into the gradebook ledger.",
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Autochecker applyScores error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
