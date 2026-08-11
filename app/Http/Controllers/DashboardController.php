<?php

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->user()->id;

        $sectionQuery = Section::query()
            ->where('user_id', $userId)
            ->whereNull('archived_at');

        $sectionIds = (clone $sectionQuery)->pluck('id');
        $studentCount = DB::table('students')
            ->whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->count();
        $meetingCount = DB::table('attendance_sessions')
            ->whereIn('section_id', $sectionIds)
            ->count();

        $attendanceTotals = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->whereIn('attendance_sessions.section_id', $sectionIds)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) as present', ['present'])
            ->first();

        $attendanceRate = $attendanceTotals && $attendanceTotals->total > 0
            ? round(($attendanceTotals->present / $attendanceTotals->total) * 100, 1)
            : null;

        $sections = (clone $sectionQuery)
            ->with(['academicTerm:id,name,school_year'])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function (Section $section): array {
                $studentTotal = DB::table('students')
                    ->where('section_id', $section->id)
                    ->where('is_active', true)
                    ->count();

                $seatTotal = DB::table('seats')
                    ->join('layout_blocks', 'layout_blocks.id', '=', 'seats.layout_block_id')
                    ->where('layout_blocks.section_id', $section->id)
                    ->where('seats.is_disabled', false)
                    ->count();

                $attendance = DB::table('attendance_records')
                    ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
                    ->where('attendance_sessions.section_id', $section->id)
                    ->selectRaw('COUNT(*) as total, SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) as present', ['present'])
                    ->first();

                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'subject' => trim($section->subject_code.' · '.$section->subject_title, ' ·'),
                    'term' => $section->academicTerm
                        ? $section->academicTerm->name.' · '.$section->academicTerm->school_year
                        : 'Term not set',
                    'students' => $studentTotal,
                    'seats' => $seatTotal,
                    'attendance_rate' => $attendance && $attendance->total > 0
                        ? round(($attendance->present / $attendance->total) * 100, 1)
                        : null,
                ];
            });

        return Inertia::render('Dashboard', [
            'teacherName' => $request->user()->name,
            'stats' => [
                'sections' => $sectionIds->count(),
                'students' => $studentCount,
                'meetings' => $meetingCount,
                'attendance_rate' => $attendanceRate,
            ],
            'sections' => $sections,
        ]);
    }
}
