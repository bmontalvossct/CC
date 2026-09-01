<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use App\Services\GradebookCalculationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentReportController extends AssessmentModuleController
{
    public function __construct(
        protected GradebookCalculationService $gradebookService
    ) {
    }

    public function gradebook(Section $section): Response
    {
        return $this->render($section, false);
    }

    public function print(Section $section): Response
    {
        return $this->render($section, true);
    }

    public function updateWeights(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section);

        $current = array_merge(GradebookCalculationService::DEFAULT_WEIGHTS, $section->grading_weights ?? []);

        // Pre-cast all incoming numbers to integer
        $input = $request->all();
        foreach (['activity', 'laboratory', 'quiz', 'exam', 'project', 'attendance', 'recitation'] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key])) {
                $input[$key] = (int) $input[$key];
            }
        }
        $request->merge($input);

        $data = $request->validate([
            'activity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'laboratory' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'quiz' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'exam' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'project' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'attendance' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'recitation' => ['nullable', 'integer', 'min:0'],
        ]);

        $merged = [
            'activity' => isset($data['activity']) && $data['activity'] !== null ? (int) $data['activity'] : (int) ($current['activity'] ?? 20),
            'laboratory' => isset($data['laboratory']) && $data['laboratory'] !== null ? (int) $data['laboratory'] : (int) ($current['laboratory'] ?? 0),
            'quiz' => isset($data['quiz']) && $data['quiz'] !== null ? (int) $data['quiz'] : (int) ($current['quiz'] ?? 20),
            'exam' => isset($data['exam']) && $data['exam'] !== null ? (int) $data['exam'] : (int) ($current['exam'] ?? 25),
            'project' => isset($data['project']) && $data['project'] !== null ? (int) $data['project'] : (int) ($current['project'] ?? 20),
            'attendance' => isset($data['attendance']) && $data['attendance'] !== null ? (int) $data['attendance'] : (int) ($current['attendance'] ?? 15),
            'recitation' => array_key_exists('recitation', $data) ? (int) ($data['recitation'] ?? 0) : (int) ($current['recitation'] ?? 5),
        ];

        $baseTotal = $merged['activity'] + $merged['laboratory'] + $merged['quiz'] + $merged['exam'] + $merged['project'] + $merged['attendance'];
        $totalWithRec = $baseTotal + $merged['recitation'];

        if ($baseTotal !== 100 && $totalWithRec !== 100) {
            return back()->withErrors([
                'weights' => "Core coursework weights currently total {$baseTotal}%. Core weights must equal exactly 100%.",
            ]);
        }

        $section->update(['grading_weights' => $merged]);

        return back()->with('success', 'Grading weights and oral recitation bonus saved.');
    }

    public function overrideOralPoints(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section);

        $validated = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'apply_to_all' => ['nullable', 'boolean'],
            'points' => ['required', 'numeric', 'min:0'],
            'include_late' => ['nullable', 'boolean'],
        ]);

        $includeLate = ! empty($validated['include_late']);
        $points = (float) $validated['points'];
        $applyToAll = ! empty($validated['apply_to_all']);

        $studentQuery = Student::where('section_id', $section->id)->where('is_active', true);
        if (! $applyToAll && ! empty($validated['student_id'])) {
            $students = $studentQuery->where('id', $validated['student_id'])->get();
            if ($students->isEmpty()) {
                return back()->withErrors(['student_id' => 'Selected student not found in this section.']);
            }
        } else {
            $students = $studentQuery->orderBy('last_name')->orderBy('first_name')->get();
        }

        if ($students->isEmpty()) {
            return back()->withErrors(['points' => 'No active students found in this section.']);
        }

        $eligibleStatuses = [AttendanceRecord::STATUS_PRESENT];
        if ($includeLate) {
            $eligibleStatuses[] = AttendanceRecord::STATUS_LATE;
        }

        // For a single student, validate days and max points strictly
        if (! $applyToAll && $students->count() === 1) {
            $singleStudent = $students->first();
            $dates = DB::table('attendance_records')
                ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
                ->where('attendance_sessions.section_id', $section->id)
                ->where('attendance_records.student_id', $singleStudent->id)
                ->whereIn('attendance_records.status', $eligibleStatuses)
                ->orderBy('attendance_sessions.session_date')
                ->pluck('attendance_sessions.session_date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->values();

            $daysCount = $dates->count();
            if ($daysCount === 0) {
                return back()->withErrors([
                    'points' => "{$singleStudent->full_name} has 0 recorded eligible attendance days. Oral points can only be allocated to days present.",
                ]);
            }

            $maxAllowed = (float) ($daysCount * 10);
            if ($points > $maxAllowed) {
                return back()->withErrors([
                    'points' => "Points for {$singleStudent->full_name} ({$points} pts) cannot surpass the maximum oral points ({$maxAllowed} pts across {$daysCount} present days at max 10 pts/day).",
                ]);
            }

            // Distribute points across eligible days
            $this->distributeOralPoints($section->id, $singleStudent->id, $dates->all(), $points);

            return back()->with('success', "Oral points successfully overridden for {$singleStudent->full_name} ({$points} pts allocated across {$daysCount} present day(s) at max 10/day).");
        }

        // For all students (or multiple), distribute points based on each student's eligible days
        $appliedCount = 0;
        foreach ($students as $student) {
            $dates = DB::table('attendance_records')
                ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
                ->where('attendance_sessions.section_id', $section->id)
                ->where('attendance_records.student_id', $student->id)
                ->whereIn('attendance_records.status', $eligibleStatuses)
                ->orderBy('attendance_sessions.session_date')
                ->pluck('attendance_sessions.session_date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->values();

            $daysCount = $dates->count();
            if ($daysCount > 0) {
                $cappedPoints = min($points, (float) ($daysCount * 10));
                $this->distributeOralPoints($section->id, $student->id, $dates->all(), $cappedPoints);
                $appliedCount++;
            }
        }

        return back()->with('success', "Oral points successfully overridden for {$appliedCount} student(s) (allocated equally across present days, max 10/day).");
    }

    private function render(Section $section, bool $print): Response
    {
        $this->authorizeSection($section);
        $data = $this->gradebookService->calculateGradebook($section);

        return Inertia::render('reports/Gradebook', array_merge($data, [
            'printMode' => $print,
        ]));
    }

    private function distributeOralPoints(int $sectionId, int $studentId, array $dates, float $totalPoints): void
    {
        $count = count($dates);
        if ($count === 0 || $totalPoints <= 0) {
            return;
        }

        $base = floor(($totalPoints / $count) * 100) / 100;
        $remainder = round($totalPoints - ($base * $count), 2);

        foreach ($dates as $index => $date) {
            $score = $base;
            if ($index === 0) {
                $score = round($score + $remainder, 2);
            }
            $score = min(10.0, max(0.0, $score));

            Recitation::updateOrCreate(
                [
                    'section_id' => $sectionId,
                    'student_id' => $studentId,
                    'conducted_on' => $date,
                ],
                [
                    'score' => $score,
                    'accuracy' => (int) round($score / 2),
                    'delivery' => (int) round($score / 2),
                    'comments' => 'Manual gradebook oral override',
                ]
            );
        }
    }
}
