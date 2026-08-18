<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectGroupMember;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        return Inertia::render('projects/Index', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'projects' => $projects,
        ]);
    }

    public function store(StoreProjectRequest $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section);
        $data = $request->validated();

        $groupCount = $data['group_count'] ?? null;
        $groupSize = $data['group_size'] ?? null;
        $shouldRandomize = (bool) ($data['randomize'] ?? false);

        unset($data['group_count'], $data['group_size'], $data['randomize'], $data['attachment']);
        $data['section_id'] = $section->id;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store("projects/{$section->id}", 'local');
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_mime'] = $file->getMimeType();
        }

        $project = DB::transaction(function () use ($data, $section, $groupCount, $groupSize, $shouldRandomize) {
            $project = Project::create($data);

            $activeStudents = Student::query()
                ->where('section_id', $section->id)
                ->where('is_active', true)
                ->get();

            $studentCount = $activeStudents->count();

            if ($groupCount || $groupSize || $shouldRandomize) {
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
            ->with('success', 'Project created successfully.');
    }

    public function show(Section $section, Project $project): Response
    {
        $this->authorizeProject($section, $project);

        $students = Student::query()
            ->where('students.section_id', $section->id)
            ->where('students.is_active', true)
            ->leftJoin('seats', 'seats.student_id', '=', 'students.id')
            ->leftJoin('layout_blocks', 'layout_blocks.id', '=', 'seats.layout_block_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->get([
                'students.id', 'students.student_number', 'students.first_name', 'students.middle_name',
                'students.last_name', 'students.photo_path', 'seats.label as seat_label',
            ]);

        $studentsById = $students->keyBy('id');

        $groups = $project->groups()
            ->with(['members'])
            ->get()
            ->map(function (ProjectGroup $group) use ($studentsById) {
                $members = $group->members->map(function ($member) use ($studentsById) {
                    $student = $studentsById->get($member->student_id);

                    return [
                        'id' => $member->id,
                        'student_id' => $member->student_id,
                        'role' => $member->role,
                        'score' => $member->score,
                        'notes' => $member->notes,
                        'student_number' => $student?->student_number,
                        'first_name' => $student?->first_name,
                        'last_name' => $student?->last_name,
                        'middle_name' => $student?->middle_name,
                        'full_name' => $student ? trim("{$student->last_name}, {$student->first_name} {$student->middle_name}") : 'Unknown Student',
                        'photo_path' => $student?->photo_path,
                        'seat_label' => $student?->seat_label,
                    ];
                });

                return [
                    'id' => $group->id,
                    'project_id' => $group->project_id,
                    'group_number' => $group->group_number,
                    'name' => $group->name,
                    'topic' => $group->topic,
                    'score' => $group->score,
                    'notes' => $group->notes,
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

        return Inertia::render('projects/Show', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title'),
            'project' => [
                ...$project->toArray(),
                'groups' => $groups,
            ],
            'totalStudentsCount' => $students->count(),
            'unassignedStudents' => $unassignedStudents,
        ]);
    }

    public function update(UpdateProjectRequest $request, Section $section, Project $project): RedirectResponse
    {
        $this->authorizeProject($section, $project);
        $data = $request->validated();

        unset($data['attachment']);
        if ($request->hasFile('attachment')) {
            if ($project->attachment_path) {
                Storage::disk('local')->delete($project->attachment_path);
            }
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store("projects/{$section->id}", 'local');
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_mime'] = $file->getMimeType();
        }

        $project->update($data);

        return back()->with('success', 'Project details updated.');
    }

    public function attachment(Request $request, Section $section, Project $project): StreamedResponse|BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeProject($section, $project);
        abort_unless($project->section_id === $section->id, 404);
        abort_unless($project->attachment_path && Storage::disk('local')->exists($project->attachment_path), 404);

        $name = $project->attachment_name ?? basename($project->attachment_path);
        $mime = $project->attachment_mime ?? Storage::disk('local')->mimeType($project->attachment_path) ?? 'application/octet-stream';

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($project->attachment_path, $name, ['Content-Type' => $mime]);
        }

        return Storage::disk('local')->response($project->attachment_path, $name, [
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
            'topic' => ['nullable', 'string', 'max:1000'],
        ]);

        $maxNumber = (int) ($project->groups()->max('group_number') ?? 0);
        $newNumber = $maxNumber + 1;

        $project->groups()->create([
            'group_number' => $newNumber,
            'name' => $data['name'] ?: "Group {$newNumber}",
            'topic' => $data['topic'] ?? null,
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
            'score' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
            'student_id' => ['required', 'exists:students,id'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        abort_unless((int) $student->section_id === (int) $section->id, 404);

        DB::transaction(function () use ($project, $group, $student, $data) {
            // Remove student from any other group in this project
            $otherGroupIds = $project->groups()->pluck('id');
            ProjectGroupMember::whereIn('project_group_id', $otherGroupIds)
                ->where('student_id', $student->id)
                ->delete();

            $group->members()->create([
                'student_id' => $student->id,
                'role' => $data['role'] ?? null,
            ]);
        });

        return back()->with('success', "Added {$student->first_name} to {$group->name}.");
    }

    public function updateMember(Request $request, Section $section, Project $project, ProjectGroup $group, Student $student): JsonResponse|RedirectResponse
    {
        $this->authorizeGroup($project, $group);
        $member = $group->members()->where('student_id', $student->id)->firstOrFail();

        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'role' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
}
