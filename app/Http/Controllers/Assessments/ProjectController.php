<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\AttendanceRecord;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectGroupMember;
use App\Models\Section;
use App\Models\Student;
use App\Services\Autochecker\AiDocumentGraderService;
use App\Services\SectionFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends AssessmentModuleController
{
    public function index(Section $section): Response
    {
        $this->authorizeSection($section);

        $projects = Project::query()
            ->where('section_id', $section->id)
            ->withCount(['groups', 'members'])
            ->latest('conducted_on')
            ->latest('id')
            ->get();

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

        return Inertia::render('projects/Index', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'projects' => $projects,
            'availableGroupProjects' => $availableGroupProjects,
        ]);
    }

    public function store(StoreProjectRequest $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section);
        $data = $request->validated();

        $sourceProjectId = $data['source_project_id'] ?? null;
        $copyTopics = (bool) ($data['copy_topics'] ?? false);
        $copyNames = (bool) ($data['copy_names'] ?? true);
        $groupCount = $data['group_count'] ?? null;
        $groupSize = $data['group_size'] ?? null;
        $shouldRandomize = (bool) ($data['randomize'] ?? false);
        $format = $data['format'] ?? 'group';

        unset(
            $data['group_count'],
            $data['group_size'],
            $data['randomize'],
            $data['attachment'],
            $data['source_project_id'],
            $data['copy_topics'],
            $data['copy_names']
        );
        $data['section_id'] = $section->id;
        $data['format'] = $format;

        if (empty($data['project_number'])) {
            $count = Project::where('section_id', $section->id)->where('type', $data['type'])->count();
            if ($data['type'] === 'group_activity') {
                $prefix = 'Activity';
            } elseif ($data['type'] === 'reporting') {
                $prefix = 'Report';
            } else {
                $prefix = 'Project';
            }
            $data['project_number'] = "{$prefix} ".($count + 1);
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $stored = app(SectionFolderService::class)->storeProjectAttachment(
                $section,
                $file,
                $data['type'] ?? 'project',
                $data['project_number'] ?? null,
                $data['title'] ?? null
            );
            $data['attachment_path'] = $stored['path'];
            $data['attachment_name'] = $stored['name'];
            $data['attachment_mime'] = $stored['mime'];
        }

        $project = DB::transaction(function () use (
            $data,
            $section,
            $sourceProjectId,
            $copyTopics,
            $copyNames,
            $groupCount,
            $groupSize,
            $shouldRandomize,
            $format
        ) {
            $project = Project::create($data);

            $activeStudents = Student::query()
                ->where('section_id', $section->id)
                ->where('is_active', true)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            $studentCount = $activeStudents->count();

            if ($format === 'individual') {
                // In individual reporting format, create a 1-student slot for each active student
                foreach ($activeStudents as $index => $student) {
                    $group = $project->groups()->create([
                        'group_number' => $index + 1,
                        'name' => $student->full_name ?: 'Student '.($index + 1),
                        'order_column' => $index + 1,
                    ]);
                    $group->members()->create([
                        'student_id' => $student->id,
                    ]);
                }
            } elseif ($sourceProjectId) {
                $sourceProject = Project::where('section_id', $section->id)
                    ->with(['groups.members'])
                    ->find($sourceProjectId);

                if ($sourceProject && $sourceProject->groups->isNotEmpty()) {
                    $activeLookup = $activeStudents->keyBy('id');
                    foreach ($sourceProject->groups as $srcGroup) {
                        $newGroup = $project->groups()->create([
                            'group_number' => $srcGroup->group_number,
                            'name' => $copyNames ? $srcGroup->name : "Group {$srcGroup->group_number}",
                            'topic' => $copyTopics ? $srcGroup->topic : null,
                            'description' => $copyTopics ? $srcGroup->description : null,
                            'order_column' => $srcGroup->order_column ?: $srcGroup->group_number,
                        ]);

                        foreach ($srcGroup->members as $srcMember) {
                            if ($activeLookup->has($srcMember->student_id)) {
                                $newGroup->members()->create([
                                    'student_id' => $srcMember->student_id,
                                    'role' => $srcMember->role,
                                ]);
                            }
                        }
                    }
                }
            } elseif ($groupCount || $groupSize || $shouldRandomize) {
                $k = $groupCount ? (int) $groupCount : null;
                if (! $k && $groupSize && $studentCount > 0) {
                    $k = max(1, (int) floor($studentCount / (int) $groupSize));
                }
                $k = max(1, $k ?: 2);

                if ($shouldRandomize && $studentCount > 0) {
                    $k = min($k, $studentCount);
                    $this->executeRandomization($project, $activeStudents, $k);
                } else {
                    for ($i = 1; $i <= $k; $i++) {
                        $project->groups()->create([
                            'group_number' => $i,
                            'name' => "Group {$i}",
                            'order_column' => $i,
                        ]);
                    }
                }
            }

            return $project;
        });

        return to_route('sections.projects.show', [$section, $project])
            ->with('success', ucfirst(str_replace('_', ' ', $project->type)).' created successfully.');
    }

    public function show(Section $section, Project $project): Response
    {
        $this->authorizeProject($section, $project);

        $students = Student::query()
            ->where('students.section_id', $section->id)
            ->where('students.is_active', true)
            ->leftJoin('seats', 'seats.student_id', '=', 'students.id')
            ->leftJoin('layout_blocks', 'layout_blocks.id', '=', 'seats.layout_block_id')
            ->orderByRaw('CASE WHEN seats.id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('layout_blocks.block_row')
            ->orderBy('layout_blocks.block_column')
            ->orderBy('seats.row_number')
            ->orderBy('seats.column_number')
            ->orderBy('students.last_name')
            ->get([
                'students.id', 'students.student_number', 'students.first_name', 'students.middle_name',
                'students.last_name', 'students.photo_path', 'seats.label as seat_label',
            ]);

        $studentsById = $students->keyBy('id');

        // Absence count lookup
        $absencesByStudent = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.section_id', $section->id)
            ->where('attendance_records.status', 'absent')
            ->selectRaw('student_id, count(*) as absent_count')
            ->groupBy('student_id')
            ->pluck('absent_count', 'student_id')
            ->all();

        $groups = $project->groups()
            ->with(['members.student:id,student_number,first_name,last_name,middle_name,photo_path'])
            ->orderBy('order_column')
            ->orderBy('group_number')
            ->get()
            ->map(function ($group) use ($studentsById, $absencesByStudent) {
                $members = $group->members->map(function ($member) use ($studentsById, $absencesByStudent) {
                    $student = $studentsById->get($member->student_id);

                    return [
                        'id' => $member->id,
                        'student_id' => $member->student_id,
                        'role' => $member->role,
                        'score' => $member->score,
                        'notes' => $member->notes,
                        'attachment_path' => $member->attachment_path,
                        'attachment_name' => $member->attachment_name,
                        'attachment_mime' => $member->attachment_mime,
                        'student_number' => $student?->student_number,
                        'first_name' => $student?->first_name,
                        'last_name' => $student?->last_name,
                        'middle_name' => $student?->middle_name,
                        'full_name' => $student ? trim("{$student->last_name}, {$student->first_name} {$student->middle_name}") : 'Unknown Student',
                        'photo_path' => $student?->photo_path,
                        'seat_label' => $student?->seat_label,
                        'absent_count' => (int) ($absencesByStudent[$member->student_id] ?? 0),
                    ];
                });

                return [
                    'id' => $group->id,
                    'project_id' => $group->project_id,
                    'group_number' => $group->group_number,
                    'name' => $group->name,
                    'topic' => $group->topic,
                    'description' => $group->description,
                    'score' => $group->score,
                    'notes' => $group->notes,
                    'attachment_path' => $group->attachment_path,
                    'attachment_name' => $group->attachment_name,
                    'attachment_mime' => $group->attachment_mime,
                    'order_column' => $group->order_column,
                    'members' => $members,
                ];
            });

        $assignedStudentIds = $groups->flatMap(fn ($g) => collect($g['members'])->pluck('student_id'))->all();
        $assignedLookup = array_flip($assignedStudentIds);

        $unassignedStudents = $students->filter(fn ($s) => ! isset($assignedLookup[$s->id]))
            ->map(fn ($s) => [
                'id' => $s->id,
                'student_number' => $s->student_number,
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                'middle_name' => $s->middle_name,
                'full_name' => trim("{$s->last_name}, {$s->first_name} {$s->middle_name}"),
                'photo_path' => $s->photo_path,
                'seat_label' => $s->seat_label,
            ])
            ->values();

        $previousProjects = Project::query()
            ->where('section_id', $section->id)
            ->where('id', '!=', $project->id)
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

        return Inertia::render('projects/Show', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'project' => [
                ...$project->toArray(),
                'groups' => $groups,
            ],
            'totalStudentsCount' => $students->count(),
            'unassignedStudents' => $unassignedStudents,
            'previousProjects' => $previousProjects,
        ]);
    }

    public function update(UpdateProjectRequest $request, Section $section, Project $project): RedirectResponse
    {
        $this->authorizeProject($section, $project);
        $data = $request->validated();

        unset($data['attachment'], $data['remove_attachment']);

        if ($request->boolean('remove_attachment')) {
            if ($project->attachment_path) {
                Storage::disk('local')->delete($project->attachment_path);
            }
            $data['attachment_path'] = null;
            $data['attachment_name'] = null;
            $data['attachment_mime'] = null;
        } elseif ($request->hasFile('attachment')) {
            if ($project->attachment_path) {
                Storage::disk('local')->delete($project->attachment_path);
            }
            $file = $request->file('attachment');
            $type = $data['type'] ?? $project->type;
            $stored = app(SectionFolderService::class)->storeProjectAttachment(
                $section,
                $file,
                $type,
                $data['project_number'] ?? $project->project_number,
                $data['title'] ?? $project->title
            );
            $data['attachment_path'] = $stored['path'];
            $data['attachment_name'] = $stored['name'];
            $data['attachment_mime'] = $stored['mime'];
        }

        $groupCount = $data['group_count'] ?? null;
        unset($data['group_count']);

        $project->update($data);

        // Adjust group count if provided and activity is in group format
        if ($groupCount !== null && ($project->format ?? 'group') !== 'individual') {
            $targetGroupCount = max(1, min((int) $groupCount, 50));
            $currentGroups = $project->groups()->orderBy('group_number')->get();
            $currentGroupCount = $currentGroups->count();

            if ($targetGroupCount > $currentGroupCount) {
                $maxNumber = (int) ($project->groups()->max('group_number') ?? 0);
                for ($i = 1; $i <= ($targetGroupCount - $currentGroupCount); $i++) {
                    $newNumber = $maxNumber + $i;
                    $project->groups()->create([
                        'group_number' => $newNumber,
                        'name' => "Group {$newNumber}",
                        'order_column' => $newNumber,
                    ]);
                }
            } elseif ($targetGroupCount < $currentGroupCount) {
                $groupsToRemove = $project->groups()
                    ->orderByDesc('group_number')
                    ->limit($currentGroupCount - $targetGroupCount)
                    ->get();

                foreach ($groupsToRemove as $grp) {
                    $grp->delete();
                }
            }
        }

        return back()->with('success', 'Project details updated.');
    }

    public function attachment(Request $request, Section $section, Project $project): BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeProject($section, $project);
        abort_unless((int) $project->section_id === (int) $section->id, 404);

        $path = $project->attachment_path;
        abort_unless($path, 404);

        $fullPath = null;
        if (Storage::disk('local')->exists($path)) {
            $fullPath = Storage::disk('local')->path($path);
        } elseif (Storage::disk('public')->exists($path)) {
            $fullPath = Storage::disk('public')->path($path);
        } elseif (file_exists(storage_path('app/'.$path))) {
            $fullPath = storage_path('app/'.$path);
        }

        abort_unless($fullPath && file_exists($fullPath), 404, 'Attachment file not found on disk.');

        $name = $project->attachment_name ?: basename($path);
        $mime = $project->attachment_mime ?: (File::mimeType($fullPath) ?: 'application/octet-stream');

        if ($request->boolean('download') || $request->has('download')) {
            return response()->download($fullPath, $name, [
                'Content-Type' => $mime,
            ]);
        }

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
        ]);
    }

    public function reuploadAttachment(Request $request, Section $section, Project $project): RedirectResponse
    {
        $this->authorizeProject($section, $project);

        $request->validate([
            'attachment' => ['required', 'file', 'max:51200', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,rtf,odt,ods,odp,svg,gif,bmp,heic,pages,numbers,key,json,sql,db,sqlite,sqlite3'],
        ], [
            'attachment.max' => 'The attachment must not be larger than 50MB.',
            'attachment.extensions' => 'The attachment must be a valid file type.',
        ]);

        if ($project->attachment_path) {
            Storage::disk('local')->delete($project->attachment_path);
        }

        $file = $request->file('attachment');
        $stored = app(SectionFolderService::class)->storeProjectAttachment(
            $section,
            $file,
            $project->type,
            $project->project_number,
            $project->title
        );

        $project->update([
            'attachment_path' => $stored['path'],
            'attachment_name' => $stored['name'],
            'attachment_mime' => $stored['mime'],
        ]);

        return back()->with('success', 'Attachment reuploaded successfully.');
    }

    public function destroyAttachment(Section $section, Project $project): RedirectResponse
    {
        $this->authorizeProject($section, $project);

        if ($project->attachment_path) {
            Storage::disk('local')->delete($project->attachment_path);
        }

        $project->update([
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_mime' => null,
        ]);

        return back()->with('success', 'Attachment deleted successfully.');
    }

    public function uploadGroupAttachment(
        Request $request,
        Section $section,
        Project $project,
        ProjectGroup $group,
    ): JsonResponse|RedirectResponse {
        $this->authorizeProject($section, $project);
        abort_unless((int) $group->project_id === (int) $project->id, 404);

        $request->validate([
            'attachment' => ['required', 'file', 'max:51200', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,rtf,odt,ods,odp,svg,gif,bmp,heic,pages,numbers,key,json,sql,db,sqlite,sqlite3'],
        ], [
            'attachment.max' => 'The group output file must not be larger than 50MB.',
            'attachment.extensions' => 'The attachment must be a valid file type.',
        ]);

        $file = $request->file('attachment');
        $stored = app(SectionFolderService::class)->storeGroupProjectOutput($section, $project, $group, $file);

        $group->update([
            'attachment_path' => $stored['path'],
            'attachment_name' => $stored['name'],
            'attachment_mime' => $stored['mime'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Group output {$stored['name']} attached successfully.",
                'group_id' => $group->id,
                'attachment_path' => $group->attachment_path,
                'attachment_name' => $group->attachment_name,
                'attachment_mime' => $group->attachment_mime,
            ]);
        }

        return back()->with('success', "Group output {$stored['name']} attached successfully.");
    }

    public function destroyGroupAttachment(
        Request $request,
        Section $section,
        Project $project,
        ProjectGroup $group,
    ): JsonResponse|RedirectResponse {
        $this->authorizeProject($section, $project);
        abort_unless((int) $group->project_id === (int) $project->id, 404);

        if ($group->attachment_path) {
            Storage::disk('local')->delete($group->attachment_path);
            $group->update([
                'attachment_path' => null,
                'attachment_name' => null,
                'attachment_mime' => null,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Group output deleted.',
                'group_id' => $group->id,
            ]);
        }

        return back()->with('success', 'Group output deleted.');
    }

    public function groupAttachment(
        Request $request,
        Section $section,
        Project $project,
        ProjectGroup $group,
    ): BinaryFileResponse|\Symfony\Component\HttpFoundation\Response {
        $this->authorizeProject($section, $project);
        abort_unless((int) $group->project_id === (int) $project->id, 404);
        abort_unless($group->attachment_path, 404, 'No group output attached.');

        $path = $group->attachment_path;
        $fullPath = null;
        if (Storage::disk('local')->exists($path)) {
            $fullPath = Storage::disk('local')->path($path);
        } elseif (file_exists(storage_path('app/'.$path))) {
            $fullPath = storage_path('app/'.$path);
        }

        abort_unless($fullPath && file_exists($fullPath), 404, 'Group output file not found on disk.');

        $name = $group->attachment_name ?: basename($path);
        $mime = $group->attachment_mime ?: (File::mimeType($fullPath) ?: 'application/octet-stream');

        if ($request->boolean('download') || $request->has('download')) {
            return response()->download($fullPath, $name, [
                'Content-Type' => $mime,
            ]);
        }

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
        ]);
    }

    public function uploadMemberAttachment(
        Request $request,
        Section $section,
        Project $project,
        ProjectGroup $group,
        Student $student,
    ): JsonResponse|RedirectResponse {
        $this->authorizeProject($section, $project);
        abort_unless((int) $group->project_id === (int) $project->id, 404);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        $member = $group->members()->firstOrNew(['student_id' => $student->id]);

        $request->validate([
            'attachment' => ['required', 'file', 'max:51200', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,rtf,odt,ods,odp,svg,gif,bmp,heic,pages,numbers,key,json,sql,db,sqlite,sqlite3'],
        ], [
            'attachment.max' => 'The student output file must not be larger than 50MB.',
            'attachment.extensions' => 'The attachment must be a valid file type.',
        ]);

        $file = $request->file('attachment');
        $stored = app(SectionFolderService::class)->storeStudentProjectOutput($section, $project, $student, $file);

        $member->fill([
            'attachment_path' => $stored['path'],
            'attachment_name' => $stored['name'],
            'attachment_mime' => $stored['mime'],
        ]);
        $member->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Student output {$stored['name']} attached successfully.",
                'student_id' => $student->id,
                'attachment_path' => $member->attachment_path,
                'attachment_name' => $member->attachment_name,
                'attachment_mime' => $member->attachment_mime,
            ]);
        }

        return back()->with('success', "Student output {$stored['name']} attached successfully.");
    }

    public function destroyMemberAttachment(
        Request $request,
        Section $section,
        Project $project,
        ProjectGroup $group,
        Student $student,
    ): JsonResponse|RedirectResponse {
        $this->authorizeProject($section, $project);
        abort_unless((int) $group->project_id === (int) $project->id, 404);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        $member = $group->members()->where('student_id', $student->id)->first();
        if ($member && $member->attachment_path) {
            Storage::disk('local')->delete($member->attachment_path);
            $member->update([
                'attachment_path' => null,
                'attachment_name' => null,
                'attachment_mime' => null,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Student output deleted.',
                'student_id' => $student->id,
            ]);
        }

        return back()->with('success', 'Student output deleted.');
    }

    public function memberAttachment(
        Request $request,
        Section $section,
        Project $project,
        ProjectGroup $group,
        Student $student,
    ): BinaryFileResponse|\Symfony\Component\HttpFoundation\Response {
        $this->authorizeProject($section, $project);
        abort_unless((int) $group->project_id === (int) $project->id, 404);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        $member = $group->members()->where('student_id', $student->id)->first();
        abort_unless($member && $member->attachment_path, 404, 'No student output attached.');

        $path = $member->attachment_path;
        $fullPath = null;
        if (Storage::disk('local')->exists($path)) {
            $fullPath = Storage::disk('local')->path($path);
        } elseif (file_exists(storage_path('app/'.$path))) {
            $fullPath = storage_path('app/'.$path);
        }

        abort_unless($fullPath && file_exists($fullPath), 404, 'Student output file not found on disk.');

        $name = $member->attachment_name ?: basename($path);
        $mime = $member->attachment_mime ?: (File::mimeType($fullPath) ?: 'application/octet-stream');

        if ($request->boolean('download') || $request->has('download')) {
            return response()->download($fullPath, $name, [
                'Content-Type' => $mime,
            ]);
        }

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
        ]);
    }

    public function destroy(Section $section, Project $project): RedirectResponse
    {
        $this->authorizeProject($section, $project);
        if ($project->attachment_path) {
            Storage::disk('local')->delete($project->attachment_path);
        }
        $project->delete();

        return to_route('sections.assessments.index', $section)->with('success', 'Project deleted.');
    }

    public function randomize(Request $request, Section $section, Project $project): RedirectResponse
    {
        $this->authorizeProject($section, $project);

        $request->validate([
            'group_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'group_size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'preserve_topics' => ['nullable', 'boolean'],
        ]);

        $activeStudents = Student::query()
            ->where('section_id', $section->id)
            ->where('is_active', true)
            ->get();

        $n = $activeStudents->count();
        if ($n === 0) {
            return back()->with('error', 'No active students found in this section to randomize.');
        }

        $k = $request->input('group_count');
        if (! $k && $request->input('group_size')) {
            $size = (int) $request->input('group_size');
            $k = max(1, (int) floor($n / $size));
        }

        if (! $k) {
            $existingCount = $project->groups()->count();
            $k = $existingCount > 0 ? $existingCount : 4;
        }

        $k = max(1, min((int) $k, $n));

        $this->executeRandomization($project, $activeStudents, $k, (bool) $request->input('preserve_topics'));

        return back()->with('success', "Randomized {$n} students into {$k} groups.");
    }

    public function storeGroup(Request $request, Section $section, Project $project): RedirectResponse
    {
        $this->authorizeProject($section, $project);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'topic' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $maxNumber = (int) ($project->groups()->max('group_number') ?? 0);
        $newNumber = $maxNumber + 1;

        $project->groups()->create([
            'group_number' => $newNumber,
            'name' => $data['name'] ?: "Group {$newNumber}",
            'topic' => $data['topic'] ?? null,
            'description' => $data['description'] ?? null,
            'order_column' => $newNumber,
        ]);

        return back()->with('success', "Group {$newNumber} added.");
    }

    public function updateGroup(Request $request, Section $section, Project $project, ProjectGroup $group): JsonResponse|RedirectResponse
    {
        $this->authorizeGroup($project, $group);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'topic' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $group->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'group' => $group->fresh(),
            ]);
        }

        return back()->with('success', 'Group updated.');
    }

    public function destroyGroup(Section $section, Project $project, ProjectGroup $group): RedirectResponse
    {
        $this->authorizeGroup($project, $group);
        $group->delete();

        return back()->with('success', 'Group removed.');
    }

    public function addMember(Request $request, Section $section, Project $project, ProjectGroup $group): RedirectResponse
    {
        $this->authorizeGroup($project, $group);

        $data = $request->validate([
            'student_id' => ['nullable', 'exists:students,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['exists:students,id'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);

        $rawIds = [];
        if (! empty($data['student_ids'])) {
            $rawIds = array_merge($rawIds, (array) $data['student_ids']);
        }
        if (! empty($data['student_id'])) {
            $rawIds[] = $data['student_id'];
        }

        $studentIds = array_values(array_unique(array_filter($rawIds)));

        if (empty($studentIds)) {
            throw ValidationException::withMessages(['student_ids' => 'Please select at least one student.']);
        }

        $students = Student::query()
            ->whereIn('id', $studentIds)
            ->where('section_id', $section->id)
            ->get();

        abort_if($students->isEmpty(), 404);

        DB::transaction(function () use ($project, $group, $students, $data) {
            $otherGroupIds = $project->groups()->pluck('id');

            // Remove students from any other group in this project
            ProjectGroupMember::whereIn('project_group_id', $otherGroupIds)
                ->whereIn('student_id', $students->pluck('id'))
                ->delete();

            foreach ($students as $student) {
                $group->members()->create([
                    'student_id' => $student->id,
                    'role' => $data['role'] ?? null,
                ]);
            }
        });

        $count = $students->count();
        $message = $count === 1
            ? "Added {$students->first()->full_name} to {$group->name}."
            : "Added {$count} students to {$group->name}.";

        return back()->with('success', $message);
    }

    public function updateMember(Request $request, Section $section, Project $project, ProjectGroup $group, Student $student): JsonResponse|RedirectResponse
    {
        $this->authorizeGroup($project, $group);
        $member = $group->members()->where('student_id', $student->id)->firstOrFail();

        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'role' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $member->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'member' => $member->fresh(),
            ]);
        }

        return back()->with('success', "Updated score for {$student->first_name}.");
    }

    public function removeMember(Section $section, Project $project, ProjectGroup $group, Student $student): RedirectResponse
    {
        $this->authorizeGroup($project, $group);

        $group->members()->where('student_id', $student->id)->delete();

        return back()->with('success', "Removed {$student->first_name} from {$group->name}.");
    }

    public function moveMember(Request $request, Section $section, Project $project): RedirectResponse
    {
        $this->authorizeProject($section, $project);

        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'target_group_id' => ['required', 'exists:project_groups,id'],
        ]);

        $targetGroup = ProjectGroup::findOrFail($data['target_group_id']);
        abort_unless((int) $targetGroup->project_id === (int) $project->id, 404);

        $student = Student::findOrFail($data['student_id']);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        DB::transaction(function () use ($project, $targetGroup, $student) {
            $otherGroupIds = $project->groups()->pluck('id');
            ProjectGroupMember::whereIn('project_group_id', $otherGroupIds)
                ->where('student_id', $student->id)
                ->delete();

            $targetGroup->members()->create([
                'student_id' => $student->id,
            ]);
        });

        return back()->with('success', "Moved {$student->first_name} to {$targetGroup->name}.");
    }

    public function copyGrouping(Request $request, Section $section, Project $project): RedirectResponse
    {
        $this->authorizeProject($section, $project);

        $data = $request->validate([
            'source_project_id' => ['required', 'integer', 'exists:projects,id'],
            'copy_topics' => ['nullable', 'boolean'],
            'copy_names' => ['nullable', 'boolean'],
        ]);

        $sourceProject = Project::where('section_id', $section->id)
            ->with(['groups.members'])
            ->findOrFail($data['source_project_id']);

        abort_if((int) $sourceProject->id === (int) $project->id, 422, 'Cannot copy grouping from the same activity.');

        $activeStudents = Student::query()
            ->where('section_id', $section->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $copyTopics = (bool) ($data['copy_topics'] ?? false);
        $copyNames = (bool) ($data['copy_names'] ?? true);

        DB::transaction(function () use ($project, $sourceProject, $copyTopics, $copyNames, $activeStudents) {
            $project->groups()->delete();

            foreach ($sourceProject->groups as $srcGroup) {
                $newGroup = $project->groups()->create([
                    'group_number' => $srcGroup->group_number,
                    'name' => $copyNames ? $srcGroup->name : "Group {$srcGroup->group_number}",
                    'topic' => $copyTopics ? $srcGroup->topic : null,
                    'description' => $copyTopics ? $srcGroup->description : null,
                    'order_column' => $srcGroup->order_column ?: $srcGroup->group_number,
                ]);

                foreach ($srcGroup->members as $srcMember) {
                    if ($activeStudents->has($srcMember->student_id)) {
                        $newGroup->members()->create([
                            'student_id' => $srcMember->student_id,
                            'role' => $srcMember->role,
                        ]);
                    }
                }
            }
        });

        return back()->with('success', "Grouping successfully copied from '{$sourceProject->title}'.");
    }

    public function saveAll(Request $request, Section $section, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorizeProject($section, $project);

        $data = $request->validate([
            'groups' => ['nullable', 'array'],
            'groups.*.id' => ['required', 'integer'],
            'groups.*.topic' => ['nullable', 'string', 'max:5000'],
            'groups.*.description' => ['nullable', 'string', 'max:5000'],
            'groups.*.score' => ['nullable'],
            'groups.*.name' => ['nullable', 'string', 'max:100'],
            'groups.*.notes' => ['nullable', 'string', 'max:10000'],
            'members' => ['nullable', 'array'],
            'members.*.id' => ['required', 'integer'],
            'members.*.score' => ['nullable'],
            'members.*.role' => ['nullable', 'string', 'max:50'],
            'members.*.notes' => ['nullable', 'string', 'max:10000'],
        ]);

        DB::transaction(function () use ($project, $data) {
            if (! empty($data['groups'])) {
                $projectGroupIds = $project->groups()->pluck('id')->all();
                $lookup = array_flip($projectGroupIds);

                foreach ($data['groups'] as $groupData) {
                    if (isset($lookup[$groupData['id']])) {
                        $updateData = [];
                        if (array_key_exists('topic', $groupData)) {
                            $updateData['topic'] = $groupData['topic'];
                        }
                        if (array_key_exists('description', $groupData)) {
                            $updateData['description'] = $groupData['description'];
                        }
                        if (array_key_exists('score', $groupData)) {
                            $updateData['score'] = $groupData['score'] !== '' && $groupData['score'] !== null ? (float) $groupData['score'] : null;
                        }
                        if (array_key_exists('name', $groupData) && ! empty($groupData['name'])) {
                            $updateData['name'] = $groupData['name'];
                        }
                        if (array_key_exists('notes', $groupData)) {
                            $updateData['notes'] = $groupData['notes'];
                        }

                        if (! empty($updateData)) {
                            ProjectGroup::where('id', $groupData['id'])->update($updateData);
                        }
                    }
                }
            }

            if (! empty($data['members'])) {
                $validMemberIds = ProjectGroupMember::whereHas('group', fn ($q) => $q->where('project_id', $project->id))
                    ->pluck('id')
                    ->all();
                $memberLookup = array_flip($validMemberIds);

                foreach ($data['members'] as $memberData) {
                    if (isset($memberLookup[$memberData['id']])) {
                        $updateMember = [];
                        if (array_key_exists('score', $memberData)) {
                            $updateMember['score'] = $memberData['score'] !== '' && $memberData['score'] !== null ? (float) $memberData['score'] : null;
                        }
                        if (array_key_exists('role', $memberData)) {
                            $updateMember['role'] = $memberData['role'];
                        }
                        if (array_key_exists('notes', $memberData)) {
                            $updateMember['notes'] = $memberData['notes'];
                        }

                        if (! empty($updateMember)) {
                            ProjectGroupMember::where('id', $memberData['id'])->update($updateMember);
                        }
                    }
                }
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'All topics and scores saved successfully.',
            ]);
        }

        return back()->with('success', 'All topics and scores saved successfully.');
    }

    public function export(Section $section, Project $project): StreamedResponse
    {
        $this->authorizeProject($section, $project);

        $safeSectionName = str($section->name)->slug();
        $safeProjectTitle = str($project->title)->slug();
        $isIndividual = $project->format === 'individual';

        $project->load([
            'groups.members.student' => function ($q) {
                $q->leftJoin('seats', 'seats.student_id', '=', 'students.id')
                    ->select('students.*', 'seats.label as seat_label');
            },
        ]);

        if ($isIndividual) {
            $headers = [
                '#',
                'Student Number',
                'Last Name',
                'First Name',
                'Middle Name',
                'Seat / Chair',
                'Presentation Topic',
                'Topic Description',
                'Score',
                'Max Points',
                'Notes / Remarks',
            ];

            $rows = [];
            foreach ($project->groups as $index => $group) {
                $member = $group->members->first();
                $student = $member?->student;

                $rows[] = [
                    $group->group_number ?: ($index + 1),
                    $student?->student_number ?? '',
                    $student?->last_name ?? '',
                    $student?->first_name ?? '',
                    $student?->middle_name ?? '',
                    $student?->seat_label ?? '',
                    $group->topic ?? '',
                    $group->description ?? '',
                    $group->score !== null ? (float) $group->score : ($member?->score !== null ? (float) $member->score : ''),
                    $project->max_points ?: '',
                    $group->notes ?: ($member?->notes ?? ''),
                ];
            }

            $filename = "{$safeSectionName}-{$safeProjectTitle}-individual-reporting.csv";
        } else {
            $headers = [
                'Group #',
                'Group Name',
                'Group Topic',
                'Topic Description',
                'Student Number',
                'Student Name',
                'Seat / Chair',
                'Role',
                'Group Score',
                'Member Score Override',
                'Final Score',
                'Max Points',
                'Notes / Remarks',
            ];

            $rows = [];
            foreach ($project->groups as $group) {
                if ($group->members->isEmpty()) {
                    $rows[] = [
                        $group->group_number,
                        $group->name,
                        $group->topic ?? '',
                        $group->description ?? '',
                        '',
                        '',
                        '',
                        '',
                        $group->score !== null ? (float) $group->score : '',
                        '',
                        $group->score !== null ? (float) $group->score : '',
                        $project->max_points ?: '',
                        $group->notes ?? '',
                    ];
                } else {
                    foreach ($group->members as $member) {
                        $student = $member->student;
                        $finalScore = $member->score !== null ? (float) $member->score : ($group->score !== null ? (float) $group->score : '');

                        $rows[] = [
                            $group->group_number,
                            $group->name,
                            $group->topic ?? '',
                            $group->description ?? '',
                            $student?->student_number ?? '',
                            $student ? trim("{$student->last_name}, {$student->first_name} {$student->middle_name}") : '',
                            $student?->seat_label ?? '',
                            $member->role ?? '',
                            $group->score !== null ? (float) $group->score : '',
                            $member->score !== null ? (float) $member->score : '',
                            $finalScore,
                            $project->max_points ?: '',
                            $member->notes ?: ($group->notes ?? ''),
                        ];
                    }
                }
            }

            $suffix = $project->type === 'group_activity' ? 'group-activity' : 'groups';
            $filename = "{$safeSectionName}-{$safeProjectTitle}-{$suffix}.csv";
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, array_map(function ($value) {
                    return is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
                }, $row));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Section $section, Project $project): Response
    {
        $this->authorizeProject($section, $project);

        $project->load(['groups.students' => function ($q) {
            $q->leftJoin('seats', 'seats.student_id', '=', 'students.id')
                ->select('students.*', 'seats.label as seat_label')
                ->orderBy('students.last_name')
                ->orderBy('students.first_name');
        }]);

        return Inertia::render('projects/Print', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'project' => $project,
        ]);
    }

    protected function authorizeProject(Section $section, Project $project): void
    {
        $this->authorizeSection($section);
        abort_unless((int) $project->section_id === (int) $section->id, 404);
    }

    protected function authorizeGroup(Project $project, ProjectGroup $group): void
    {
        abort_unless((int) $group->project_id === (int) $project->id, 404);
    }

    /**
     * Randomization algorithm:
     * Randomizes N active students into K groups.
     * If N is uneven (remainder R > 0), the extra students are assigned to the first R groups
     * (starting with Group 1).
     */
    protected function executeRandomization(Project $project, $activeStudents, int $k, bool $preserveTopics = false): void
    {
        $n = $activeStudents->count();
        $base = (int) floor($n / $k);
        $remainder = $n % $k;

        $shuffled = $activeStudents->shuffle()->values();

        DB::transaction(function () use ($project, $k, $base, $remainder, $shuffled, $preserveTopics) {
            $existingTopics = [];
            if ($preserveTopics) {
                $existingTopics = $project->groups()->pluck('topic', 'group_number')->all();
            }

            $project->groups()->delete();

            $studentIndex = 0;
            for ($i = 1; $i <= $k; $i++) {
                // If remainder > 0, the first R groups get an extra member (+1)
                $groupSize = $base + ($i <= $remainder ? 1 : 0);

                $group = $project->groups()->create([
                    'group_number' => $i,
                    'name' => "Group {$i}",
                    'topic' => $existingTopics[$i] ?? null,
                    'order_column' => $i,
                ]);

                for ($j = 0; $j < $groupSize; $j++) {
                    if (isset($shuffled[$studentIndex])) {
                        $group->members()->create([
                            'student_id' => $shuffled[$studentIndex]->id,
                        ]);
                        $studentIndex++;
                    }
                }
            }
        });
    }

    /**
     * AI analyze the attached group report document against project details & rubrics.
     */
    public function aiCheckGroup(
        Request $request,
        Section $section,
        Project $project,
        ProjectGroup $group,
        AiDocumentGraderService $aiGrader,
    ): JsonResponse {
        $this->authorizeProject($section, $project);
        abort_unless((int) $group->project_id === (int) $project->id, 404);

        if (! $group->attachment_path) {
            return response()->json([
                'success' => false,
                'message' => 'No group output attached to evaluate.',
            ], 422);
        }

        try {
            $result = $aiGrader->gradeProjectGroupSubmission($section, $project, $group);

            $group->update([
                'score' => $result['score'],
                'notes' => $result['remarks'],
            ]);

            return response()->json([
                'success' => true,
                'message' => "AI graded {$group->name}: {$result['score']} pts. Score and notes saved.",
                'group_id' => $group->id,
                'score' => $result['score'],
                'notes' => $result['remarks'],
                'remarks' => $result['remarks'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * AI analyze an individual member's attached report document.
     */
    public function aiCheckMember(
        Request $request,
        Section $section,
        Project $project,
        ProjectGroup $group,
        Student $student,
        AiDocumentGraderService $aiGrader,
    ): JsonResponse {
        $this->authorizeProject($section, $project);
        abort_unless((int) $group->project_id === (int) $project->id, 404);

        $member = $group->members()->where('student_id', $student->id)->first();
        if (! $member || ! $member->attachment_path) {
            return response()->json([
                'success' => false,
                'message' => 'No member output attached to evaluate.',
            ], 422);
        }

        try {
            $result = $aiGrader->gradeProjectMemberSubmission($section, $project, $group, $student);

            $member->update([
                'score' => $result['score'],
                'notes' => $result['remarks'],
            ]);

            return response()->json([
                'success' => true,
                'message' => "AI graded {$student->full_name}: {$result['score']} pts. Score and notes saved.",
                'member_id' => $member->id,
                'student_id' => $student->id,
                'score' => $result['score'],
                'notes' => $result['remarks'],
                'remarks' => $result['remarks'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function saveRubric(Request $request, Section $section, Project $project)
    {
        $this->authorizeProject($section, $project);

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
            if ($project->attachment_path) {
                Storage::disk('local')->delete($project->attachment_path);
            }
            $project->attachment_path = null;
            $project->attachment_name = null;
            $project->attachment_mime = null;
        }

        if ($request->hasFile('attachment')) {
            if ($project->attachment_path) {
                Storage::disk('local')->delete($project->attachment_path);
            }
            $stored = app(SectionFolderService::class)->storeProjectAttachment(
                $section,
                $request->file('attachment'),
                $project->type ?? 'project',
                $project->project_number ?? null,
                $project->title ?? null
            );
            $project->attachment_path = $stored['path'];
            $project->attachment_name = $stored['name'];
            $project->attachment_mime = $stored['mime'];
        }

        $project->rubric_type = $validated['rubric_type'] ?? null;
        $project->rubric_data = $validated['rubric_data'] ?? null;
        $project->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Rubric configuration successfully saved.',
                'project' => $project->fresh(),
            ]);
        }

        return back()->with('success', 'Rubric configuration successfully saved.');
    }

    public function studyRubric(Request $request, Section $section, Project $project)
    {
        $this->authorizeProject($section, $project);

        $validated = $request->validate([
            'raw_text' => ['nullable', 'string'],
        ]);

        $filePath = null;
        $fileName = null;

        if ($project->attachment_path) {
            $grader = app(AiDocumentGraderService::class);
            $filePath = $grader->resolveFilePath($project->attachment_path);
            $fileName = $project->attachment_name;
        }

        try {
            $grader = app(AiDocumentGraderService::class);
            $result = $grader->studyRubricDocument(
                filePath: $filePath,
                fileName: $fileName,
                maxPoints: (float) ($project->max_points ?: 100),
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
}
