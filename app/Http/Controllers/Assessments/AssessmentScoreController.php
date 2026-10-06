<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Requests\Assessments\UpdateAssessmentScoreRequest;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssessmentScoreController extends AssessmentModuleController
{
    public function update(
        UpdateAssessmentScoreRequest $request,
        Section $section,
        Assessment $assessment,
        Student $student,
    ): JsonResponse {
        $this->authorizeAssessment($section, $assessment);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        $score = $request->validated('score');
        if ($score !== null && (float) $score > (float) $assessment->max_points) {
            throw ValidationException::withMessages(['score' => "Score cannot exceed {$assessment->max_points}."]);
        }

        $absent = $assessment->attendance_session_id && AttendanceRecord::query()
            ->where('attendance_session_id', $assessment->attendance_session_id)
            ->where('student_id', $student->id)
            ->where('status', 'absent')
            ->exists();
        $override = $request->boolean('include_absent');

        if ($absent && ! $override) {
            throw ValidationException::withMessages(['score' => 'This student is absent. Enable the absent override to enter a score.']);
        }

        $remarks = $request->validated('remarks');

        $record = $assessment->scores()->updateOrCreate(
            ['student_id' => $student->id],
            [
                'score' => $score,
                'remarks' => $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null,
                'absence_override' => $absent && $override,
            ],
        );

        return response()->json([
            'student_id' => $student->id,
            'score' => $record->score,
            'remarks' => $record->remarks,
            'absence_override' => $record->absence_override,
            'saved_at' => $record->updated_at->toIso8601String(),
        ]);
    }

    /**
     * AI analyze the attached student document against the activity details and rubrics.
     */
    public function aiCheck(
        Request $request,
        Section $section,
        Assessment $assessment,
        Student $student,
        \App\Services\Autochecker\AiDocumentGraderService $aiGrader,
    ): JsonResponse {
        $this->authorizeAssessment($section, $assessment);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        $score = $assessment->scores()->where('student_id', $student->id)->first();
        if (! $score || ! $score->attachment_path) {
            return response()->json([
                'success' => false,
                'message' => 'No student output attached to evaluate.',
            ], 422);
        }

        try {
            $result = $aiGrader->gradeAssessmentSubmission($section, $assessment, $student);

            // Automatically save score & remarks to the database
            $score->update([
                'score' => $result['score'],
                'remarks' => $result['remarks'],
            ]);

            return response()->json([
                'success' => true,
                'message' => "AI graded {$student->full_name}: {$result['score']} pts. Score and remarks saved.",
                'student_id' => $student->id,
                'score' => $result['score'],
                'remarks' => $result['remarks'],
                'saved_at' => $score->updated_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function uploadAttachment(
        Request $request,
        Section $section,
        Assessment $assessment,
        Student $student,
    ): JsonResponse|RedirectResponse {
        $this->authorizeAssessment($section, $assessment);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        $request->validate([
            'attachment' => ['required', 'file', 'max:51200', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,rtf,odt,ods,odp,svg,gif,bmp,heic,pages,numbers,key,json,sql,db,sqlite,sqlite3'],
        ], [
            'attachment.max' => 'The student output file must not be larger than 50MB.',
            'attachment.extensions' => 'The attachment must be a valid file type.',
        ]);

        $score = $assessment->scores()->firstOrNew(['student_id' => $student->id]);

        $file = $request->file('attachment');
        $stored = app(\App\Services\SectionFolderService::class)->storeStudentAssessmentOutput($section, $assessment, $student, $file);

        $score->fill([
            'attachment_path' => $stored['path'],
            'attachment_name' => $stored['name'],
            'attachment_mime' => $stored['mime'],
        ]);
        $score->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Student output {$stored['name']} attached successfully.",
                'student_id' => $student->id,
                'attachment_path' => $score->attachment_path,
                'attachment_name' => $score->attachment_name,
                'attachment_mime' => $score->attachment_mime,
            ]);
        }

        return back()->with('success', "Student output {$stored['name']} attached successfully.");
    }

    public function destroyAttachment(
        Request $request,
        Section $section,
        Assessment $assessment,
        Student $student,
    ): JsonResponse|RedirectResponse {
        $this->authorizeAssessment($section, $assessment);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        $score = $assessment->scores()->where('student_id', $student->id)->first();
        if ($score && $score->attachment_path) {
            Storage::disk('local')->delete($score->attachment_path);
            $score->update([
                'attachment_path' => null,
                'attachment_name' => null,
                'attachment_mime' => null,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Student output deleted.',
                'student_id' => $student->id,
            ]);
        }

        return back()->with('success', 'Student output deleted.');
    }

    public function streamAttachment(
        Request $request,
        Section $section,
        Assessment $assessment,
        Student $student,
    ): BinaryFileResponse|\Symfony\Component\HttpFoundation\Response {
        $this->authorizeAssessment($section, $assessment);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        $score = $assessment->scores()->where('student_id', $student->id)->first();
        abort_unless($score && $score->attachment_path, 404, 'No student output attached.');

        $path = $score->attachment_path;
        $fullPath = null;
        if (Storage::disk('local')->exists($path)) {
            $fullPath = Storage::disk('local')->path($path);
        } elseif (file_exists(storage_path('app/'.$path))) {
            $fullPath = storage_path('app/'.$path);
        }

        abort_unless($fullPath && file_exists($fullPath), 404, 'Student output file not found on disk.');

        $name = $score->attachment_name ?: basename($path);
        $mime = $score->attachment_mime ?: (File::mimeType($fullPath) ?: 'application/octet-stream');

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

    public function batchUpdate(
        Request $request,
        Section $section,
        Assessment $assessment,
    ): JsonResponse|RedirectResponse {
        $this->authorizeAssessment($section, $assessment);

        $validated = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['nullable'],
            'remarks' => ['nullable', 'array'],
            'remarks.*' => ['nullable', 'string', 'max:10000'],
            'include_absent' => ['nullable', 'boolean'],
        ]);

        $includeAbsent = (bool) ($validated['include_absent'] ?? false);
        $rawScores = $validated['scores'];
        $rawRemarks = $validated['remarks'] ?? [];

        // Ensure students belong to this section
        $studentIds = array_map('intval', array_keys($rawScores));
        $validMap = array_flip(
            Student::query()
                ->where('section_id', $section->id)
                ->whereIn('id', $studentIds)
                ->pluck('id')
                ->all()
        );

        $absentMap = [];
        if ($assessment->attendance_session_id) {
            $absentMap = array_flip(
                AttendanceRecord::query()
                    ->where('attendance_session_id', $assessment->attendance_session_id)
                    ->where('status', 'absent')
                    ->pluck('student_id')
                    ->all()
            );
        }

        DB::transaction(function () use ($assessment, $rawScores, $rawRemarks, $validMap, $absentMap, $includeAbsent) {
            foreach ($rawScores as $studentId => $rawVal) {
                $sId = (int) $studentId;
                if (! isset($validMap[$sId])) {
                    continue;
                }

                $isAbsent = isset($absentMap[$sId]);
                if ($isAbsent && ! $includeAbsent) {
                    continue;
                }

                if ($rawVal === null || $rawVal === '' || trim((string) $rawVal) === '') {
                    $numericScore = null;
                } else {
                    $numericScore = round((float) $rawVal, 2);
                    if ($numericScore < 0 || $numericScore > (float) $assessment->max_points) {
                        throw ValidationException::withMessages([
                            "scores.{$sId}" => "Score must be between 0 and {$assessment->max_points}.",
                        ]);
                    }
                }

                $remarkVal = isset($rawRemarks[$sId]) ? trim((string) $rawRemarks[$sId]) : null;
                if ($remarkVal === '') {
                    $remarkVal = null;
                }

                $assessment->scores()->updateOrCreate(
                    ['student_id' => $sId],
                    [
                        'score' => $numericScore,
                        'remarks' => $remarkVal,
                        'absence_override' => $isAbsent && $includeAbsent,
                    ]
                );
            }
        });

        if ($request->wantsJson()) {
            $saved = $assessment->scores()->pluck('score', 'student_id');
            $savedRemarks = $assessment->scores()->pluck('remarks', 'student_id');

            return response()->json([
                'success' => true,
                'message' => 'All scores have been saved successfully.',
                'scores' => $saved,
                'remarks' => $savedRemarks,
            ]);
        }

        return back()->with('success', 'All scores have been saved successfully.');
    }
}
