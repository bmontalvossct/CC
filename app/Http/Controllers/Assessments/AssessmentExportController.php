<?php

namespace App\Http\Controllers\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
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
        $headers = ['Student number', 'Student'];
        foreach ($assessments as $assessment) {
            $headers[] = "{$assessment->title} ({$assessment->max_points})";
        }
        foreach (Assessment::TYPES as $type) {
            array_push($headers, ucfirst($type).' earned', ucfirst($type).' possible', ucfirst($type).' percent');
        }

        $rows = Student::where('section_id', $section->id)->orderBy('last_name')->orderBy('first_name')->get()
            ->map(function ($student) use ($assessments, $scores) {
                $studentScores = $scores->get($student->id, collect())->keyBy('assessment_id');
                $row = [$student->student_number, $student->full_name];
                foreach ($assessments as $assessment) {
                    $row[] = $studentScores->get($assessment->id)?->score;
                }
                foreach (Assessment::TYPES as $type) {
                    $items = $assessments->where('type', $type);
                    $earned = $items->sum(fn ($item) => (float) ($studentScores->get($item->id)?->score ?? 0));
                    $possible = $items->sum(fn ($item) => (float) $item->max_points);
                    array_push($row, $earned, $possible, $possible > 0 ? round($earned / $possible * 100, 2) : null);
                }

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
