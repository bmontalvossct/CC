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
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GradebookCalculationService
{
    public const DEFAULT_PASSING_RATES = [
        'quiz' => 50,
        'activity' => 50,
        'project' => 50,
        'exam' => 50,
    ];

    public const DEFAULT_WEIGHTS = [
        'activity' => 20,
        'laboratory' => 0,
        'quiz' => 20,
        'exam' => 25,
        'project' => 20,
        'attendance' => 15,
        'recitation' => 5,
        'reporting_frequency' => 'once_per_sem', // 'once_per_sem' (1 report in finals) or 'twice_per_sem' (midterm + finals)
        'midterm_weight' => 50, // 50% Midterm Grade
        'final_weight' => 50,   // 50% Final Period Grade
        'passing_rates' => self::DEFAULT_PASSING_RATES,
    ];

    protected GradeComputationService $gradeComputationService;

    public function __construct(?GradeComputationService $gradeComputationService = null)
    {
        $this->gradeComputationService = $gradeComputationService ?? app(GradeComputationService::class);
    }

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
     *     midtermCategorySummary: Collection,
     *     finalCategorySummary: Collection,
     *     projectSummary: array,
     *     attendanceSummary: array,
     *     gradingWeights: array,
     *     midtermExam: ?array,
     *     reportingFrequency: string
     * }
     */
    public function calculateGradebook(Section $section, bool $forceFresh = false): array
    {
        $cacheKey = "section_{$section->id}_gradebook_calc";
        if (! $forceFresh && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $result = $this->computeGradebook($section);
        cache()->put($cacheKey, $result, 60);

        return $result;
    }

    /**
     * Clear cached gradebook calculations for a section.
     */
    public function clearGradebookCache(Section|int $section): void
    {
        $id = $section instanceof Section ? $section->id : $section;
        cache()->forget("section_{$id}_gradebook_calc");
    }

    /**
     * Compute raw gradebook matrix and metrics for a section.
     */
    public function computeGradebook(Section $section): array
    {
        [$assessments, $students, $scores, $projects, $attendanceSessions, $recitations] = $this->loadData($section);

        $gradingWeights = array_merge(self::DEFAULT_WEIGHTS, $section->grading_weights ?? []);
        $gradingWeights['passing_rates'] = array_merge(
            self::DEFAULT_PASSING_RATES,
            $section->grading_weights['passing_rates'] ?? []
        );
        $recitationBonusCap = (float) ($gradingWeights['recitation'] ?? 5);
        $reportingFrequency = (string) ($gradingWeights['reporting_frequency'] ?? 'once_per_sem');

        // 1. Identify Midterm Exam and period boundary
        $midtermExam = $this->identifyMidtermExam($assessments);
        $midtermDate = $midtermExam?->conducted_on?->toDateString();

        if (! $midtermDate && $section->academic_term_id) {
            $term = $section->relationLoaded('academicTerm') ? $section->academicTerm : $section->academicTerm()->first();
            if ($term?->starts_on && $term?->ends_on) {
                $start = Carbon::parse($term->starts_on);
                $end = Carbon::parse($term->ends_on);
                $midtermDate = $start->copy()->addDays((int) round($start->diffInDays($end) / 2))->toDateString();
            }
        }

        // 2. Partition assessments into Midterm vs Final periods
        $assessmentsWithPeriod = $assessments->map(function ($assessment) use ($midtermExam, $midtermDate) {
            $period = $this->determineItemPeriod(
                $assessment->term_period,
                $assessment->conducted_on?->toDateString(),
                $midtermExam?->id === $assessment->id,
                $midtermDate,
                $assessment->type === 'exam',
                $assessment->title,
                $assessment->created_at?->toDateString()
            );
            $assessment->computed_period = $period;

            return $assessment;
        });

        // 3. Partition projects (group activities, projects, reporting)
        $projectsWithPeriod = $projects->map(function ($project) use ($midtermDate) {
            $period = $this->determineItemPeriod(
                $project->term_period,
                $project->conducted_on?->toDateString(),
                false,
                $midtermDate,
                false,
                $project->title,
                $project->created_at?->toDateString()
            );
            $project->computed_period = $period;

            return $project;
        });

        $groupActivities = $projectsWithPeriod->where('type', 'group_activity');
        $regularProjects = $projectsWithPeriod->whereIn('type', ['project', 'reporting']);

        // 4. Compute Category summaries (Overall, Midterm, and Final Period)
        $categorySummary = $this->computeCategorySummary($assessmentsWithPeriod, $groupActivities);
        $midtermCategorySummary = $this->computeCategorySummary(
            $assessmentsWithPeriod->where('computed_period', 'midterm'),
            $groupActivities->where('computed_period', 'midterm')
        );
        $finalCategorySummary = $this->computeCategorySummary(
            $assessmentsWithPeriod->where('computed_period', 'final'),
            $groupActivities->where('computed_period', 'final')
        );

        $totalProjectPossible = round($regularProjects->sum(fn ($p) => (float) ($p->max_points ?: 100)), 2);

        // 5. Index project scores and notes by student and project ID
        $studentProjectScores = [];
        $studentProjectNotes = [];
        foreach ($projectsWithPeriod as $project) {
            foreach ($project->groups as $group) {
                $groupScore = $group->score !== null ? (float) $group->score : null;
                foreach ($group->members as $member) {
                    $memberScore = $member->score !== null ? (float) $member->score : $groupScore;
                    $studentProjectScores[$member->student_id][$project->id] = $memberScore;
                    $studentProjectNotes[$member->student_id][$project->id] = $member->notes ?: $group->notes;
                }
            }
        }

        // 6. Index attendance by student and partition into Midterm vs Final periods
        $totalSessions = $attendanceSessions->count();
        $studentAttendance = [];
        $midtermSessionsCount = 0;
        $finalSessionsCount = 0;

        foreach ($attendanceSessions as $session) {
            $sessionDate = $session->session_date->toDateString();
            $isMidtermSession = $midtermDate ? ($sessionDate <= $midtermDate) : true;
            if ($isMidtermSession) {
                $midtermSessionsCount++;
            } else {
                $finalSessionsCount++;
            }

            foreach ($session->records as $record) {
                $sid = $record->student_id;
                if (! isset($studentAttendance[$sid])) {
                    $studentAttendance[$sid] = [
                        'overall' => ['present' => 0, 'late' => 0, 'excused' => 0, 'excused_with_points' => 0, 'absent' => 0, 'present_dates' => [], 'late_dates' => [], 'excused_dates' => []],
                        'midterm' => ['present' => 0, 'late' => 0, 'excused' => 0, 'excused_with_points' => 0, 'absent' => 0, 'present_dates' => [], 'late_dates' => [], 'excused_dates' => []],
                        'final' => ['present' => 0, 'late' => 0, 'excused' => 0, 'excused_with_points' => 0, 'absent' => 0, 'present_dates' => [], 'late_dates' => [], 'excused_dates' => []],
                    ];
                }

                $this->accumulateAttendanceRecord($studentAttendance[$sid]['overall'], $record, $sessionDate);
                if ($isMidtermSession) {
                    $this->accumulateAttendanceRecord($studentAttendance[$sid]['midterm'], $record, $sessionDate);
                } else {
                    $this->accumulateAttendanceRecord($studentAttendance[$sid]['final'], $record, $sessionDate);
                }
            }
        }

        // 7. Calculate Student rows (with Midterm Grade, Final Period Grade, and Semestral Final Grade)
        $rows = $students->map(function ($student) use (
            $assessmentsWithPeriod,
            $scores,
            $categorySummary,
            $midtermCategorySummary,
            $finalCategorySummary,
            $recitations,
            $groupActivities,
            $regularProjects,
            $studentProjectScores,
            $studentProjectNotes,
            $studentAttendance,
            $totalSessions,
            $midtermSessionsCount,
            $finalSessionsCount,
            $recitationBonusCap,
            $gradingWeights,
            $reportingFrequency,
            $midtermDate
        ) {
            $studentScores = $scores->get($student->id, collect());
            $scoreGrid = [];
            $remarksGrid = [];

            $earnedByType = ['overall' => array_fill_keys(Assessment::TYPES, 0.0), 'midterm' => array_fill_keys(Assessment::TYPES, 0.0), 'final' => array_fill_keys(Assessment::TYPES, 0.0)];
            $possibleByType = ['overall' => array_fill_keys(Assessment::TYPES, 0.0), 'midterm' => array_fill_keys(Assessment::TYPES, 0.0), 'final' => array_fill_keys(Assessment::TYPES, 0.0)];
            $missingByType = ['overall' => array_fill_keys(Assessment::TYPES, 0), 'midterm' => array_fill_keys(Assessment::TYPES, 0), 'final' => array_fill_keys(Assessment::TYPES, 0)];

            foreach ($assessmentsWithPeriod as $assessment) {
                $scoreRecord = $studentScores->get($assessment->id);
                $score = $scoreRecord?->score;
                $scoreRemarks = $scoreRecord?->remarks;
                $scoreGrid[$assessment->id] = $score;
                $remarksGrid[$assessment->id] = $scoreRemarks;
                $period = $assessment->computed_period; // 'midterm' or 'final'
                $maxPts = (float) $assessment->max_points;

                // Excused or Not Applicable assessments are excluded from both earned and possible scores
                $isExcused = is_string($scoreRemarks) && preg_match('/\b(excused|not_applicable|not applicable|n\/a)\b/i', $scoreRemarks);
                if ($isExcused) {
                    continue;
                }

                $possibleByType['overall'][$assessment->type] += $maxPts;
                $possibleByType[$period][$assessment->type] += $maxPts;

                $earnedByType['overall'][$assessment->type] += (float) ($score ?? 0);
                $earnedByType[$period][$assessment->type] += (float) ($score ?? 0);

                if ($score === null) {
                    $missingByType['overall'][$assessment->type]++;
                    $missingByType[$period][$assessment->type]++;
                }
            }

            // Group activities into Activity category
            $groupActivityScoreGrid = [];
            foreach ($groupActivities as $gAct) {
                $score = $studentProjectScores[$student->id][$gAct->id] ?? null;
                $note = $studentProjectNotes[$student->id][$gAct->id] ?? null;
                $groupActivityScoreGrid[$gAct->id] = $score !== null ? round($score, 2) : null;
                $period = $gAct->computed_period;
                $maxPtsAct = (float) ($gAct->max_points ?: 100);

                $isExcusedAct = is_string($note) && preg_match('/\b(excused|not_applicable|not applicable|n\/a)\b/i', $note);
                if ($isExcusedAct) {
                    continue;
                }

                $possibleByType['overall']['activity'] += $maxPtsAct;
                $possibleByType[$period]['activity'] += $maxPtsAct;

                if ($score !== null) {
                    $earnedByType['overall']['activity'] += (float) $score;
                    $earnedByType[$period]['activity'] += (float) $score;
                } else {
                    $missingByType['overall']['activity']++;
                    $missingByType[$period]['activity']++;
                }
            }

            // Recitations calculation (Overall, Midterm, Final)
            $allStudentRecs = $recitations->get($student->id, collect());
            $midtermStudentRecs = $allStudentRecs->filter(function ($r) use ($midtermDate) {
                if (! $midtermDate) {
                    return true;
                }
                $date = $r->conducted_on ? (Carbon::parse($r->conducted_on)->toDateString()) : ($r->created_at ? $r->created_at->toDateString() : null);

                return $date ? ($date <= $midtermDate) : true;
            });
            $finalStudentRecs = $allStudentRecs->filter(function ($r) use ($midtermDate) {
                if (! $midtermDate) {
                    return false;
                }
                $date = $r->conducted_on ? (Carbon::parse($r->conducted_on)->toDateString()) : ($r->created_at ? $r->created_at->toDateString() : null);

                return $date ? ($date > $midtermDate) : false;
            });

            $overallRecitation = $this->calculateRecitationMetrics($allStudentRecs, $recitationBonusCap);
            $midtermRecitation = $this->calculateRecitationMetrics($midtermStudentRecs, $recitationBonusCap);
            $finalRecitation = $this->calculateRecitationMetrics($finalStudentRecs, $recitationBonusCap);

            // Categories summary by period
            $categoriesOverall = $this->buildCategoriesBreakdown($categorySummary, $earnedByType['overall'], $missingByType['overall'], $overallRecitation['bonus_points'], $possibleByType['overall']);
            $categoriesMidterm = $this->buildCategoriesBreakdown($midtermCategorySummary, $earnedByType['midterm'], $missingByType['midterm'], $midtermRecitation['bonus_points'], $possibleByType['midterm']);
            $categoriesFinal = $this->buildCategoriesBreakdown($finalCategorySummary, $earnedByType['final'], $missingByType['final'], $finalRecitation['bonus_points'], $possibleByType['final']);

            // Projects and Reporting allocation
            $projScoresMap = $studentProjectScores[$student->id] ?? [];
            $projectScoreGrid = [];

            $projMetricsOverall = ['earned' => 0.0, 'possible' => 0.0, 'missing' => 0, 'count' => 0];
            $projMetricsMidterm = ['earned' => 0.0, 'possible' => 0.0, 'missing' => 0, 'count' => 0];
            $projMetricsFinal = ['earned' => 0.0, 'possible' => 0.0, 'missing' => 0, 'count' => 0];

            foreach ($regularProjects as $proj) {
                $projScore = $projScoresMap[$proj->id] ?? null;
                $projectScoreGrid[$proj->id] = $projScore !== null ? round($projScore, 2) : null;
                $maxPts = (float) ($proj->max_points ?: 100);
                $isReporting = $proj->type === 'reporting';

                // Overall accumulation
                $projMetricsOverall['count']++;
                $projMetricsOverall['possible'] += $maxPts;
                if ($projScore !== null) {
                    $projMetricsOverall['earned'] += (float) $projScore;
                } else {
                    $projMetricsOverall['missing']++;
                }

                // Midterm vs Final period accumulation based on reporting frequency
                if ($isReporting && $reportingFrequency === 'once_per_sem') {
                    // 1 report per sem: Exclude from Midterm so students reporting later aren't penalized!
                    // Assigned to Final period / semestral evaluation
                    $projMetricsFinal['count']++;
                    $projMetricsFinal['possible'] += $maxPts;
                    if ($projScore !== null) {
                        $projMetricsFinal['earned'] += (float) $projScore;
                    } else {
                        $projMetricsFinal['missing']++;
                    }
                } else {
                    // Regular project or 2-reports-per-sem: Follow project period
                    $targetMetrics = $proj->computed_period === 'midterm' ? 'projMetricsMidterm' : 'projMetricsFinal';
                    ${$targetMetrics}['count']++;
                    ${$targetMetrics}['possible'] += $maxPts;
                    if ($projScore !== null) {
                        ${$targetMetrics}['earned'] += (float) $projScore;
                    } else {
                        ${$targetMetrics}['missing']++;
                    }
                }
            }

            $projectPctOverall = $projMetricsOverall['possible'] > 0 ? round(($projMetricsOverall['earned'] / $projMetricsOverall['possible']) * 100, 2) : null;
            $projectPctMidterm = $projMetricsMidterm['possible'] > 0 ? round(($projMetricsMidterm['earned'] / $projMetricsMidterm['possible']) * 100, 2) : null;
            $projectPctFinal = $projMetricsFinal['possible'] > 0 ? round(($projMetricsFinal['earned'] / $projMetricsFinal['possible']) * 100, 2) : null;

            // Attendance metrics by period
            $attStudent = $studentAttendance[$student->id] ?? [
                'overall' => ['present' => 0, 'late' => 0, 'excused' => 0, 'excused_with_points' => 0, 'absent' => 0],
                'midterm' => ['present' => 0, 'late' => 0, 'excused' => 0, 'excused_with_points' => 0, 'absent' => 0],
                'final' => ['present' => 0, 'late' => 0, 'excused' => 0, 'excused_with_points' => 0, 'absent' => 0],
            ];

            $attendanceOverall = $this->calculateAttendanceMetrics($attStudent['overall'], $totalSessions);
            $attendanceMidterm = $this->calculateAttendanceMetrics($attStudent['midterm'], $midtermSessionsCount);
            $attendanceFinal = $this->calculateAttendanceMetrics($attStudent['final'], $finalSessionsCount);

            // Period Weighted Grade calculations
            $midtermWeighted = $this->calculateWeightedGrade(
                $categoriesMidterm,
                $projectPctMidterm,
                $attendanceMidterm['percentage'],
                $gradingWeights,
                $midtermRecitation['bonus_points'] ?? 0.0,
                $midtermRecitation['percentage'] ?? null
            );
            $finalPeriodWeighted = $this->calculateWeightedGrade(
                $categoriesFinal,
                $projectPctFinal,
                $attendanceFinal['percentage'],
                $gradingWeights,
                $finalRecitation['bonus_points'] ?? 0.0,
                $finalRecitation['percentage'] ?? null
            );
            $overallCumulativeWeighted = $this->calculateWeightedGrade(
                $categoriesOverall,
                $projectPctOverall,
                $attendanceOverall['percentage'],
                $gradingWeights,
                $overallRecitation['bonus_points'] ?? 0.0,
                $overallRecitation['percentage'] ?? null
            );

            // Semestral Combined Grade: (Midterm * 50%) + (Final Period * 50%)
            $midWeightPct = (float) ($gradingWeights['midterm_weight'] ?? 50);
            $finWeightPct = (float) ($gradingWeights['final_weight'] ?? 50);

            if ($midtermWeighted !== null && $finalPeriodWeighted !== null) {
                $totalTermWeight = $midWeightPct + $finWeightPct;
                $semestralWeighted = $totalTermWeight > 0
                    ? round((($midtermWeighted * $midWeightPct) + ($finalPeriodWeighted * $finWeightPct)) / $totalTermWeight, 2)
                    : $overallCumulativeWeighted;
            } elseif ($midtermWeighted !== null) {
                $semestralWeighted = $midtermWeighted;
            } elseif ($finalPeriodWeighted !== null) {
                $semestralWeighted = $finalPeriodWeighted;
            } else {
                $semestralWeighted = $overallCumulativeWeighted;
            }

            // Calculate period numerical grades using the Excel formula:
            // ComponentGrade = 5 - (4 * (TotalEarned / TotalMaximum))
            // MD / FD = ROUND(SUM(ComponentGrade * ComponentWeight), 1)
            $midtermMD = $this->calculatePeriodNumericalGrade(
                $categoriesMidterm,
                $projMetricsMidterm,
                $attendanceMidterm,
                $gradingWeights,
                $midtermRecitation['bonus_points'] ?? 0.0,
                $midtermRecitation['percentage'] ?? null
            );
            $finalFD = $this->calculatePeriodNumericalGrade(
                $categoriesFinal,
                $projMetricsFinal,
                $attendanceFinal,
                $gradingWeights,
                $finalRecitation['bonus_points'] ?? 0.0,
                $finalRecitation['percentage'] ?? null
            );

            $customTable = $gradingWeights['transmutation_table'] ?? $gradingWeights['equivalency_table'] ?? null;
            if (! empty($customTable) && is_array($customTable)) {
                $semestralEquiv = $this->lookupGradeEquivalent($semestralWeighted, $customTable);
                $midtermEquiv = $this->lookupGradeEquivalent($midtermWeighted, $customTable);
                $finalPeriodEquiv = $this->lookupGradeEquivalent($finalPeriodWeighted, $customTable);

                $semestralScale = $semestralEquiv['grade_equivalent'];
                $semestralRemarks = $semestralEquiv['remarks'];
                $isPassing = $semestralWeighted !== null ? in_array(strtoupper($semestralRemarks), ['PASSED', 'PASS'], true) : null;

                $midtermScale = $midtermEquiv['grade_equivalent'];
                $midtermRemarks = $midtermEquiv['remarks'];

                $finalPeriodScale = $finalPeriodEquiv['grade_equivalent'];
                $finalPeriodRemarks = $finalPeriodEquiv['remarks'];
            } else {
                // Exact Excel grading system
                $midtermScale = $midtermMD !== null ? number_format($midtermMD, 1) : '—';
                $midtermRemarks = $midtermMD !== null ? ($midtermMD <= 3.0 ? 'Passed' : 'Failed') : '—';

                $finalPeriodScale = $finalFD !== null ? number_format($finalFD, 1) : '—';
                $finalPeriodRemarks = $finalFD !== null ? ($finalFD <= 3.0 ? 'Passed' : 'Failed') : '—';

                if ($midtermMD !== null && $finalFD !== null) {
                    $fgResult = $this->gradeComputationService->computeOverallFinalGrade($midtermMD, $finalFD);
                    $semestralScale = $fgResult['formatted_final_grade'];
                    $semestralRemarks = $fgResult['remarks'];
                    $isPassing = $fgResult['is_passing'];
                } elseif ($midtermMD !== null) {
                    $semestralScale = number_format($midtermMD, 1);
                    $semestralRemarks = $midtermMD <= 3.0 ? 'Passed' : 'Failed';
                    $isPassing = $midtermMD <= 3.0;
                } else {
                    $semestralScale = '—';
                    $semestralRemarks = '—';
                    $isPassing = null;
                }
            }

            return [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'scores' => $scoreGrid,
                'remarks' => $remarksGrid,
                'categories' => $categoriesOverall,
                'group_activity_scores' => $groupActivityScoreGrid,
                'project_scores' => $projectScoreGrid,
                'project_notes' => $studentProjectNotes[$student->id] ?? [],
                'projectSummary' => [
                    'count' => $projMetricsOverall['count'],
                    'earned' => round($projMetricsOverall['earned'], 2),
                    'possible' => $projMetricsOverall['possible'],
                    'percentage' => $projectPctOverall,
                    'missing' => $projMetricsOverall['missing'],
                ],
                'attendance' => $attendanceOverall,
                'recitation' => $overallRecitation,
                'weighted_grade' => $semestralWeighted,
                'scale_grade' => $semestralScale,
                'final_grade' => $semestralScale,
                'grade_remarks' => $semestralRemarks,
                'final_remarks' => $semestralRemarks,
                'is_passing' => $isPassing,

                // Midterm Grade Breakdown
                'midterm' => [
                    'weighted_grade' => $midtermWeighted,
                    'scale_grade' => $midtermScale,
                    'md' => $midtermMD,
                    'grade_remarks' => $midtermRemarks,
                    'categories' => $categoriesMidterm,
                    'attendance' => $attendanceMidterm,
                    'recitation' => $midtermRecitation,
                    'projectSummary' => [
                        'count' => $projMetricsMidterm['count'],
                        'earned' => round($projMetricsMidterm['earned'], 2),
                        'possible' => $projMetricsMidterm['possible'],
                        'percentage' => $projectPctMidterm,
                        'missing' => $projMetricsMidterm['missing'],
                    ],
                ],

                // Final Period Grade Breakdown
                'final_period' => [
                    'weighted_grade' => $finalPeriodWeighted,
                    'scale_grade' => $finalPeriodScale,
                    'fd' => $finalFD,
                    'grade_remarks' => $finalPeriodRemarks,
                    'categories' => $categoriesFinal,
                    'attendance' => $attendanceFinal,
                    'recitation' => $finalRecitation,
                    'projectSummary' => [
                        'count' => $projMetricsFinal['count'],
                        'earned' => round($projMetricsFinal['earned'], 2),
                        'possible' => $projMetricsFinal['possible'],
                        'percentage' => $projectPctFinal,
                        'missing' => $projMetricsFinal['missing'],
                    ],
                ],
            ];
        });

        return [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'assessments' => $assessmentsWithPeriod,
            'groupActivities' => $groupActivities->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'period' => $p->computed_period,
                'project_number' => $p->project_number,
                'title' => $p->title,
                'conducted_on' => $p->conducted_on?->toDateString(),
                'max_points' => $p->max_points ?: '100.00',
            ])->values(),
            'projects' => $regularProjects->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'period' => $p->computed_period,
                'project_number' => $p->project_number,
                'title' => $p->title,
                'conducted_on' => $p->conducted_on?->toDateString(),
                'max_points' => $p->max_points ?: '100.00',
            ])->values(),
            'rows' => $rows,
            'categorySummary' => $categorySummary,
            'midtermCategorySummary' => $midtermCategorySummary,
            'finalCategorySummary' => $finalCategorySummary,
            'projectSummary' => [
                'count' => $regularProjects->count(),
                'possible' => $totalProjectPossible,
            ],
            'attendanceSummary' => [
                'total_sessions' => $totalSessions,
                'midterm_sessions' => $midtermSessionsCount,
                'final_sessions' => $finalSessionsCount,
            ],
            'gradingWeights' => $gradingWeights,
            'reportingFrequency' => $reportingFrequency,
            'midtermExam' => $midtermExam ? [
                'id' => $midtermExam->id,
                'title' => $midtermExam->title,
                'conducted_on' => $midtermExam->conducted_on?->toDateString(),
                'max_points' => $midtermExam->max_points,
            ] : null,
        ];
    }

    /**
    /**
     * Convert percentage to university numerical grade scale (1.00 - 5.00) using configurable transmutation table.
     */
    public function percentToScale(?float $pct, ?array $transmutationTable = null): string
    {
        if ($pct === null) {
            return '—';
        }

        return $this->lookupGradeEquivalent($pct, $transmutationTable)['grade_equivalent'];
    }

    /**
     * Lookup numerical grade equivalent and remarks from transmutation table.
     *
     * @return array{grade_equivalent: string, remarks: string, minimum_percentage: float, maximum_percentage: float}
     */
    public function lookupGradeEquivalent(?float $pct, ?array $transmutationTable = null): array
    {
        if ($pct === null) {
            return [
                'grade_equivalent' => '—',
                'remarks' => '—',
                'minimum_percentage' => 0.0,
                'maximum_percentage' => 0.0,
            ];
        }

        return $this->gradeComputationService->lookupGradeEquivalent($pct, $transmutationTable);
    }

    /**
     * Identify the Midterm Exam from assessments.
     */
    protected function identifyMidtermExam(Collection $assessments): ?Assessment
    {
        $exams = $assessments->where('type', 'exam');
        if ($exams->isEmpty()) {
            return null;
        }

        // 1. Explicit term_period == 'midterm'
        $explicit = $exams->firstWhere('term_period', 'midterm');
        if ($explicit) {
            return $explicit;
        }

        // 2. Title or number matching 'midterm'
        $byName = $exams->first(function ($exam) {
            $title = strtolower($exam->title ?? '');
            $num = strtolower($exam->assessment_number ?? '');

            return str_contains($title, 'midterm') || str_contains($title, 'mid-term') || str_contains($title, 'mid term')
                || str_contains($num, 'midterm') || str_contains($num, 'mid-term');
        });

        if ($byName) {
            return $byName;
        }

        // 3. Fallback to first chronological exam
        return $exams->sortBy('conducted_on')->first();
    }

    /**
     * Determine whether an item belongs to 'midterm' or 'final' period.
     */
    protected function determineItemPeriod(
        ?string $explicitPeriod,
        ?string $conductedOn,
        bool $isMidtermExam,
        ?string $midtermDate,
        bool $isExam = false,
        ?string $title = null,
        ?string $createdDate = null
    ): string {
        if ($explicitPeriod === 'midterm' || $explicitPeriod === 'final') {
            return $explicitPeriod;
        }

        // Major Exams: For the Midterm Grade, use the Midterm Examination only.
        // The Final Examination or any other exam must never be included in the Midterm Grade.
        if ($isExam) {
            return $isMidtermExam ? 'midterm' : 'final';
        }

        // Items explicitly titled with "final exam" or "finals" belong to final period
        if ($title) {
            $lower = strtolower($title);
            if (str_contains($lower, 'final exam') || str_contains($lower, 'finals')) {
                return 'final';
            }
        }

        $effectiveDate = $conductedOn ?: $createdDate;

        if ($midtermDate && $effectiveDate) {
            return $effectiveDate <= $midtermDate ? 'midterm' : 'final';
        }

        return 'midterm';
    }

    protected function computeCategorySummary(Collection $assessments, Collection $groupActivities): Collection
    {
        $summary = collect(Assessment::TYPES)->mapWithKeys(fn ($type) => [$type => [
            'count' => 0,
            'possible' => 0.0,
        ]]);

        foreach ($assessments as $assessment) {
            $summary[$assessment->type] = [
                'count' => $summary[$assessment->type]['count'] + 1,
                'possible' => round($summary[$assessment->type]['possible'] + (float) $assessment->max_points, 2),
            ];
        }

        foreach ($groupActivities as $gAct) {
            $summary['activity'] = [
                'count' => $summary['activity']['count'] + 1,
                'possible' => round($summary['activity']['possible'] + (float) ($gAct->max_points ?: 100), 2),
            ];
        }

        return $summary;
    }

    protected function buildCategoriesBreakdown(
        Collection $summary,
        array $earnedByType,
        array $missingByType,
        float $bonusEarned,
        ?array $possibleByType = null
    ): Collection {
        return collect(Assessment::TYPES)->mapWithKeys(function ($type) use ($summary, $earnedByType, $missingByType, $bonusEarned, $possibleByType) {
            $rawEarned = (float) $earnedByType[$type];
            $bonus = $type === 'activity' ? $bonusEarned : 0.0;
            $earned = $rawEarned + $bonus;
            $possible = $possibleByType !== null ? (float) ($possibleByType[$type] ?? 0.0) : (float) ($summary[$type]['possible'] ?? 0.0);

            // Maintain full decimal precision during intermediate calculations
            $rawPercentage = $possible > 0 ? min(100.0, ($earned / $possible * 100)) : null;

            return [$type => [
                'raw_earned' => round($rawEarned, 2),
                'bonus_earned' => round($bonus, 2),
                'earned' => round($earned, 2),
                'possible' => round($possible, 2),
                'percentage' => $rawPercentage !== null ? round($rawPercentage, 2) : null,
                'raw_percentage' => $rawPercentage,
                'missing' => $missingByType[$type] ?? 0,
            ]];
        });
    }

    protected function calculateRecitationMetrics(Collection $recs, float $bonusCap): array
    {
        $count = $recs->count();
        $total = round((float) $recs->sum('score'), 2);
        $avg = $count > 0 ? round((float) $recs->avg('score'), 2) : null;
        $pct = $avg !== null ? round(($avg / 10) * 100, 2) : null;
        $bonus = $avg !== null && $bonusCap > 0 ? round(($avg / 10) * $bonusCap, 2) : 0.0;

        return [
            'count' => $count,
            'total_score' => $total,
            'avg_score' => $avg,
            'percentage' => $pct,
            'bonus_points' => $bonus,
        ];
    }

    protected function calculateAttendanceMetrics(array $att, int $totalSessions): array
    {
        $present = $att['present'] ?? 0;
        $late = $att['late'] ?? 0;
        $excused = $att['excused'] ?? 0;
        $excusedWithPoints = $att['excused_with_points'] ?? 0;
        $absent = $att['absent'] ?? 0;
        $earnedPts = round(($present * 1.0) + ($late * 0.5) + ($excusedWithPoints * 1.0), 1);
        $possiblePts = (float) $totalSessions;
        $pct = $totalSessions > 0 ? round(($earnedPts / $totalSessions) * 100, 2) : null;

        return [
            'total_sessions' => $totalSessions,
            'present_count' => $present,
            'late_count' => $late,
            'excused_count' => $excused,
            'absent_count' => $absent,
            'present_dates' => array_values(array_unique($att['present_dates'] ?? [])),
            'late_dates' => array_values(array_unique($att['late_dates'] ?? [])),
            'excused_dates' => array_values(array_unique($att['excused_dates'] ?? [])),
            'earned_points' => $earnedPts,
            'possible_points' => $possiblePts,
            'percentage' => $pct,
        ];
    }

    protected function accumulateAttendanceRecord(array &$target, AttendanceRecord $record, string $date): void
    {
        if ($record->status === AttendanceRecord::STATUS_PRESENT) {
            $target['present']++;
            $target['present_dates'][] = $date;
        } elseif ($record->status === AttendanceRecord::STATUS_LATE) {
            $target['late']++;
            $target['late_dates'][] = $date;
        } elseif ($record->status === AttendanceRecord::STATUS_EXCUSED) {
            $target['excused']++;
            $target['excused_dates'][] = $date;
            if ($record->points_awarded || $record->attended_minutes > 0) {
                $target['excused_with_points']++;
            }
        } elseif ($record->status === AttendanceRecord::STATUS_ABSENT) {
            $target['absent']++;
        }
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

        $passing = $rows->filter(fn ($r) => $r['is_passing'] === true || (($r['weighted_grade'] ?? 0) >= 50.0))->count();
        $failing = $rows->filter(fn ($r) => $r['is_passing'] === false || ($r['weighted_grade'] !== null && $r['weighted_grade'] < 50.0))->count();

        $atRisk = $rows->filter(function ($r) {
            $isLowGrade = ($r['is_passing'] === false) || ($r['weighted_grade'] !== null && $r['weighted_grade'] < 50.0);
            $hasAbsences = ($r['attendance']['absent_count'] ?? 0) >= 3;
            $missingCount = collect($r['categories'])->sum('missing') + ($r['projectSummary']['missing'] ?? 0);

            return $isLowGrade || $hasAbsences || $missingCount >= 2;
        })->map(fn ($r) => [
            'student_number' => $r['student_number'],
            'full_name' => $r['full_name'],
            'weighted_grade' => $r['weighted_grade'],
            'scale_grade' => $r['scale_grade'],
            'midterm_grade' => $r['midterm']['weighted_grade'],
            'absences' => $r['attendance']['absent_count'],
            'missing_tasks' => collect($r['categories'])->sum('missing') + ($r['projectSummary']['missing'] ?? 0),
        ])->values()->all();

        $topPerformers = $rows->sortByDesc('weighted_grade')->take(5)->map(fn ($r) => [
            'student_number' => $r['student_number'],
            'full_name' => $r['full_name'],
            'weighted_grade' => $r['weighted_grade'],
            'scale_grade' => $r['scale_grade'],
            'midterm_grade' => $r['midterm']['weighted_grade'],
        ])->values()->all();

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
            'reporting_frequency' => $gradebook['reportingFrequency'],
        ];
    }

    /**
     * Helper to compute weighted percentage total.
     */
    public function calculateWeightedGrade(
        Collection $categories,
        ?float $projectPct,
        ?float $attendancePct,
        array $gradingWeights,
        float $recitationBonus = 0.0,
        ?float $recitationPct = null
    ): ?float {
        $academicWeights = [
            'activity' => (float) ($gradingWeights['activity'] ?? 0),
            'laboratory' => (float) ($gradingWeights['laboratory'] ?? 0),
            'quiz' => (float) ($gradingWeights['quiz'] ?? 0),
            'exam' => (float) ($gradingWeights['exam'] ?? 0),
            'project' => (float) ($gradingWeights['project'] ?? 0),
        ];
        $attendanceWeight = (float) ($gradingWeights['attendance'] ?? 0);
        $recitationWeight = (float) ($gradingWeights['recitation'] ?? 0);

        $coreWeightTotal = array_sum($academicWeights) + $attendanceWeight;
        $isRecitationCore = (abs(($coreWeightTotal + $recitationWeight) - 100.0) < 0.01 && abs($coreWeightTotal - 100.0) > 0.01);

        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach (['activity', 'laboratory', 'quiz', 'exam'] as $cat) {
            $weight = $academicWeights[$cat];
            $pct = $categories[$cat]['raw_percentage'] ?? $categories[$cat]['percentage'] ?? null;
            if ($weight > 0 && $pct !== null) {
                $weightedSum += ($pct * ($weight / 100));
                $totalWeight += $weight;
            }
        }

        if ($academicWeights['project'] > 0 && $projectPct !== null) {
            $weightedSum += ($projectPct * ($academicWeights['project'] / 100));
            $totalWeight += $academicWeights['project'];
        }

        if ($attendanceWeight > 0 && $attendancePct !== null) {
            $weightedSum += ($attendancePct * ($attendanceWeight / 100));
            $totalWeight += $attendanceWeight;
        }

        // If recitation is configured as a core weighted category (core + rec = 100)
        if ($isRecitationCore && $recitationWeight > 0 && $recitationPct !== null) {
            $weightedSum += ($recitationPct * ($recitationWeight / 100));
            $totalWeight += $recitationWeight;
        }

        if ($totalWeight <= 0) {
            return null;
        }

        // Normalize to available evaluated categories
        $grade = round(($weightedSum / ($totalWeight / 100)), 2);

        // If recitation is an extra credit bonus, apply it if not already absorbed into an existing activity category
        $activityPossible = (float) ($categories['activity']['possible'] ?? 0);
        if (! $isRecitationCore && $recitationBonus > 0 && $activityPossible <= 0) {
            $grade = min(100.0, round($grade + $recitationBonus, 2));
        }

        return $grade;
    }

    /**
     * Compute period numerical grade (MD or FD) using the Excel formula:
     * ComponentGrade_i = 5 - (4 * (TotalEarned_i / TotalMaximum_i))
     * PeriodGrade = ROUND(SUM(ComponentGrade_i * ComponentWeight_i), 1)
     */
    public function calculatePeriodNumericalGrade(
        Collection $categories,
        array $projectMetrics,
        array $attendanceMetrics,
        array $gradingWeights,
        float $recitationBonus = 0.0,
        ?float $recitationPct = null
    ): ?float {
        $academicWeights = [
            'activity' => (float) ($gradingWeights['activity'] ?? 0),
            'laboratory' => (float) ($gradingWeights['laboratory'] ?? 0),
            'quiz' => (float) ($gradingWeights['quiz'] ?? 0),
            'exam' => (float) ($gradingWeights['exam'] ?? 0),
            'project' => (float) ($gradingWeights['project'] ?? 0),
        ];
        $attendanceWeight = (float) ($gradingWeights['attendance'] ?? 0);
        $recitationWeight = (float) ($gradingWeights['recitation'] ?? 0);

        $coreWeightTotal = array_sum($academicWeights) + $attendanceWeight;
        $isRecitationCore = (abs(($coreWeightTotal + $recitationWeight) - 100.0) < 0.01 && abs($coreWeightTotal - 100.0) > 0.01);

        $sumWeighted = 0.0;
        $availableWeight = 0.0;

        foreach (['activity', 'laboratory', 'quiz', 'exam'] as $cat) {
            $weight = $academicWeights[$cat];
            $earned = (float) ($categories[$cat]['earned'] ?? 0.0);
            $possible = (float) ($categories[$cat]['possible'] ?? 0.0);

            if ($weight > 0 && $possible > 0) {
                $compGrade = 5.0 - (4.0 * ($earned / $possible));
                $sumWeighted += ($compGrade * ($weight / 100.0));
                $availableWeight += ($weight / 100.0);
            }
        }

        if ($academicWeights['project'] > 0 && ($projectMetrics['possible'] ?? 0) > 0) {
            $compGrade = 5.0 - (4.0 * ((float) $projectMetrics['earned'] / (float) $projectMetrics['possible']));
            $sumWeighted += ($compGrade * ($academicWeights['project'] / 100.0));
            $availableWeight += ($academicWeights['project'] / 100.0);
        }

        if ($attendanceWeight > 0 && ($attendanceMetrics['possible_points'] ?? 0) > 0) {
            $compGrade = 5.0 - (4.0 * ((float) $attendanceMetrics['earned_points'] / (float) $attendanceMetrics['possible_points']));
            $sumWeighted += ($compGrade * ($attendanceWeight / 100.0));
            $availableWeight += ($attendanceWeight / 100.0);
        }

        if ($isRecitationCore && $recitationWeight > 0 && $recitationPct !== null) {
            $compGrade = 5.0 - (4.0 * ($recitationPct / 100.0));
            $sumWeighted += ($compGrade * ($recitationWeight / 100.0));
            $availableWeight += ($recitationWeight / 100.0);
        }

        if ($availableWeight <= 0) {
            return null;
        }

        $normalizedSum = $sumWeighted / $availableWeight;

        return round($normalizedSum, 1, PHP_ROUND_HALF_UP);
    }

    /**
     * @return array{Collection, Collection, Collection, Collection, Collection, Collection}
     */
    protected function loadData(Section $section): array
    {
        $assessments = Assessment::where('section_id', $section->id)->orderBy('conducted_on')->orderBy('id')
            ->get(['id', 'type', 'term_period', 'assessment_number', 'title', 'conducted_on', 'max_points', 'created_at']);
        $students = Student::where('section_id', $section->id)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'student_number', 'first_name', 'middle_name', 'last_name']);
        $scores = AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))
            ->get(['assessment_id', 'student_id', 'score', 'remarks'])
            ->groupBy('student_id')
            ->map->keyBy('assessment_id');

        $projects = Project::where('section_id', $section->id)
            ->orderBy('conducted_on')
            ->orderBy('id')
            ->with(['groups.members'])
            ->get();

        $attendanceSessions = AttendanceSession::where('section_id', $section->id)
            ->orderBy('session_date')
            ->with('records:id,attendance_session_id,student_id,status,attended_minutes,points_awarded')
            ->get();

        $recitations = Recitation::where('section_id', $section->id)
            ->get(['id', 'student_id', 'score', 'conducted_on', 'created_at'])
            ->groupBy('student_id');

        return [$assessments, $students, $scores, $projects, $attendanceSessions, $recitations];
    }
}
