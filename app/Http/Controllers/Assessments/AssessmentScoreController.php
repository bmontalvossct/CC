<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Requests\Assessments\UpdateAssessmentScoreRequest;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
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

        $record = $assessment->scores()->updateOrCreate(
            ['student_id' => $student->id],
            ['score' => $score, 'absence_override' => $absent && $override],
        );

        return response()->json([
            'student_id' => $student->id,
            'score' => $record->score,
            'absence_override' => $record->absence_override,
            'saved_at' => $record->updated_at->toIso8601String(),
        ]);
    }
}
