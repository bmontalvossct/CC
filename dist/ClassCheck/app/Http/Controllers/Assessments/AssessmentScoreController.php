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
use Illuminate\Validation\ValidationException;

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
            'remarks.*' => ['nullable', 'string', 'max:500'],
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
