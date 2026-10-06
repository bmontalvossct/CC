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
use Illuminate\Http\Request;
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

        $availableGroupProjects = Project::query()
            ->where('section_id', $section->id)
            ->where('format', '!=', 'individual')
            ->has('groups')
            ->withCount(['groups', 'members'])
            ->with(['groups.members.student:id,student_number,first_name,last_name,middle_name'])
            ->latest('conducted_on')
            ->latest('id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'type' => $p->type,
                'format' => $p->format,
                'conducted_on' => $p->conducted_on?->toDateString(),
                'groups_count' => $p->groups_count,
                'members_count' => $p->members_count,
                'groups' => $p->groups->map(fn ($g) => [
                    'id' => $g->id,
                    'group_number' => $g->group_number,
                    'name' => $g->name,
                    'topic' => $g->topic,
                    'members' => $g->members->map(fn ($m) => [
                        'student_id' => $m->student_id,
                        'full_name' => $m->student ? trim("{$m->student->last_name}, {$m->student->first_name}") : 'Unknown Student',
                    ]),
                ]),
            ]);

        return Inertia::render('assessments/Index', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'assessments' => $assessments,
            'projects' => $projects,
            'availableGroupProjects' => $availableGroupProjects,
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

        if (empty($data['assessment_number'])) {
            $count = Assessment::where('section_id', $section->id)->where('type', $data['type'])->count();
            $prefix = $data['type'] === 'laboratory' ? 'Lab' : ucfirst($data['type']);
            $data['assessment_number'] = "{$prefix} ".($count + 1);
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $stored = app(\App\Services\SectionFolderService::class)->storeAssessmentAttachment(
                $section,
                $file,
                $data['type'] ?? 'activity',
                $data['assessment_number'] ?? null,
                $data['title'] ?? null
            );
            $data['attachment_path'] = $stored['path'];
            $data['attachment_name'] = $stored['name'];
            $data['attachment_mime'] = $stored['mime'];
        }

        $assessment = Assessment::create($data);

        return to_route('sections.assessments.show', [$section, $assessment])
            ->with('success', 'Assessment created. Start entering scores.');
    }

    public function show(Section $section, Assessment $assessment): Response
    {
        $this->authorizeAssessment($section, $assessment);
        $assessment->load(['scores:id,assessment_id,student_id,score,remarks,absence_override,attachment_path,attachment_name,attachment_mime', 'attendanceSession:id,session_date,starts_at,ends_at']);

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
                'remarks' => $saved?->remarks,
                'absence_override' => (bool) ($saved?->absence_override ?? false),
                'attachment_path' => $saved?->attachment_path,
                'attachment_name' => $saved?->attachment_name,
                'attachment_mime' => $saved?->attachment_mime,
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

        unset($data['attachment'], $data['remove_attachment']);

        if ($request->boolean('remove_attachment')) {
            if ($assessment->attachment_path) {
                Storage::disk('local')->delete($assessment->attachment_path);
            }
            $data['attachment_path'] = null;
            $data['attachment_name'] = null;
            $data['attachment_mime'] = null;
        } elseif ($request->hasFile('attachment')) {
            if ($assessment->attachment_path) {
                Storage::disk('local')->delete($assessment->attachment_path);
            }
            $file = $request->file('attachment');
            $type = $data['type'] ?? $assessment->type;
            $stored = app(\App\Services\SectionFolderService::class)->storeAssessmentAttachment(
                $section,
                $file,
                $type,
                $data['assessment_number'] ?? $assessment->assessment_number,
                $data['title'] ?? $assessment->title
            );
            $data['attachment_path'] = $stored['path'];
            $data['attachment_name'] = $stored['name'];
            $data['attachment_mime'] = $stored['mime'];
        }

        $assessment->update($data);

        return back()->with('success', 'Assessment updated.');
    }

    public function reuploadAttachment(Request $request, Section $section, Assessment $assessment): RedirectResponse
    {
        $this->authorizeAssessment($section, $assessment);

        $request->validate([
            'attachment' => ['required', 'file', 'max:51200', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,rtf,odt,ods,odp,svg,gif,bmp,heic,pages,numbers,key,json,sql,db,sqlite,sqlite3'],
        ], [
            'attachment.max' => 'The attachment must not be larger than 50MB.',
            'attachment.extensions' => 'The attachment must be a valid file type.',
        ]);

        if ($assessment->attachment_path) {
            Storage::disk('local')->delete($assessment->attachment_path);
        }

        $file = $request->file('attachment');
        $stored = app(\App\Services\SectionFolderService::class)->storeAssessmentAttachment(
            $section,
            $file,
            $assessment->type,
            $assessment->assessment_number,
            $assessment->title
        );

        $assessment->update([
            'attachment_path' => $stored['path'],
            'attachment_name' => $stored['name'],
            'attachment_mime' => $stored['mime'],
        ]);

        return back()->with('success', 'Attachment reuploaded successfully.');
    }

    public function destroyAttachment(Section $section, Assessment $assessment): RedirectResponse
    {
        $this->authorizeAssessment($section, $assessment);

        if ($assessment->attachment_path) {
            Storage::disk('local')->delete($assessment->attachment_path);
        }

        $assessment->update([
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_mime' => null,
        ]);

        return back()->with('success', 'Attachment deleted successfully.');
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

    public function saveRubric(Request $request, Section $section, Assessment $assessment)
    {
        $this->authorizeAssessment($section, $assessment);

        if ($request->has('rubric_data') && is_string($request->input('rubric_data'))) {
            $decoded = json_decode($request->input('rubric_data'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['rubric_data' => $decoded]);
            }
        }

        $validated = $request->validate([
            'rubric_type' => ['nullable', 'string', 'in:percentage,answer_key,file,custom'],
            'rubric_data' => ['nullable', 'array'],
            'attachment' => ['nullable', 'file', 'max:25600'],
            'remove_attachment' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_attachment')) {
            if ($assessment->attachment_path) {
                Storage::disk('local')->delete($assessment->attachment_path);
            }
            $assessment->attachment_path = null;
            $assessment->attachment_name = null;
            $assessment->attachment_mime = null;
        }

        if ($request->hasFile('attachment')) {
            if ($assessment->attachment_path) {
                Storage::disk('local')->delete($assessment->attachment_path);
            }
            $stored = app(\App\Services\SectionFolderService::class)->storeAssessmentAttachment(
                $section,
                $request->file('attachment'),
                $assessment->type ?? 'activity',
                $assessment->assessment_number ?? null,
                $assessment->title ?? null
            );
            $assessment->attachment_path = $stored['path'];
            $assessment->attachment_name = $stored['name'];
            $assessment->attachment_mime = $stored['mime'];
        }

        $assessment->rubric_type = $validated['rubric_type'] ?? null;
        $assessment->rubric_data = $validated['rubric_data'] ?? null;
        $assessment->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Rubric configuration successfully saved.',
                'assessment' => $assessment->fresh(),
            ]);
        }

        return back()->with('success', 'Rubric configuration successfully saved.');
    }

    public function studyRubric(Request $request, Section $section, Assessment $assessment)
    {
        $this->authorizeAssessment($section, $assessment);

        $validated = $request->validate([
            'raw_text' => ['nullable', 'string'],
        ]);

        $filePath = null;
        $fileName = null;

        if ($assessment->attachment_path) {
            $grader = app(\App\Services\Autochecker\AiDocumentGraderService::class);
            $filePath = $grader->resolveFilePath($assessment->attachment_path);
            $fileName = $assessment->attachment_name;
        }

        try {
            $grader = app(\App\Services\Autochecker\AiDocumentGraderService::class);
            $result = $grader->studyRubricDocument(
                filePath: $filePath,
                fileName: $fileName,
                maxPoints: (float) $assessment->max_points,
                rawText: $validated['raw_text'] ?? null
            );

            return response()->json([
                'success' => true,
                'studied' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422);
        }
    }

    private function validateSession(Section $section, mixed $sessionId): void
    {
        if ($sessionId && ! AttendanceSession::whereKey($sessionId)->where('section_id', $section->id)->exists()) {
            throw ValidationException::withMessages(['attendance_session_id' => 'Select an attendance session from this section.']);
        }
    }
}
