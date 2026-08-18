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
        $periods = $this->periods($section, $referenceDate);
        $aggregates = $this->attendanceAggregates($section, $periods);

        $sessions = AttendanceSession::query()
            ->where('section_id', $section->id)
            ->with('records:id,attendance_session_id,student_id,status,attended_minutes')
            ->latest('session_date')
            ->latest('starts_at')
            ->limit(50)
            ->get();

        $students = $section->students()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'section_id', 'student_number', 'first_name', 'middle_name', 'last_name']);

        $studentHistoryMap = [];
        foreach ($students as $student) {
            $studentHistoryMap[$student->id] = [
                'absent_days' => [],
                'late_days' => [],
                'present_count' => 0,
                'total_sessions' => 0,
            ];
        }

        foreach ($sessions as $session) {
            $dateStr = $session->session_date->toDateString();
            $timeStr = substr($session->starts_at, 0, 5).' – '.substr($session->ends_at, 0, 5);
            $sessionInfo = [
                'session_id' => $session->id,
                'date' => $dateStr,
                'time' => $timeStr,
                'notes' => $session->notes,
                'duration_minutes' => $session->duration_minutes,
            ];

            foreach ($session->records as $rec) {
                if (isset($studentHistoryMap[$rec->student_id])) {
                    $studentHistoryMap[$rec->student_id]['total_sessions']++;
                    if ($rec->status === AttendanceRecord::STATUS_ABSENT) {
                        $studentHistoryMap[$rec->student_id]['absent_days'][] = $sessionInfo;
                    } elseif ($rec->status === AttendanceRecord::STATUS_LATE) {
                        $studentHistoryMap[$rec->student_id]['late_days'][] = $sessionInfo;
                    } elseif ($rec->status === AttendanceRecord::STATUS_PRESENT) {
                        $studentHistoryMap[$rec->student_id]['present_count']++;
                    }
                }
            }
        }

        return Inertia::render('attendance/Index', [
            'section' => $this->sectionData($section),
            'referenceDate' => $referenceDate->toDateString(),
            'students' => $students->map(fn ($s) => [
                'id' => $s->id,
                'student_number' => $s->student_number,
                'name' => trim("{$s->last_name}, {$s->first_name} {$s->middle_name}"),
            ]),
            'periodSummaries' => collect(array_keys($periods))->mapWithKeys(fn ($period) => [
                $period => $this->formatSummary(
                    $aggregates->sum($period.'_sessions'),
                    $aggregates->sum($period.'_present'),
                    $aggregates->sum($period.'_minutes'),
                ),
            ]),
            'studentSummaries' => $students->map(function ($student) use ($aggregates, $studentHistoryMap) {
                $summary = $aggregates->get($student->id);
                $history = $studentHistoryMap[$student->id] ?? [
                    'absent_days' => [],
                    'late_days' => [],
                    'present_count' => 0,
                    'total_sessions' => 0,
                ];

                $absentCount = count($history['absent_days']);
                $lateCount = count($history['late_days']);
                $presentCount = $history['present_count'];
                $totalSessions = $history['total_sessions'];
                $earnedPoints = round(($presentCount * 1.0) + ($lateCount * 0.5), 1);
                $possiblePoints = (float) $totalSessions;
                $gradeRate = $totalSessions > 0 ? round(($earnedPoints / $totalSessions) * 100, 1) : null;
                $absencesRemaining = max(0, 3 - $absentCount);

                $absenceStatus = match (true) {
                    $absentCount > 3 => 'exceeded',
                    $absentCount === 3 => 'limit_reached',
                    $absentCount === 2 => 'warning',
                    default => 'good',
                };

                return [
                    'id' => $student->id,
                    'student_number' => $student->student_number,
                    'name' => trim("{$student->last_name}, {$student->first_name} {$student->middle_name}"),
                    'week' => $this->summaryFromAggregate($summary, 'week'),
                    'month' => $this->summaryFromAggregate($summary, 'month'),
                    'term' => $this->summaryFromAggregate($summary, 'term'),
                    'overall' => $this->summaryFromAggregate($summary, 'overall'),
                    'absent_days' => $history['absent_days'],
                    'late_days' => $history['late_days'],
                    'absent_count' => $absentCount,
                    'late_count' => $lateCount,
                    'present_count' => $presentCount,
                    'total_sessions' => $totalSessions,
                    'earned_points' => $earnedPoints,
                    'possible_points' => $possiblePoints,
                    'grade_rate' => $gradeRate,
                    'absences_allowed' => 3,
                    'absences_remaining' => $absencesRemaining,
                    'absence_status' => $absenceStatus,
                ];
            })->values(),
            'sessions' => $sessions->map(fn ($session) => [
                'id' => $session->id,
                'session_date' => $session->session_date->toDateString(),
                'starts_at' => substr($session->starts_at, 0, 5),
                'ends_at' => substr($session->ends_at, 0, 5),
                'duration_minutes' => $session->duration_minutes,
                'notes' => $session->notes,
                'records_count' => $session->records->count(),
                'present_count' => $session->records->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
                'late_count' => $session->records->where('status', AttendanceRecord::STATUS_LATE)->count(),
                'absent_count' => $session->records->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
                'records' => $session->records->map(fn ($r) => [
                    'student_id' => $r->student_id,
                    'status' => $r->status,
                    'attended_minutes' => $r->attended_minutes,
                ]),
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
            ->with(['layoutBlock:id,section_id,label,block_row,block_column,internal_rows,internal_columns,aisle_after_rows,aisle_after_columns', 'student'])
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
                        'internal_rows' => $seat->layoutBlock->internal_rows,
                        'internal_columns' => $seat->layoutBlock->internal_columns,
                        'aisle_after_rows' => $seat->layoutBlock->aisle_after_rows ?? [],
                        'aisle_after_columns' => $seat->layoutBlock->aisle_after_columns ?? [],
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

    public function destroy(Request $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        $section = $attendanceSession->section;
        $this->authorizeSection($request, $section);

        $dateFormatted = $attendanceSession->session_date->format('M d, Y');
        $attendanceSession->delete();

        return to_route('attendance.sections.index', $section)
            ->with('success', "Attendance roll call for {$dateFormatted} has been deleted.");
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

    private function attendanceAggregates(Section $section, array $periods): Collection
    {
        $query = DB::table('attendance_records as records')
            ->join('attendance_sessions as sessions', 'sessions.id', '=', 'records.attendance_session_id')
            ->where('sessions.section_id', $section->id)
            ->select('records.student_id')
            ->selectRaw('COUNT(*) as overall_sessions')
            ->selectRaw('SUM(CASE WHEN records.status = ? THEN 1 ELSE 0 END) as overall_present', [AttendanceRecord::STATUS_PRESENT])
            ->selectRaw('COALESCE(SUM(records.attended_minutes), 0) as overall_minutes');

        foreach ($periods as $period => [$from, $to]) {
            $fromDate = $from->toDateString();
            $toDate = $to->toDateString();
            $query->selectRaw(
                'SUM(CASE WHEN sessions.session_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as '.$period.'_sessions',
                [$fromDate, $toDate],
            );
            $query->selectRaw(
                'SUM(CASE WHEN sessions.session_date BETWEEN ? AND ? AND records.status = ? THEN 1 ELSE 0 END) as '.$period.'_present',
                [$fromDate, $toDate, AttendanceRecord::STATUS_PRESENT],
            );
            $query->selectRaw(
                'SUM(CASE WHEN sessions.session_date BETWEEN ? AND ? THEN records.attended_minutes ELSE 0 END) as '.$period.'_minutes',
                [$fromDate, $toDate],
            );
        }

        return $query->groupBy('records.student_id')->get()->keyBy('student_id');
    }

    private function summaryFromAggregate(?object $aggregate, string $period): array
    {
        return $this->formatSummary(
            (int) ($aggregate->{$period.'_sessions'} ?? 0),
            (int) ($aggregate->{$period.'_present'} ?? 0),
            (int) ($aggregate->{$period.'_minutes'} ?? 0),
        );
    }

    private function formatSummary(int $total, int $present, int $minutes): array
    {
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
