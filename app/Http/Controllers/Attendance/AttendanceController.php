<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceSessionRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Seat;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request, Section $section): Response
    {
        $this->authorizeSection($request, $section);
        $section->load(['academicTerm', 'schedules']);

        $referenceDate = $request->date('reference_date') ?? today();
        $records = AttendanceRecord::query()
            ->whereHas('session', fn ($query) => $query->where('section_id', $section->id))
            ->with([
                'session:id,section_id,session_date,duration_minutes',
                'student:id,section_id,student_number,first_name,middle_name,last_name',
            ])
            ->get();

        $periods = $this->periods($section, $referenceDate);

        return Inertia::render('attendance/Index', [
            'section' => $this->sectionData($section),
            'referenceDate' => $referenceDate->toDateString(),
            'periodSummaries' => collect($periods)->mapWithKeys(fn ($range, $key) => [
                $key => $this->summaryFor($records, $range[0], $range[1]),
            ]),
            'studentSummaries' => $section->students()->orderBy('last_name')->orderBy('first_name')->get()
                ->map(function ($student) use ($records, $periods) {
                    $studentRecords = $records->where('student_id', $student->id);

                    return [
                        'id' => $student->id,
                        'student_number' => $student->student_number,
                        'name' => trim("{$student->last_name}, {$student->first_name} {$student->middle_name}"),
                        'week' => $this->summaryFor($studentRecords, ...$periods['week']),
                        'month' => $this->summaryFor($studentRecords, ...$periods['month']),
                        'term' => $this->summaryFor($studentRecords, ...$periods['term']),
                        'overall' => $this->summaryFor($studentRecords),
                    ];
                })->values(),
            'sessions' => AttendanceSession::query()
                ->where('section_id', $section->id)
                ->withCount([
                    'records',
                    'records as present_count' => fn ($query) => $query->where('status', AttendanceRecord::STATUS_PRESENT),
                ])
                ->latest('session_date')
                ->latest('starts_at')
                ->limit(50)
                ->get()
                ->map(fn ($session) => [
                    'id' => $session->id,
                    'session_date' => $session->session_date->toDateString(),
                    'starts_at' => substr($session->starts_at, 0, 5),
                    'ends_at' => substr($session->ends_at, 0, 5),
                    'duration_minutes' => $session->duration_minutes,
                    'notes' => $session->notes,
                    'records_count' => $session->records_count,
                    'present_count' => $session->present_count,
                ]),
        ]);
    }

    public function store(StoreAttendanceSessionRequest $request, Section $section): RedirectResponse
    {
        $validated = $request->validated();
        $duration = Carbon::createFromFormat('H:i', $validated['starts_at'])
            ->diffInMinutes(Carbon::createFromFormat('H:i', $validated['ends_at']));

        $session = DB::transaction(function () use ($section, $validated, $duration) {
            $session = AttendanceSession::create([
                'section_id' => $section->id,
                ...$validated,
                'duration_minutes' => $duration,
            ]);

            $now = now();
            $records = $section->students()->where('is_active', true)->get(['id'])->map(fn ($student) => [
                'attendance_session_id' => $session->id,
                'student_id' => $student->id,
                'status' => AttendanceRecord::STATUS_PRESENT,
                'attended_minutes' => $duration,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($records !== []) {
                AttendanceRecord::insert($records);
            }

            return $session;
        });

        return to_route('attendance.sessions.show', $session)
            ->with('success', 'Attendance session started. Everyone is marked present.');
    }

    public function show(Request $request, AttendanceSession $attendanceSession): Response
    {
        $attendanceSession->load(['section.academicTerm', 'records.student']);
        $section = $attendanceSession->section;
        $this->authorizeSection($request, $section);

        $recordsByStudent = $attendanceSession->records->keyBy('student_id');
        $seats = Seat::query()
            ->whereHas('layoutBlock', fn ($query) => $query->where('section_id', $section->id))
            ->with(['layoutBlock:id,section_id,label,block_row,block_column', 'student'])
            ->get()
            ->sortBy([
                ['layoutBlock.block_row', 'asc'],
                ['layoutBlock.block_column', 'asc'],
                ['row_number', 'asc'],
                ['column_number', 'asc'],
            ])
            ->map(function ($seat) use ($recordsByStudent) {
                $record = $seat->student_id ? $recordsByStudent->get($seat->student_id) : null;

                return [
                    'id' => $seat->id,
                    'label' => $seat->label,
                    'row_number' => $seat->row_number,
                    'column_number' => $seat->column_number,
                    'is_disabled' => $seat->is_disabled,
                    'block' => [
                        'id' => $seat->layoutBlock->id,
                        'label' => $seat->layoutBlock->label,
                        'row' => $seat->layoutBlock->block_row,
                        'column' => $seat->layoutBlock->block_column,
                    ],
                    'student' => $seat->student ? $this->studentData($seat->student) : null,
                    'record' => $record ? $this->recordData($record) : null,
                ];
            })->values();

        $seatedStudentIds = $seats->pluck('student.id')->filter();
        $unseated = $attendanceSession->records
            ->whereNotIn('student_id', $seatedStudentIds)
            ->map(fn ($record) => [
                'student' => $this->studentData($record->student),
                'record' => $this->recordData($record),
            ])->values();

        return Inertia::render('attendance/Show', [
            'section' => $this->sectionData($section),
            'session' => [
                'id' => $attendanceSession->id,
                'session_date' => $attendanceSession->session_date->toDateString(),
                'starts_at' => substr($attendanceSession->starts_at, 0, 5),
                'ends_at' => substr($attendanceSession->ends_at, 0, 5),
                'duration_minutes' => $attendanceSession->duration_minutes,
                'notes' => $attendanceSession->notes,
                'present_count' => $attendanceSession->records->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
                'total_count' => $attendanceSession->records->count(),
            ],
            'seats' => $seats,
            'unseated' => $unseated,
        ]);
    }

    private function authorizeSection(Request $request, Section $section): void
    {
        abort_unless((int) $section->user_id === (int) $request->user()?->id, 403);
    }

    private function periods(Section $section, Carbon $referenceDate): array
    {
        return [
            'week' => [$referenceDate->copy()->startOfWeek(Carbon::MONDAY), $referenceDate->copy()->endOfWeek(Carbon::SUNDAY)],
            'month' => [$referenceDate->copy()->startOfMonth(), $referenceDate->copy()->endOfMonth()],
            'term' => [Carbon::parse($section->academicTerm->starts_on), Carbon::parse($section->academicTerm->ends_on)],
        ];
    }

    private function summaryFor(Collection $records, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($from && $to) {
            $records = $records->filter(fn ($record) => $record->session->session_date->betweenIncluded($from, $to));
        }

        $present = $records->where('status', AttendanceRecord::STATUS_PRESENT)->count();
        $minutes = $records->sum('attended_minutes');
        $total = $records->count();

        return [
            'sessions' => $total,
            'present' => $present,
            'absent' => $total - $present,
            'rate' => $total > 0 ? round(($present / $total) * 100, 1) : null,
            'attended_minutes' => $minutes,
            'attended_hours' => round($minutes / 60, 2),
        ];
    }

    private function sectionData(Section $section): array
    {
        return [
            'id' => $section->id,
            'subject_code' => $section->subject_code,
            'subject_title' => $section->subject_title,
            'name' => $section->name,
            'term' => $section->relationLoaded('academicTerm') ? [
                'name' => $section->academicTerm->name,
                'school_year' => $section->academicTerm->school_year,
                'starts_on' => $section->academicTerm->starts_on->toDateString(),
                'ends_on' => $section->academicTerm->ends_on->toDateString(),
            ] : null,
            'default_schedule' => $section->relationLoaded('schedules')
                ? optional($section->schedules->firstWhere('day_of_week', now()->dayOfWeekIso))?->only(['starts_at', 'ends_at'])
                : null,
        ];
    }

    private function studentData($student): array
    {
        return [
            'id' => $student->id,
            'student_number' => $student->student_number,
            'name' => trim("{$student->last_name}, {$student->first_name} {$student->middle_name}"),
            'photo_url' => $student->photo_path && Route::has('sections.students.photo')
                ? route('sections.students.photo', [$student->section_id, $student])
                : null,
        ];
    }

    private function recordData(AttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'status' => $record->status,
            'attended_minutes' => $record->attended_minutes,
        ];
    }
}
