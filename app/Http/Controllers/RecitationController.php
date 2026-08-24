<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RecitationController extends Controller
{
    public function index(Section $section): Response
    {
        Gate::authorize('update', $section);

        // Fetch students in this section with their seat labels
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

        // Map full name
        $students = $students->map(function ($s) {
            $s->full_name = trim("{$s->last_name}, {$s->first_name} {$s->middle_name}");
            $s->photo_url = $s->photo_path ? asset('storage/'.$s->photo_path) : null;

            return $s;
        });

        // Get recitations for today
        $today = now()->toDateString();
        $todayRecitations = Recitation::query()
            ->where('section_id', $section->id)
            ->where('conducted_on', $today)
            ->get()
            ->keyBy('student_id');

        // Check today's attendance session for this section
        $todayAttendanceSession = AttendanceSession::query()
            ->where('section_id', $section->id)
            ->whereDate('session_date', $today)
            ->latest('starts_at')
            ->with('records')
            ->first();

        $attendanceMap = [];
        if ($todayAttendanceSession) {
            foreach ($todayAttendanceSession->records as $record) {
                $attendanceMap[$record->student_id] = $record->status;
            }
        }

        // Get all recitations for computing overall grades and displaying student logs
        $allRecitations = Recitation::query()
            ->where('section_id', $section->id)
            ->orderBy('conducted_on', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('student_id');

        $bonusCap = (float) ($section->grading_weights['recitation'] ?? 5);

        $absentCounts = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.section_id', $section->id)
            ->where('attendance_records.status', AttendanceRecord::STATUS_ABSENT)
            ->groupBy('attendance_records.student_id')
            ->select('attendance_records.student_id', DB::raw('COUNT(*) as count'))
            ->pluck('count', 'student_id')
            ->toArray();

        // Compute grades and summaries
        $rubricSummary = $students->map(function ($student) use ($allRecitations, $todayRecitations, $bonusCap, $attendanceMap, $absentCounts) {
            $studentRecs = $allRecitations->get($student->id, collect());
            $todayRec = $todayRecitations->get($student->id);

            $attendanceStatus = $attendanceMap[$student->id] ?? null;
            $isAbsent = $attendanceStatus === AttendanceRecord::STATUS_ABSENT;
            $isPresent = $attendanceStatus === AttendanceRecord::STATUS_PRESENT || $attendanceStatus === AttendanceRecord::STATUS_LATE;

            $count = $studentRecs->count();
            $avgAccuracy = $count ? round((float) $studentRecs->avg('accuracy'), 2) : null;
            $avgDelivery = $count ? round((float) $studentRecs->avg('delivery'), 2) : null;
            $avgScore = $count ? round((float) $studentRecs->avg('score'), 2) : null;
            $bonusPoints = $avgScore !== null && $bonusCap > 0 ? round(($avgScore / 10) * $bonusCap, 2) : 0.0;
            // Recitation percentage (out of 100%)
            $computedGrade = $avgScore !== null ? round(($avgScore / 10) * 100, 2) : null;

            return [
                'id' => (int) $student->id,
                'student_number' => $student->student_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => $student->full_name,
                'photo_url' => $student->photo_url,
                'seat_label' => $student->seat_label,
                'attendance_status' => $attendanceStatus,
                'absent_count' => (int) ($absentCounts[$student->id] ?? 0),
                'is_absent' => $isAbsent,
                'is_present' => $isPresent,
                'can_recite' => ! $isAbsent,
                'times_called' => $count,
                'avg_accuracy' => $avgAccuracy,
                'avg_delivery' => $avgDelivery,
                'avg_score' => $avgScore,
                'bonus_points' => $bonusPoints,
                'bonus_cap' => $bonusCap,
                'computed_grade' => $computedGrade,
                'recitations' => $studentRecs->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'conducted_on' => $r->conducted_on->toDateString(),
                    'conducted_on_formatted' => $r->conducted_on->format('M d, Y'),
                    'accuracy' => $r->accuracy !== null ? (int) $r->accuracy : null,
                    'delivery' => $r->delivery !== null ? (int) $r->delivery : null,
                    'score' => (float) $r->score,
                    'comments' => $r->comments,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->values(),
                'today_recitation' => $todayRec ? [
                    'id' => (int) $todayRec->id,
                    'accuracy' => $todayRec->accuracy !== null ? (int) $todayRec->accuracy : null,
                    'delivery' => $todayRec->delivery !== null ? (int) $todayRec->delivery : null,
                    'score' => (float) $todayRec->score,
                    'comments' => $todayRec->comments,
                ] : null,
                'called_today' => $todayRec !== null,
            ];
        })->values();

        // Load section with layout blocks
        $section->load(['layoutBlocks.seats' => function ($query) {
            $query->orderBy('row_number')->orderBy('column_number');
        }]);

        return Inertia::render('sections/OralParticipation', [
            'section' => $section->only('id', 'name', 'subject_code', 'subject_title', 'layoutBlocks'),
            'students' => $rubricSummary,
            'bonusCap' => $bonusCap,
            'todayDate' => now()->format('Y-m-d'),
            'todayFormatted' => now()->format('M d, Y'),
            'hasTodayAttendance' => $todayAttendanceSession !== null,
        ]);
    }

    public function storeScore(Request $request, Section $section, Student $student): RedirectResponse
    {
        Gate::authorize('update', $section);
        abort_unless($student->section_id === $section->id, 404);

        $data = $request->validate([
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:5'],
            'delivery' => ['nullable', 'integer', 'min:0', 'max:5'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'comments' => ['nullable', 'string', 'max:1000'],
            'conducted_on' => ['nullable', 'date'],
        ]);

        if (isset($data['score']) && $data['score'] !== null && $data['score'] !== '') {
            $score = min(10.0, max(0.0, round((float) $data['score'], 2)));
        } else {
            $score = (int) ($data['accuracy'] ?? 0) + (int) ($data['delivery'] ?? 0);
        }

        $conductedOn = ! empty($data['conducted_on'])
            ? Carbon::parse($data['conducted_on'])->toDateString()
            : now()->toDateString();

        // Ensure student is not absent in attendance on this date
        $isAbsentOnDate = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->where('status', AttendanceRecord::STATUS_ABSENT)
            ->whereHas('session', fn ($q) => $q->where('section_id', $section->id)->whereDate('session_date', $conductedOn))
            ->exists();

        if ($isAbsentOnDate) {
            return back()->withErrors([
                'score' => "{$student->full_name} is marked ABSENT in attendance on {$conductedOn} and is ineligible to receive oral participation points.",
            ]);
        }

        $recitation = Recitation::query()
            ->where('section_id', $section->id)
            ->where('student_id', $student->id)
            ->whereDate('conducted_on', $conductedOn)
            ->first();

        if ($recitation) {
            $recitation->update([
                'accuracy' => isset($data['accuracy']) ? (int) $data['accuracy'] : $recitation->accuracy,
                'delivery' => isset($data['delivery']) ? (int) $data['delivery'] : $recitation->delivery,
                'score' => $score,
                'comments' => $data['comments'] ?? null,
            ]);
        } else {
            Recitation::create([
                'section_id' => $section->id,
                'student_id' => $student->id,
                'conducted_on' => $conductedOn,
                'accuracy' => isset($data['accuracy']) ? (int) $data['accuracy'] : null,
                'delivery' => isset($data['delivery']) ? (int) $data['delivery'] : null,
                'score' => $score,
                'comments' => $data['comments'] ?? null,
            ]);
        }

        return back()->with('success', "Recitation score recorded for {$student->full_name}.");
    }

    public function updateScore(Request $request, Section $section, Recitation $recitation): RedirectResponse
    {
        Gate::authorize('update', $section);
        abort_unless($recitation->section_id === $section->id, 404);

        $data = $request->validate([
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:5'],
            'delivery' => ['nullable', 'integer', 'min:0', 'max:5'],
            'score' => ['required', 'numeric', 'min:0', 'max:10'],
            'comments' => ['nullable', 'string', 'max:1000'],
            'conducted_on' => ['nullable', 'date'],
        ]);

        $score = min(10.0, max(0.0, round((float) $data['score'], 2)));
        $conductedOn = ! empty($data['conducted_on'])
            ? Carbon::parse($data['conducted_on'])->toDateString()
            : $recitation->conducted_on->toDateString();

        $isAbsentOnDate = AttendanceRecord::query()
            ->where('student_id', $recitation->student_id)
            ->where('status', AttendanceRecord::STATUS_ABSENT)
            ->whereHas('session', fn ($q) => $q->where('section_id', $section->id)->whereDate('session_date', $conductedOn))
            ->exists();

        if ($isAbsentOnDate) {
            return back()->withErrors([
                'score' => "Student is marked ABSENT in attendance on {$conductedOn} and is ineligible to receive oral participation points.",
            ]);
        }

        $recitation->update([
            'accuracy' => isset($data['accuracy']) ? (int) $data['accuracy'] : $recitation->accuracy,
            'delivery' => isset($data['delivery']) ? (int) $data['delivery'] : $recitation->delivery,
            'score' => $score,
            'conducted_on' => $conductedOn,
            'comments' => $data['comments'] ?? null,
        ]);

        return back()->with('success', 'Recitation log updated.');
    }

    public function destroyScore(Section $section, Recitation $recitation): RedirectResponse
    {
        Gate::authorize('update', $section);
        abort_unless($recitation->section_id === $section->id, 404);

        $recitation->delete();

        return back()->with('success', 'Recitation log entry deleted.');
    }
}
