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

        $academicTermExists = DB::table('academic_terms')
            ->where('user_id', $userId)
            ->selectRaw('count(*)');

        $seatingLayoutExists = DB::table('seats')
            ->join('layout_blocks', 'layout_blocks.id', '=', 'seats.layout_block_id')
            ->join('sections', 'sections.id', '=', 'layout_blocks.section_id')
            ->where('sections.user_id', $userId)
            ->whereNull('sections.archived_at')
            ->selectRaw('count(*)');

        $stats = DB::query()
            ->selectSub((clone $sectionQuery)->selectRaw('count(*)'), 'sections')
            ->selectSub($activeStudents, 'students')
            ->selectSub($meetings, 'meetings')
            ->selectSub((clone $attendanceRecords)->selectRaw('count(*)'), 'attendance_total')
            ->selectSub((clone $attendanceRecords)->where('attendance_records.status', 'present')->selectRaw('count(*)'), 'attendance_present')
            ->selectSub($academicTermExists, 'academic_terms_count')
            ->selectSub($seatingLayoutExists, 'seats_count')
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

        $onboarding = [
            'has_academic_term' => (int) ($stats->academic_terms_count ?? 0) > 0,
            'has_section' => (int) ($stats->sections ?? 0) > 0,
            'has_seating_layout' => (int) ($stats->seats_count ?? 0) > 0,
            'has_students' => (int) ($stats->students ?? 0) > 0,
            'has_attendance' => (int) ($stats->meetings ?? 0) > 0,
            'first_section_id' => $sections->first()['id'] ?? null,
        ];

        $currentTerm = $request->user()->academicTerms()
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->first();

        return Inertia::render('Dashboard', [
            'teacherName' => $request->user()->name,
            'currentTerm' => $currentTerm ? [
                'id' => $currentTerm->id,
                'name' => $currentTerm->name,
                'school_year' => $currentTerm->school_year,
                'starts_on' => $currentTerm->starts_on instanceof \DateTimeInterface
                    ? $currentTerm->starts_on->format('Y-m-d')
                    : (string) $currentTerm->starts_on,
                'ends_on' => $currentTerm->ends_on instanceof \DateTimeInterface
                    ? $currentTerm->ends_on->format('Y-m-d')
                    : (string) $currentTerm->ends_on,
            ] : null,
            'stats' => [
                'sections' => (int) ($stats->sections ?? 0),
                'students' => (int) ($stats->students ?? 0),
                'meetings' => (int) ($stats->meetings ?? 0),
                'attendance_rate' => $attendanceRate,
            ],
            'sections' => $sections,
            'onboarding' => $onboarding,
        ]);
    }

    public function saveQuickSetup(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'term_name' => ['required', 'string', 'max:100'],
            'school_year' => ['required', 'string', 'max:20'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        // Update teacher profile name
        $user->update(['name' => $data['name']]);

        // Ensure current Academic Term is resolved and active
        $startsOn = $data['starts_on'] ?? now()->startOfMonth()->toDateString();
        $endsOn = $data['ends_on'] ?? now()->addMonths(5)->endOfMonth()->toDateString();

        $term = \App\Models\AcademicTerm::resolveForUser($user->id, [
            'name' => $data['term_name'],
            'school_year' => $data['school_year'],
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_current' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding setup saved successfully',
            'teacher_name' => $user->name,
            'term' => [
                'id' => $term->id,
                'name' => $term->name,
                'school_year' => $term->school_year,
                'starts_on' => $term->starts_on?->format('Y-m-d'),
                'ends_on' => $term->ends_on?->format('Y-m-d'),
            ],
        ]);
    }
}
