<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Requests\Assessments\StoreAssessmentRequest;
use App\Http\Requests\Assessments\UpdateAssessmentRequest;
use App\Models\Assessment;
use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends AssessmentModuleController
{
    public function index(Section $section): Response
    {
        $this->authorizeSection($section);
        $type = request('type');

        $assessments = Assessment::query()
            ->where('section_id', $section->id)
            ->when(in_array($type, Assessment::TYPES, true), fn ($query) => $query->where('type', $type))
            ->withCount([
                'scores',
                'scores as graded_count' => fn ($query) => $query->whereNotNull('score'),
            ])
            ->withSum('scores as points_awarded', 'score')
            ->latest('conducted_on')
            ->latest('id')
            ->get();

        $projects = Project::query()
            ->where('section_id', $section->id)
            ->withCount(['groups', 'members'])
            ->latest('conducted_on')
            ->latest('id')
            ->get();

        $activeStudentsCount = Student::query()
            ->where('section_id', $section->id)
            ->where('is_active', true)
            ->count();

        return Inertia::render('assessments/Index', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'assessments' => $assessments,
            'projects' => $projects,
            'activeStudentsCount' => $activeStudentsCount,
            'filter' => in_array($type, [...Assessment::TYPES, 'project'], true) ? $type : 'all',
            'attendanceSessions' => AttendanceSession::query()
                ->where('section_id', $section->id)
                ->latest('session_date')
                ->get(['id', 'session_date', 'starts_at']),
        ]);
    }


    public function store(StoreAssessmentRequest $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section);
        $data = $request->validated();
        $sessionId = $data['attendance_session_id'] ?? null;
        $this->validateSession($section, $sessionId);

        if (! $sessionId) {
            $matches = AttendanceSession::query()
                ->where('section_id', $section->id)
                ->whereDate('session_date', $data['conducted_on'])
                ->pluck('id');
            $sessionId = $matches->count() === 1 ? $matches->first() : null;
        }

        unset($data['attachment']);
        $data['attendance_session_id'] = $sessionId;
        $data['section_id'] = $section->id;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store("assessments/{$section->id}", 'local');
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_mime'] = $file->getMimeType();
        }

        $assessment = Assessment::create($data);

        return to_route('sections.assessments.show', [$section, $assessment])
            ->with('success', 'Assessment created. Start entering scores.');
    }

    public function show(Section $section, Assessment $assessment): Response
    {
        $this->authorizeAssessment($section, $assessment);
        $assessment->load(['scores:id,assessment_id,student_id,score,absence_override', 'attendanceSession:id,session_date,starts_at,ends_at']);

        $students = Student::query()
            ->where('students.section_id', $section->id)
            ->where('students.is_active', true)
            ->leftJoin('seats', 'seats.student_id', '=', 'students.id')
            ->leftJoin('layout_blocks', 'layout_blocks.id', '=', 'seats.layout_block_id')
            ->leftJoin('attendance_records', function ($join) use ($assessment) {
                $join->on('attendance_records.student_id', '=', 'students.id')
                    ->where('attendance_records.attendance_session_id', $assessment->attendance_session_id ?? 0);
            })
            ->orderByRaw('CASE WHEN seats.id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('layout_blocks.block_row')
            ->orderBy('layout_blocks.block_column')
            ->orderBy('seats.row_number')
            ->orderBy('seats.column_number')
            ->orderBy('students.last_name')
            ->get([
                'students.id', 'students.student_number', 'students.first_name', 'students.middle_name',
                'students.last_name', 'students.photo_path', 'seats.label as seat_label',
                'attendance_records.status as attendance_status',
            ]);

        $scores = $assessment->scores->keyBy('student_id');
        $roster = $students->map(function ($student) use ($scores) {
            $saved = $scores->get($student->id);

            return [
                ...$student->toArray(),
                'full_name' => trim("{$student->last_name}, {$student->first_name} {$student->middle_name}"),
                'is_absent' => $student->attendance_status === 'absent',
                'score' => $saved?->score,
                'absence_override' => (bool) ($saved?->absence_override ?? false),
            ];
        });

        $graded = $assessment->scores->whereNotNull('score');

        return Inertia::render('assessments/Show', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'assessment' => $assessment,
            'students' => $roster,
            'summary' => [
                'graded' => $graded->count(),
                'missing' => max(0, $roster->where('is_absent', false)->count() - $graded->count()),
                'absent' => $roster->where('is_absent', true)->count(),
                'average' => $graded->count() ? round((float) $graded->avg('score'), 2) : null,
            ],
            'attendanceSessions' => AttendanceSession::query()
                ->where('section_id', $section->id)
                ->latest('session_date')
                ->get(['id', 'session_date', 'starts_at']),
        ]);
    }

    public function update(UpdateAssessmentRequest $request, Section $section, Assessment $assessment): RedirectResponse
    {
        $this->authorizeAssessment($section, $assessment);
        $data = $request->validated();
        $this->validateSession($section, $data['attendance_session_id'] ?? null);

        if (isset($data['max_points']) && $assessment->scores()->where('score', '>', $data['max_points'])->exists()) {
            throw ValidationException::withMessages(['max_points' => 'The maximum cannot be lower than an existing score.']);
        }

        unset($data['attachment']);
        if ($request->hasFile('attachment')) {
            if ($assessment->attachment_path) {
                Storage::disk('local')->delete($assessment->attachment_path);
            }
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store("assessments/{$section->id}", 'local');
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_mime'] = $file->getMimeType();
        }
        $assessment->update($data);

        return back()->with('success', 'Assessment updated.');
    }

    public function destroy(Section $section, Assessment $assessment): RedirectResponse
    {
        $this->authorizeAssessment($section, $assessment);
        if ($assessment->attachment_path) {
            Storage::disk('local')->delete($assessment->attachment_path);
        }
        $assessment->delete();

        return to_route('sections.assessments.index', $section)->with('success', 'Assessment deleted.');
    }

    private function validateSession(Section $section, mixed $sessionId): void
    {
        if ($sessionId && ! AttendanceSession::whereKey($sessionId)->where('section_id', $section->id)->exists()) {
            throw ValidationException::withMessages(['attendance_session_id' => 'Select an attendance session from this section.']);
        }
    }
}
