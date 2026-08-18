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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentReportController extends AssessmentModuleController
{
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

        $current = array_merge([
            'activity' => 20,
            'quiz' => 20,
            'exam' => 25,
            'project' => 20,
            'attendance' => 15,
            'recitation' => 5,
        ], $section->grading_weights ?? []);

        $data = $request->validate([
            'activity' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'quiz' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'exam' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'project' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'attendance' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'recitation' => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $merged = array_merge($current, $data);
        if (array_key_exists('recitation', $data)) {
            $merged['recitation'] = (int) ($data['recitation'] ?? 0);
        }

        $baseTotal = $merged['activity'] + $merged['quiz'] + $merged['exam'] + $merged['project'] + $merged['attendance'];
        $totalWithRec = $baseTotal + ($merged['recitation'] ?? 0);

        if ($baseTotal !== 100 && $totalWithRec !== 100) {
            return back()->withErrors(['weights' => 'Core coursework weights (Activity, Quiz, Exam, Project, Attendance) must total exactly 100%.']);
        }

        $section->update(['grading_weights' => $merged]);

        return back()->with('success', 'Grading weights and oral recitation bonus saved.');
    }

    private function render(Section $section, bool $print): Response
    {
        $this->authorizeSection($section);
        [$assessments, $students, $scores, $projects, $attendanceSessions, $recitations] = $this->data($section);

        $defaultWeights = [
            'activity' => 20,
            'quiz' => 20,
            'exam' => 25,
            'project' => 20,
            'attendance' => 15,
            'recitation' => 5,
        ];
        $gradingWeights = array_merge($defaultWeights, $section->grading_weights ?? []);
        $recitationBonusCap = (float) ($gradingWeights['recitation'] ?? 5);

        $categorySummary = collect(Assessment::TYPES)->mapWithKeys(fn ($type) => [$type => [
            'count' => 0,
            'possible' => 0.0,
        ]]);

        foreach ($assessments as $assessment) {
            $categorySummary[$assessment->type] = [
                'count' => $categorySummary[$assessment->type]['count'] + 1,
                'possible' => round($categorySummary[$assessment->type]['possible'] + (float) $assessment->max_points, 2),
            ];
        }

        // Project possible points
        $totalProjectPossible = round($projects->sum(fn ($p) => (float) ($p->max_points ?: 100)), 2);

        // Pre-build project scores map: [student_id => [project_id => score]]
        $studentProjectScores = [];
        foreach ($projects as $project) {
            $projMax = (float) ($project->max_points ?: 100);
            foreach ($project->groups as $group) {
                $groupScore = $group->score !== null ? (float) $group->score : null;
                foreach ($group->members as $member) {
                    $memberScore = $member->score !== null ? (float) $member->score : $groupScore;
                    $studentProjectScores[$member->student_id][$project->id] = $memberScore;
                }
            }
        }

        // Pre-build attendance map per student: [student_id => ['present' => int, 'late' => int, 'absent' => int]]
        $totalSessions = $attendanceSessions->count();
        $studentAttendance = [];
        foreach ($attendanceSessions as $session) {
            foreach ($session->records as $record) {
                if (! isset($studentAttendance[$record->student_id])) {
                    $studentAttendance[$record->student_id] = [
                        'present' => 0,
                        'late' => 0,
                        'absent' => 0,
                    ];
                }
                if ($record->status === AttendanceRecord::STATUS_PRESENT) {
                    $studentAttendance[$record->student_id]['present']++;
                } elseif ($record->status === AttendanceRecord::STATUS_LATE) {
                    $studentAttendance[$record->student_id]['late']++;
                } elseif ($record->status === AttendanceRecord::STATUS_ABSENT) {
                    $studentAttendance[$record->student_id]['absent']++;
                }
            }
        }

        $rows = $students->map(function ($student) use (
            $assessments,
            $scores,
            $categorySummary,
            $recitations,
            $projects,
            $studentProjectScores,
            $totalProjectPossible,
            $studentAttendance,
            $totalSessions,
            $recitationBonusCap
        ) {
            $studentScores = $scores->get($student->id, collect());
            $scoreGrid = [];
            $earnedByType = array_fill_keys(Assessment::TYPES, 0.0);
            $missingByType = array_fill_keys(Assessment::TYPES, 0);

            foreach ($assessments as $assessment) {
                $score = $studentScores->get($assessment->id)?->score;
                $scoreGrid[$assessment->id] = $score;
                $earnedByType[$assessment->type] += (float) ($score ?? 0);

                if ($score === null) {
                    $missingByType[$assessment->type]++;
                }
            }

            // Recitation stats: average out of 10, percentage, and earned additional bonus points
            $studentRecs = $recitations->get($student->id, collect());
            $recitationCount = $studentRecs->count();
            $recitationAvg = $recitationCount > 0 ? round((float) $studentRecs->avg('score'), 2) : null;
            $recitationPct = $recitationAvg !== null ? round(($recitationAvg / 10) * 100, 2) : null;
            $earnedBonus = $recitationAvg !== null && $recitationBonusCap > 0
                ? round(($recitationAvg / 10) * $recitationBonusCap, 2)
                : 0.0;

            $categories = collect(Assessment::TYPES)->mapWithKeys(function ($type) use ($categorySummary, $earnedByType, $missingByType, $earnedBonus) {
                $rawEarned = round($earnedByType[$type], 2);
                $bonusEarned = $type === 'activity' ? $earnedBonus : 0.0;
                $earned = round($rawEarned + $bonusEarned, 2);
                $possible = $categorySummary[$type]['possible'];

                return [$type => [
                    'raw_earned' => $rawEarned,
                    'bonus_earned' => $bonusEarned,
                    'earned' => $earned,
                    'possible' => $possible,
                    'percentage' => $possible > 0 ? min(100.0, round($earned / $possible * 100, 2)) : null,
                    'missing' => $missingByType[$type],
                ]];
            });

            // Project / Reporting stats
            $projScoresMap = $studentProjectScores[$student->id] ?? [];
            $projectEarned = 0.0;
            $projectMissing = 0;
            $projectScoreGrid = [];

            foreach ($projects as $proj) {
                $projScore = $projScoresMap[$proj->id] ?? null;
                $projectScoreGrid[$proj->id] = $projScore !== null ? round($projScore, 2) : null;
                if ($projScore !== null) {
                    $projectEarned += (float) $projScore;
                } else {
                    $projectMissing++;
                }
            }

            $projectPct = $totalProjectPossible > 0 && $projects->isNotEmpty()
                ? round(($projectEarned / $totalProjectPossible) * 100, 2)
                : null;

            // Attendance stats
            $att = $studentAttendance[$student->id] ?? ['present' => 0, 'late' => 0, 'absent' => 0];
            $presentCount = $att['present'];
            $lateCount = $att['late'];
            $absentCount = $att['absent'];
            $earnedAttendancePts = round(($presentCount * 1.0) + ($lateCount * 0.5), 1);
            $possibleAttendancePts = (float) $totalSessions;
            $attendancePct = $totalSessions > 0 ? round(($earnedAttendancePts / $totalSessions) * 100, 2) : null;

            return [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'scores' => $scoreGrid,
                'categories' => $categories,
                'project_scores' => $projectScoreGrid,
                'projectSummary' => [
                    'count' => $projects->count(),
                    'earned' => round($projectEarned, 2),
                    'possible' => $totalProjectPossible,
                    'percentage' => $projectPct,
                    'missing' => $projectMissing,
                ],
                'attendance' => [
                    'total_sessions' => $totalSessions,
                    'present_count' => $presentCount,
                    'late_count' => $lateCount,
                    'absent_count' => $absentCount,
                    'earned_points' => $earnedAttendancePts,
                    'possible_points' => $possibleAttendancePts,
                    'percentage' => $attendancePct,
                ],
                'recitation' => [
                    'count' => $recitationCount,
                    'avg_score' => $recitationAvg,
                    'percentage' => $recitationPct,
                    'bonus_points' => $earnedBonus,
                ],
            ];
        });

        return Inertia::render('reports/Gradebook', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'assessments' => $assessments,
            'projects' => $projects->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'title' => $p->title,
                'conducted_on' => $p->conducted_on?->toDateString(),
                'max_points' => $p->max_points ?: '100.00',
            ]),
            'rows' => $rows,
            'categorySummary' => $categorySummary,
            'projectSummary' => [
                'count' => $projects->count(),
                'possible' => $totalProjectPossible,
            ],
            'attendanceSummary' => [
                'total_sessions' => $totalSessions,
            ],
            'gradingWeights' => $gradingWeights,
            'printMode' => $print,
        ]);
    }

    /** @return array{Collection, Collection, Collection, Collection, Collection, Collection} */
    private function data(Section $section): array
    {
        $assessments = Assessment::where('section_id', $section->id)->orderBy('conducted_on')->orderBy('id')
            ->get(['id', 'type', 'title', 'conducted_on', 'max_points']);
        $students = Student::where('section_id', $section->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'student_number', 'first_name', 'middle_name', 'last_name']);
        $scores = AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))
            ->get(['assessment_id', 'student_id', 'score'])
            ->groupBy('student_id')
            ->map->keyBy('assessment_id');

        $projects = Project::where('section_id', $section->id)
            ->orderBy('conducted_on')
            ->orderBy('id')
            ->with(['groups.members'])
            ->get();

        $attendanceSessions = AttendanceSession::where('section_id', $section->id)
            ->with('records:id,attendance_session_id,student_id,status,attended_minutes')
            ->get();

        $recitations = Recitation::where('section_id', $section->id)
            ->get(['student_id', 'score'])
            ->groupBy('student_id');

        return [$assessments, $students, $scores, $projects, $attendanceSessions, $recitations];
    }
}
