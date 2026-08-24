<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Models\AcademicTerm;
use App\Models\Seat;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Section::class);

        $userId = request()->user()->id;

        return Inertia::render('sections/Index', [
            'sections' => Section::query()
                ->where('user_id', $userId)
                ->whereNull('archived_at')
                ->with('academicTerm')
                ->withCount(['students', 'layoutBlocks'])
                ->latest()
                ->paginate(6)
                ->withQueryString(),
            'archivedCount' => Section::query()
                ->where('user_id', $userId)
                ->whereNotNull('archived_at')
                ->count(),
        ]);
    }

    public function archived(): Response
    {
        Gate::authorize('viewAny', Section::class);

        $userId = request()->user()->id;

        return Inertia::render('sections/Archived', [
            'sections' => Section::query()
                ->where('user_id', $userId)
                ->whereNotNull('archived_at')
                ->with('academicTerm')
                ->withCount(['students', 'layoutBlocks'])
                ->latest('archived_at')
                ->paginate(6)
                ->withQueryString(),
            'activeCount' => Section::query()
                ->where('user_id', $userId)
                ->whereNull('archived_at')
                ->count(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Section::class);

        $user = request()->user() ?? auth()->user();
        $currentTerm = $user?->currentAcademicTerm();

        $startsOn = null;
        if ($currentTerm?->starts_on) {
            $startsOn = $currentTerm->starts_on instanceof \DateTimeInterface
                ? $currentTerm->starts_on->format('Y-m-d')
                : (string) $currentTerm->starts_on;
        }

        $endsOn = null;
        if ($currentTerm?->ends_on) {
            $endsOn = $currentTerm->ends_on instanceof \DateTimeInterface
                ? $currentTerm->ends_on->format('Y-m-d')
                : (string) $currentTerm->ends_on;
        }

        return Inertia::render('sections/Create', [
            'currentTerm' => $currentTerm ? [
                'id' => $currentTerm->id,
                'name' => $currentTerm->name,
                'school_year' => $currentTerm->school_year,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'default_starts_at' => $currentTerm->default_starts_at ?? '08:00',
                'default_ends_at' => $currentTerm->default_ends_at ?? '09:30',
            ] : null,
        ]);
    }

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        if (! empty($data['term']['name']) && ! empty($data['term']['school_year'])) {
            $term = AcademicTerm::resolveForUser($userId, $data['term']);
        } else {
            $term = $request->user()->currentAcademicTerm();
        }

        $section = DB::transaction(function () use ($data, $term, $userId) {
            $primaryRoom = $data['room'] ?? ($data['schedules'][0]['room'] ?? null);
            $section = Section::create([
                ...collect($data)->only(['subject_code', 'subject_title', 'name'])->all(),
                'room' => $primaryRoom,
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
            'students' => fn ($query) => $query->where('is_active', true)->orderBy('last_name')->orderBy('first_name'),
            'students.seat',
        ]);

        $absentCounts = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.section_id', $section->id)
            ->where('attendance_records.status', 'absent')
            ->groupBy('attendance_records.student_id')
            ->select('attendance_records.student_id', DB::raw('COUNT(*) as count'))
            ->pluck('count', 'student_id')
            ->toArray();

        $section->students->each(function ($student) use ($absentCounts) {
            $student->setAttribute('photo_url', $student->photo_path ? route('sections.students.photo', [$student->section_id, $student]) : null);
            $student->setAttribute('absent_count', (int) ($absentCounts[$student->id] ?? 0));
        });
        $roster = $section->students->keyBy('id');
        $section->layoutBlocks->each(fn ($block) => $block->seats->each(function ($seat) use ($roster) {
            if ($seat->student_id && $roster->has($seat->student_id)) {
                $seat->setRelation('student', $roster->get($seat->student_id));
            }
        }));

        $joinUrl = route('join.show', $section->enrollment_token);

        $calledTodayIds = DB::table('recitations')
            ->where('section_id', $section->id)
            ->whereDate('conducted_on', now()->toDateString())
            ->pluck('student_id')
            ->toArray();

        $absentTodayIds = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.section_id', $section->id)
            ->whereDate('attendance_sessions.session_date', now()->toDateString())
            ->where('attendance_records.status', 'absent')
            ->pluck('attendance_records.student_id')
            ->toArray();

        $excludedStudentIds = array_values(array_unique(array_merge($calledTodayIds, $absentTodayIds)));

        return Inertia::render('sections/Show', [
            'section' => $section,
            'join_url' => $joinUrl,
            'called_today_ids' => $excludedStudentIds,
        ]);
    }

    public function edit(Request $request, Section $section): Response
    {
        Gate::authorize('update', $section);
        $section->load(['academicTerm', 'schedules']);
        $user = $request->user() ?? auth()->user() ?? $section->user;
        $currentTerm = $user?->currentAcademicTerm() ?? $section->academicTerm;

        $startsOn = null;
        if ($currentTerm?->starts_on) {
            $startsOn = $currentTerm->starts_on instanceof \DateTimeInterface
                ? $currentTerm->starts_on->format('Y-m-d')
                : (string) $currentTerm->starts_on;
        }

        $endsOn = null;
        if ($currentTerm?->ends_on) {
            $endsOn = $currentTerm->ends_on instanceof \DateTimeInterface
                ? $currentTerm->ends_on->format('Y-m-d')
                : (string) $currentTerm->ends_on;
        }

        return Inertia::render('sections/Edit', [
            'section' => $section,
            'currentTerm' => $currentTerm ? [
                'id' => $currentTerm->id,
                'name' => $currentTerm->name,
                'school_year' => $currentTerm->school_year,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'default_starts_at' => $currentTerm->default_starts_at ?? '08:00',
                'default_ends_at' => $currentTerm->default_ends_at ?? '09:30',
            ] : null,
        ]);
    }

    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        if (! empty($data['term']['name']) && ! empty($data['term']['school_year'])) {
            $term = AcademicTerm::resolveForUser($userId, $data['term']);
        } else {
            $term = $section->academicTerm ?? $request->user()->currentAcademicTerm();
        }

        DB::transaction(function () use ($section, $data, $term) {
            $primaryRoom = $data['room'] ?? ($data['schedules'][0]['room'] ?? null);
            $section->update([
                ...collect($data)->only(['subject_code', 'subject_title', 'name'])->all(),
                'room' => $primaryRoom,
                'academic_term_id' => $term->id,
            ]);
            $section->schedules()->delete();
            $section->schedules()->createMany($data['schedules'] ?? []);
        });

        return to_route('sections.show', $section)->with('success', 'Section updated.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        Gate::authorize('delete', $section);

        foreach ($section->students()->whereNotNull('photo_path')->get() as $student) {
            if ($student->photo_path && Storage::disk('local')->exists($student->photo_path)) {
                Storage::disk('local')->delete($student->photo_path);
            }
        }

        $section->delete();

        return back()->with('success', 'Section and all associated records permanently deleted.');
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

    public function autoAssign(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);
        $mode = $request->input('mode', 'alphabetical');

        $students = $section->students()
            ->where('is_active', true)
            ->when($mode === 'random', fn ($q) => $q->inRandomOrder(), fn ($q) => $q->orderBy('last_name')->orderBy('first_name'))
            ->get();

        // Get all enabled seats ordered by layout block position and seat coordinates
        $availableSeats = Seat::query()
            ->join('layout_blocks', 'layout_blocks.id', '=', 'seats.layout_block_id')
            ->where('layout_blocks.section_id', $section->id)
            ->where('seats.is_disabled', false)
            ->orderBy('layout_blocks.block_row')
            ->orderBy('layout_blocks.block_column')
            ->orderBy('seats.row_number')
            ->orderBy('seats.column_number')
            ->select('seats.*')
            ->get();

        $assignedCount = 0;
        DB::transaction(function () use ($section, $students, $availableSeats, &$assignedCount) {
            // Clear current seat assignments for this section first
            $section->seats()->update(['student_id' => null]);

            foreach ($students as $index => $student) {
                if (! isset($availableSeats[$index])) {
                    break;
                }
                $seat = $availableSeats[$index];
                $seat->update(['student_id' => $student->id]);
                $assignedCount++;
            }
        });

        $modeLabel = $mode === 'alphabetical' ? 'last name' : 'random shuffle';

        return back()->with('success', "{$assignedCount} students assigned to chairs by {$modeLabel}.");
    }

    public function resetSeats(Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);

        $section->seats()->update(['student_id' => null]);

        return back()->with('success', 'All chair assignments have been cleared.');
    }

    public function printRoster(Section $section): Response
    {
        Gate::authorize('view', $section);
        $section->load([
            'academicTerm',
            'students' => fn ($query) => $query->where('is_active', true)->orderBy('last_name')->orderBy('first_name'),
            'students.seat',
        ]);

        $section->students->each(function ($student) {
            $student->setAttribute('photo_url', $student->photo_path ? route('sections.students.photo', [$student->section_id, $student]) : null);
        });

        return Inertia::render('sections/PrintRoster', [
            'section' => $section,
        ]);
    }
}
