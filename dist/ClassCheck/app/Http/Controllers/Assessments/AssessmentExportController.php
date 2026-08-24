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
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentExportController extends AssessmentModuleController
{
    public function roster(Section $section): StreamedResponse
    {
        $this->authorizeSection($section);
        $rows = Student::where('section_id', $section->id)->orderBy('last_name')->orderBy('first_name')->get()
            ->map(fn ($student) => [$student->student_number, $student->last_name, $student->first_name, $student->middle_name]);

        return $this->csv($section, 'roster', ['Student number', 'Last name', 'First name', 'Middle name'], $rows);
    }

    public function attendance(Section $section): StreamedResponse
    {
        $this->authorizeSection($section);
        $rows = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->where('attendance_sessions.section_id', $section->id)
            ->orderBy('attendance_sessions.session_date')->orderBy('students.last_name')
            ->get(['attendance_sessions.session_date', 'attendance_sessions.starts_at', 'attendance_sessions.ends_at',
                'students.student_number', 'students.last_name', 'students.first_name',
                'attendance_records.status', 'attendance_records.attended_minutes'])
            ->map(fn ($row) => [$row->session_date, $row->starts_at, $row->ends_at, $row->student_number,
                "{$row->last_name}, {$row->first_name}", $row->status, $row->attended_minutes]);

        return $this->csv($section, 'attendance', ['Date', 'Start', 'End', 'Student number', 'Student', 'Status', 'Attended minutes'], $rows);
    }

    public function assessment(Section $section, Assessment $assessment): StreamedResponse
    {
        $this->authorizeAssessment($section, $assessment);
        $scores = $assessment->scores()->get()->keyBy('student_id');
        $rows = Student::where('section_id', $section->id)->orderBy('last_name')->orderBy('first_name')->get()
            ->map(fn ($student) => [$student->student_number, $student->full_name, $scores->get($student->id)?->score,
                $assessment->max_points, $scores->get($student->id)?->score === null ? 'Missing' : 'Recorded']);

        return $this->csv($section, "{$assessment->type}-{$assessment->id}",
            ['Student number', 'Student', 'Score', 'Max points', 'Status'], $rows);
    }

    public function gradebook(Section $section): StreamedResponse
    {
        $this->authorizeSection($section);
        $assessments = Assessment::where('section_id', $section->id)->orderBy('conducted_on')->orderBy('id')->get();
        $scores = AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))->get()->groupBy('student_id');

        $projects = Project::where('section_id', $section->id)->orderBy('conducted_on')->orderBy('id')->with(['groups.members'])->get();
        $attendanceSessions = AttendanceSession::where('section_id', $section->id)->with('records')->get();
        $recitations = Recitation::where('section_id', $section->id)->get()->groupBy('student_id');

        $defaultWeights = [
            'activity' => 20,
            'quiz' => 20,
            'exam' => 25,
            'project' => 20,
            'attendance' => 15,
            'recitation' => 5,
        ];
        $weights = array_merge($defaultWeights, $section->grading_weights ?? []);

        $groupActivities = $projects->where('type', 'group_activity');
        $regularProjects = $projects->whereIn('type', ['project', 'reporting']);

        $headers = ['Student number', 'Student'];
        foreach ($assessments as $assessment) {
            $prefix = $assessment->assessment_number ? "{$assessment->assessment_number}: " : '';
            $headers[] = "{$prefix}{$assessment->title} ({$assessment->max_points})";
        }
        foreach ($groupActivities as $gAct) {
            $prefix = $gAct->project_number ? "{$gAct->project_number}: " : '[Group Act] ';
            $maxPts = $gAct->max_points ?: 100;
            $headers[] = "[Group Act] {$prefix}{$gAct->title} ({$maxPts})";
        }
        foreach ($regularProjects as $project) {
            $prefix = $project->project_number ? "{$project->project_number}: " : ($project->type === 'reporting' ? '[Report] ' : '[Project] ');
            $maxPts = $project->max_points ?: 100;
            $headers[] = "{$prefix}{$project->title} ({$maxPts})";
        }

        foreach (Assessment::TYPES as $type) {
            array_push($headers, ucfirst($type).' earned', ucfirst($type).' possible', ucfirst($type).' percent');
        }

        array_push(
            $headers,
            'Project earned',
            'Project possible',
            'Project percent',
            'Attendance sessions',
            'Attendance present',
            'Attendance late',
            'Attendance absent',
            'Attendance percent',
            'Recitation count',
            'Recitation average (/10)',
            'Oral bonus (+pts)',
            'Base grade percent',
            'Final grade with bonus'
        );

        $totalProjectPossible = round($regularProjects->sum(fn ($p) => (float) ($p->max_points ?: 100)), 2);
        $totalSessions = $attendanceSessions->count();

        // Project / Group Activity scores map
        $studentProjectScores = [];
        foreach ($projects as $project) {
            foreach ($project->groups as $group) {
                $groupScore = $group->score !== null ? (float) $group->score : null;
                foreach ($group->members as $member) {
                    $memberScore = $member->score !== null ? (float) $member->score : $groupScore;
                    $studentProjectScores[$member->student_id][$project->id] = $memberScore;
                }
            }
        }

        // Attendance map
        $studentAttendance = [];
        foreach ($attendanceSessions as $session) {
            foreach ($session->records as $record) {
                if (! isset($studentAttendance[$record->student_id])) {
                    $studentAttendance[$record->student_id] = ['present' => 0, 'late' => 0, 'absent' => 0];
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

        $rows = Student::where('section_id', $section->id)->orderBy('last_name')->orderBy('first_name')->get()
            ->map(function ($student) use (
                $assessments,
                $scores,
                $groupActivities,
                $regularProjects,
                $totalProjectPossible,
                $studentProjectScores,
                $totalSessions,
                $studentAttendance,
                $recitations,
                $weights
            ) {
                $studentScores = $scores->get($student->id, collect())->keyBy('assessment_id');
                $row = [$student->student_number, $student->full_name];

                // Assessment scores
                foreach ($assessments as $assessment) {
                    $row[] = $studentScores->get($assessment->id)?->score;
                }

                // Group activity scores
                $projScoresMap = $studentProjectScores[$student->id] ?? [];
                $groupActivityEarned = 0.0;
                foreach ($groupActivities as $gAct) {
                    $score = $projScoresMap[$gAct->id] ?? null;
                    $row[] = $score;
                    if ($score !== null) {
                        $groupActivityEarned += (float) $score;
                    }
                }

                // Regular Project scores
                $projectEarned = 0.0;
                foreach ($regularProjects as $project) {
                    $score = $projScoresMap[$project->id] ?? null;
                    $row[] = $score;
                    if ($score !== null) {
                        $projectEarned += (float) $score;
                    }
                }

                // Recitation summary & additional bonus points
                $studentRecs = $recitations->get($student->id, collect());
                $recitationCount = $studentRecs->count();
                $recitationAvg = $recitationCount > 0 ? round((float) $studentRecs->avg('score'), 2) : null;
                $bonusCap = (float) ($weights['recitation'] ?? 5);
                $earnedBonus = $recitationAvg !== null && $bonusCap > 0
                    ? round(($recitationAvg / 10) * $bonusCap, 2)
                    : 0.0;

                $categoriesPct = [];
                foreach (Assessment::TYPES as $type) {
                    $items = $assessments->where('type', $type);
                    $rawEarned = $items->sum(fn ($item) => (float) ($studentScores->get($item->id)?->score ?? 0));
                    $possible = $items->sum(fn ($item) => (float) $item->max_points);

                    if ($type === 'activity') {
                        $rawEarned += $groupActivityEarned;
                        $possible += $groupActivities->sum(fn ($g) => (float) ($g->max_points ?: 100));
                        $bonus = $earnedBonus;
                    } else {
                        $bonus = 0.0;
                    }

                    $earned = round($rawEarned + $bonus, 2);
                    $pct = $possible > 0 ? min(100.0, round($earned / $possible * 100, 2)) : null;
                    $categoriesPct[$type] = $pct;
                    array_push($row, $earned, $possible, $pct);
                }

                // Project summary (regular projects only)
                $projectPct = $totalProjectPossible > 0 && $regularProjects->isNotEmpty()
                    ? round(($projectEarned / $totalProjectPossible) * 100, 2)
                    : null;
                array_push($row, round($projectEarned, 2), $totalProjectPossible, $projectPct);

                // Attendance summary
                $att = $studentAttendance[$student->id] ?? ['present' => 0, 'late' => 0, 'absent' => 0];
                $presentCount = $att['present'];
                $lateCount = $att['late'];
                $absentCount = $att['absent'];
                $earnedAttendancePts = round(($presentCount * 1.0) + ($lateCount * 0.5), 1);
                $possibleAttendancePts = (float) $totalSessions;
                $attendancePct = $totalSessions > 0 ? round(($earnedAttendancePts / $totalSessions) * 100, 2) : null;
                array_push($row, $totalSessions, $presentCount, $lateCount, $absentCount, $attendancePct);

                // Push recitation info
                array_push($row, $recitationCount, $recitationAvg, $earnedBonus);

                // Weighted coursework score (Oral bonus is already in Activity score)
                $weighted = 0.0;
                $totalBaseWeight = 0;
                if ($categoriesPct['activity'] !== null && $weights['activity'] > 0) {
                    $weighted += $categoriesPct['activity'] * ($weights['activity'] / 100);
                    $totalBaseWeight += $weights['activity'];
                }
                if ($categoriesPct['quiz'] !== null && $weights['quiz'] > 0) {
                    $weighted += $categoriesPct['quiz'] * ($weights['quiz'] / 100);
                    $totalBaseWeight += $weights['quiz'];
                }
                if ($categoriesPct['exam'] !== null && $weights['exam'] > 0) {
                    $weighted += $categoriesPct['exam'] * ($weights['exam'] / 100);
                    $totalBaseWeight += $weights['exam'];
                }
                if ($projectPct !== null && $weights['project'] > 0) {
                    $weighted += $projectPct * ($weights['project'] / 100);
                    $totalBaseWeight += $weights['project'];
                }
                if ($attendancePct !== null && $weights['attendance'] > 0) {
                    $weighted += $attendancePct * ($weights['attendance'] / 100);
                    $totalBaseWeight += $weights['attendance'];
                }

                $finalGrade = $totalBaseWeight > 0 ? min(100.0, round($weighted, 2)) : null;

                array_push($row, $finalGrade, $finalGrade);

                return $row;
            });

        return $this->csv($section, 'gradebook', $headers, $rows);
    }

    private function csv(Section $section, string $suffix, array $headers, iterable $rows): StreamedResponse
    {
        $safeName = str($section->name)->slug();

        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, array_map([$this, 'safeCell'], (array) $row));
            }
            fclose($output);
        }, "{$safeName}-{$suffix}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function safeCell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
