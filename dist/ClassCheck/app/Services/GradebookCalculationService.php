<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Collection;

class GradebookCalculationService
{
    public const DEFAULT_WEIGHTS = [
        'activity' => 20,
        'laboratory' => 0,
        'quiz' => 20,
        'exam' => 25,
        'project' => 20,
        'attendance' => 15,
        'recitation' => 5,
    ];

    /**
     * Compute full gradebook matrix and metrics for a section.
     *
     * @return array{
     *     section: array,
     *     assessments: Collection,
     *     groupActivities: Collection,
     *     projects: Collection,
     *     rows: Collection,
     *     categorySummary: Collection,
     *     projectSummary: array,
     *     attendanceSummary: array,
     *     gradingWeights: array
     * }
     */
    public function calculateGradebook(Section $section): array
    {
        [$assessments, $students, $scores, $projects, $attendanceSessions, $recitations] = $this->loadData($section);

        $gradingWeights = array_merge(self::DEFAULT_WEIGHTS, $section->grading_weights ?? []);
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

        $groupActivities = $projects->where('type', 'group_activity');
        $regularProjects = $projects->whereIn('type', ['project', 'reporting']);

        foreach ($groupActivities as $gAct) {
            $categorySummary['activity'] = [
                'count' => $categorySummary['activity']['count'] + 1,
                'possible' => round($categorySummary['activity']['possible'] + (float) ($gAct->max_points ?: 100), 2),
            ];
        }

        $totalProjectPossible = round($regularProjects->sum(fn ($p) => (float) ($p->max_points ?: 100)), 2);

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

        $totalSessions = $attendanceSessions->count();
        $studentAttendance = [];
        foreach ($attendanceSessions as $session) {
            $sessionDate = $session->session_date->toDateString();
            foreach ($session->records as $record) {
                if (! isset($studentAttendance[$record->student_id])) {
                    $studentAttendance[$record->student_id] = [
                        'present' => 0,
                        'late' => 0,
                        'absent' => 0,
                        'present_dates' => [],
                        'late_dates' => [],
                    ];
                }
                if ($record->status === AttendanceRecord::STATUS_PRESENT) {
                    $studentAttendance[$record->student_id]['present']++;
                    $studentAttendance[$record->student_id]['present_dates'][] = $sessionDate;
                } elseif ($record->status === AttendanceRecord::STATUS_LATE) {
                    $studentAttendance[$record->student_id]['late']++;
                    $studentAttendance[$record->student_id]['late_dates'][] = $sessionDate;
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
            $groupActivities,
            $regularProjects,
            $studentProjectScores,
            $totalProjectPossible,
            $studentAttendance,
            $totalSessions,
            $recitationBonusCap,
            $gradingWeights
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

            $groupActivityScoreGrid = [];
            foreach ($groupActivities as $gAct) {
                $score = $studentProjectScores[$student->id][$gAct->id] ?? null;
                $groupActivityScoreGrid[$gAct->id] = $score !== null ? round($score, 2) : null;
                if ($score !== null) {
                    $earnedByType['activity'] += (float) $score;
                } else {
                    $missingByType['activity']++;
                }
            }

            $studentRecs = $recitations->get($student->id, collect());
            $recitationCount = $studentRecs->count();
            $recitationTotal = round((float) $studentRecs->sum('score'), 2);
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

            $projScoresMap = $studentProjectScores[$student->id] ?? [];
            $projectEarned = 0.0;
            $projectMissing = 0;
            $projectScoreGrid = [];

            foreach ($regularProjects as $proj) {
                $projScore = $projScoresMap[$proj->id] ?? null;
                $projectScoreGrid[$proj->id] = $projScore !== null ? round($projScore, 2) : null;
                if ($projScore !== null) {
                    $projectEarned += (float) $projScore;
                } else {
                    $projectMissing++;
                }
            }

            $projectPct = $totalProjectPossible > 0 && $regularProjects->isNotEmpty()
                ? round(($projectEarned / $totalProjectPossible) * 100, 2)
                : null;

            $att = $studentAttendance[$student->id] ?? ['present' => 0, 'late' => 0, 'absent' => 0];
            $presentCount = $att['present'];
            $lateCount = $att['late'];
            $absentCount = $att['absent'];
            $earnedAttendancePts = round(($presentCount * 1.0) + ($lateCount * 0.5), 1);
            $possibleAttendancePts = (float) $totalSessions;
            $attendancePct = $totalSessions > 0 ? round(($earnedAttendancePts / $totalSessions) * 100, 2) : null;

            // Compute overall weighted grade
            $weightedGrade = $this->calculateWeightedGrade(
                $categories,
                $projectPct,
                $attendancePct,
                $gradingWeights
            );

            return [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'scores' => $scoreGrid,
                'categories' => $categories,
                'group_activity_scores' => $groupActivityScoreGrid,
                'project_scores' => $projectScoreGrid,
                'projectSummary' => [
                    'count' => $regularProjects->count(),
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
                    'present_dates' => array_values(array_unique($att['present_dates'] ?? [])),
                    'late_dates' => array_values(array_unique($att['late_dates'] ?? [])),
                    'earned_points' => $earnedAttendancePts,
                    'possible_points' => $possibleAttendancePts,
                    'percentage' => $attendancePct,
                ],
                'recitation' => [
                    'count' => $recitationCount,
                    'total_score' => $recitationTotal,
                    'avg_score' => $recitationAvg,
                    'percentage' => $recitationPct,
                    'bonus_points' => $earnedBonus,
                ],
                'weighted_grade' => $weightedGrade,
            ];
        });

        return [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'assessments' => $assessments,
            'groupActivities' => $groupActivities->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'project_number' => $p->project_number,
                'title' => $p->title,
                'conducted_on' => $p->conducted_on?->toDateString(),
                'max_points' => $p->max_points ?: '100.00',
            ])->values(),
            'projects' => $regularProjects->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'project_number' => $p->project_number,
                'title' => $p->title,
                'conducted_on' => $p->conducted_on?->toDateString(),
                'max_points' => $p->max_points ?: '100.00',
            ])->values(),
            'rows' => $rows,
            'categorySummary' => $categorySummary,
            'projectSummary' => [
                'count' => $regularProjects->count(),
                'possible' => $totalProjectPossible,
            ],
            'attendanceSummary' => [
                'total_sessions' => $totalSessions,
            ],
            'gradingWeights' => $gradingWeights,
        ];
    }

    /**
     * Compute higher-level pedagogical insights for Octo AI domain tool.
     */
    public function getSectionInsights(Section $section): array
    {
        $gradebook = $this->calculateGradebook($section);
        $rows = $gradebook['rows'];
        $totalStudents = $rows->count();

        if ($totalStudents === 0) {
            return [
                'total_students' => 0,
                'message' => 'No active students enrolled in this section.',
            ];
        }

        $gradesWithScores = $rows->filter(fn ($r) => $r['weighted_grade'] !== null);
        $averageGrade = $gradesWithScores->isNotEmpty()
            ? round($gradesWithScores->avg('weighted_grade'), 2)
            : null;

        $passing = $rows->filter(fn ($r) => ($r['weighted_grade'] ?? 0) >= 75.0)->count();
        $failing = $rows->filter(fn ($r) => $r['weighted_grade'] !== null && $r['weighted_grade'] < 75.0)->count();

        $atRisk = $rows->filter(function ($r) {
            $isLowGrade = $r['weighted_grade'] !== null && $r['weighted_grade'] < 75.0;
            $hasAbsences = ($r['attendance']['absent_count'] ?? 0) >= 3;
            $missingCount = collect($r['categories'])->sum('missing') + ($r['projectSummary']['missing'] ?? 0);

            return $isLowGrade || $hasAbsences || $missingCount >= 2;
        })->map(fn ($r) => [
            'student_number' => $r['student_number'],
            'full_name' => $r['full_name'],
            'weighted_grade' => $r['weighted_grade'],
            'absences' => $r['attendance']['absent_count'],
            'missing_tasks' => collect($r['categories'])->sum('missing') + ($r['projectSummary']['missing'] ?? 0),
        ])->values()->all();

        $topPerformers = $rows->sortByDesc('weighted_grade')->take(5)->map(fn ($r) => [
            'student_number' => $r['student_number'],
            'full_name' => $r['full_name'],
            'weighted_grade' => $r['weighted_grade'],
        ])->values()->all();

        // Overall section attendance rate
        $totalPossibleAtt = $rows->sum(fn ($r) => $r['attendance']['possible_points']);
        $totalEarnedAtt = $rows->sum(fn ($r) => $r['attendance']['earned_points']);
        $attendanceRate = $totalPossibleAtt > 0
            ? round(($totalEarnedAtt / $totalPossibleAtt) * 100, 1)
            : null;

        return [
            'section_name' => $section->name,
            'subject' => $section->subject_code ? "{$section->subject_code} - {$section->subject_title}" : null,
            'total_students' => $totalStudents,
            'class_average_grade' => $averageGrade,
            'passing_count' => $passing,
            'failing_count' => $failing,
            'attendance_rate_pct' => $attendanceRate,
            'total_attendance_sessions' => $gradebook['attendanceSummary']['total_sessions'],
            'at_risk_students' => $atRisk,
            'top_performers' => $topPerformers,
            'weights' => $gradebook['gradingWeights'],
        ];
    }

    /**
     * Helper to compute weighted percentage total.
     */
    protected function calculateWeightedGrade(
        Collection $categories,
        ?float $projectPct,
        ?float $attendancePct,
        array $gradingWeights
    ): ?float {
        $totalWeight = 0;
        $weightedSum = 0.0;

        foreach (['activity', 'laboratory', 'quiz', 'exam'] as $cat) {
            $weight = (float) ($gradingWeights[$cat] ?? 0);
            $pct = $categories[$cat]['percentage'] ?? null;
            if ($weight > 0 && $pct !== null) {
                $weightedSum += ($pct * ($weight / 100));
                $totalWeight += $weight;
            }
        }

        $projWeight = (float) ($gradingWeights['project'] ?? 0);
        if ($projWeight > 0 && $projectPct !== null) {
            $weightedSum += ($projectPct * ($projWeight / 100));
            $totalWeight += $projWeight;
        }

        $attWeight = (float) ($gradingWeights['attendance'] ?? 0);
        if ($attWeight > 0 && $attendancePct !== null) {
            $weightedSum += ($attendancePct * ($attWeight / 100));
            $totalWeight += $attWeight;
        }

        if ($totalWeight === 0) {
            return null;
        }

        // Normalize to available evaluated categories
        return round(($weightedSum / ($totalWeight / 100)), 2);
    }

    /**
     * @return array{Collection, Collection, Collection, Collection, Collection, Collection}
     */
    protected function loadData(Section $section): array
    {
        $assessments = Assessment::where('section_id', $section->id)->orderBy('conducted_on')->orderBy('id')
            ->get(['id', 'type', 'assessment_number', 'title', 'conducted_on', 'max_points']);
        $students = Student::where('section_id', $section->id)
            ->where('is_active', true)
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
