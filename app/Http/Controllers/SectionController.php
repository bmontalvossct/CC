<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Models\AcademicTerm;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Section::class);

        return Inertia::render('sections/Index', [
            'sections' => Section::query()
                ->where('user_id', request()->user()->id)
                ->with('academicTerm')
                ->withCount(['students', 'layoutBlocks'])
                ->orderByRaw('archived_at is not null')
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Section::class);

        return Inertia::render('sections/Create');
    }

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;
        $term = AcademicTerm::resolveForUser($userId, $data['term']);

        $section = DB::transaction(function () use ($data, $term, $userId) {
            $section = Section::create([
                ...collect($data)->only(['subject_code', 'subject_title', 'name', 'room'])->all(),
                'user_id' => $userId,
                'academic_term_id' => $term->id,
            ]);
            $section->schedules()->createMany($data['schedules'] ?? []);

            return $section;
        });

        return to_route('sections.show', $section)->with('success', 'Section created. Build its classroom next.');
    }

    public function show(Section $section): Response
    {
        Gate::authorize('view', $section);
        $section->load([
            'academicTerm', 'schedules',
            'layoutBlocks' => fn ($query) => $query->orderBy('block_row')->orderBy('block_column'),
            'layoutBlocks.seats' => fn ($query) => $query->orderBy('row_number')->orderBy('column_number'),
            'layoutBlocks.seats.student',
            'students' => fn ($query) => $query->where('is_active', true)->orderBy('last_name')->orderBy('first_name'),
            'students.seat',
        ]);

        $section->students->each(function ($student) {
            $student->setAttribute('photo_url', $student->photo_path ? route('sections.students.photo', [$student->section_id, $student]) : null);
        });
        $roster = $section->students->keyBy('id');
        $section->layoutBlocks->each(fn ($block) => $block->seats->each(function ($seat) use ($roster) {
            if ($seat->student_id && $roster->has($seat->student_id)) {
                $seat->setRelation('student', $roster->get($seat->student_id));
            }
        }));

        $joinUrl = route('join.show', $section->enrollment_token);

        return Inertia::render('sections/Show', [
            'section' => $section,
            'join_url' => $joinUrl,
        ]);
    }

    public function edit(Section $section): Response
    {
        Gate::authorize('update', $section);

        return Inertia::render('sections/Edit', ['section' => $section->load('academicTerm', 'schedules')]);
    }

    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        $data = $request->validated();
        $term = AcademicTerm::resolveForUser($request->user()->id, $data['term']);

        DB::transaction(function () use ($data, $term, $section) {
            $section->update([
                ...collect($data)->only(['subject_code', 'subject_title', 'name', 'room'])->all(),
                'academic_term_id' => $term->id,
            ]);
            $section->schedules()->delete();
            $section->schedules()->createMany($data['schedules'] ?? []);
        });

        return to_route('sections.show', $section)->with('success', 'Section details updated.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        Gate::authorize('delete', $section);
        $section->delete();

        return to_route('sections.index')->with('success', 'Section deleted.');
    }

    public function enrollment(Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);
        $section->update(['enrollment_open' => ! $section->enrollment_open]);

        return back()->with('success', $section->enrollment_open ? 'Student enrollment opened.' : 'Student enrollment closed.');
    }

    public function regenerateToken(Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);
        $section->update(['enrollment_token' => Str::random(48)]);

        return back()->with('success', 'A new enrollment QR link is ready.');
    }

    public function archive(Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);
        $section->update(['archived_at' => $section->archived_at ? null : now()]);

        return back()->with('success', $section->archived_at ? 'Section archived.' : 'Section restored.');
    }
}
