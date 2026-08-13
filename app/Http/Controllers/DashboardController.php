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

        $activeStudents = DB::table('students')
            ->join('sections', 'sections.id', '=', 'students.section_id')
            ->where('sections.user_id', $userId)
            ->whereNull('sections.archived_at')
            ->where('students.is_active', true)
            ->selectRaw('count(*)');

        $meetings = DB::table('attendance_sessions')
            ->join('sections', 'sections.id', '=', 'attendance_sessions.section_id')
            ->where('sections.user_id', $userId)
            ->whereNull('sections.archived_at')
            ->selectRaw('count(*)');

        $attendanceRecords = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->join('sections', 'sections.id', '=', 'attendance_sessions.section_id')
            ->where('sections.user_id', $userId)
            ->whereNull('sections.archived_at');

        $stats = DB::query()
            ->selectSub((clone $sectionQuery)->selectRaw('count(*)'), 'sections')
            ->selectSub($activeStudents, 'students')
            ->selectSub($meetings, 'meetings')
            ->selectSub((clone $attendanceRecords)->selectRaw('count(*)'), 'attendance_total')
            ->selectSub((clone $attendanceRecords)->where('attendance_records.status', 'present')->selectRaw('count(*)'), 'attendance_present')
            ->first();

        $attendanceRate = $stats && $stats->attendance_total > 0
            ? round(($stats->attendance_present / $stats->attendance_total) * 100, 1)
            : null;

        $sections = (clone $sectionQuery)
            ->with(['academicTerm:id,name,school_year'])
            ->withCount([
                'students as active_students_count' => fn ($query) => $query->where('is_active', true),
                'seats as available_seats_count' => fn ($query) => $query->where('is_disabled', false),
                'attendanceRecords',
                'attendanceRecords as present_attendance_records_count' => fn ($query) => $query->where('status', 'present'),
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function (Section $section): array {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'subject' => trim($section->subject_code.' · '.$section->subject_title, ' ·'),
                    'term' => $section->academicTerm
                        ? $section->academicTerm->name.' · '.$section->academicTerm->school_year
                        : 'Term not set',
                    'students' => $section->active_students_count,
                    'seats' => $section->available_seats_count,
                    'attendance_rate' => $section->attendance_records_count > 0
                        ? round(($section->present_attendance_records_count / $section->attendance_records_count) * 100, 1)
                        : null,
                ];
            });

        return Inertia::render('Dashboard', [
            'teacherName' => $request->user()->name,
            'stats' => [
                'sections' => (int) ($stats->sections ?? 0),
                'students' => (int) ($stats->students ?? 0),
                'meetings' => (int) ($stats->meetings ?? 0),
                'attendance_rate' => $attendanceRate,
            ],
            'sections' => $sections,
        ]);
    }
}
