<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Section;
use App\Services\PhilippineHolidayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentTerm = $user->currentAcademicTerm();

        $monthParam = $request->input('month', now()->format('Y-m'));
        try {
            $currentMonth = Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth();
        } catch (\Exception) {
            $currentMonth = now()->startOfMonth();
        }

        $sectionFilterId = $request->integer('section_id') ?: null;

        // Calendar grid range from Monday to Sunday
        $gridStart = $currentMonth->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $currentMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        // Fetch user's active sections and their schedules
        $sectionsQuery = Section::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->with(['schedules', 'academicTerm'])
            ->withCount('students');

        if ($sectionFilterId) {
            $sectionsQuery->where('id', $sectionFilterId);
        }

        $sections = $sectionsQuery->get();

        $allUserSections = Section::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->select(['id', 'name', 'subject_code', 'subject_title'])
            ->orderBy('name')
            ->get();

        $sectionIds = $sections->pluck('id')->all();

        // Fetch attendance sessions with attendance counts in range
        $attendanceSessions = AttendanceSession::query()
            ->whereIn('section_id', $sectionIds)
            ->whereBetween('session_date', [$gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d')])
            ->with(['records'])
            ->get()
            ->groupBy(fn (AttendanceSession $session) => $session->section_id . '_' . $session->session_date->format('Y-m-d'));

        // Fetch Philippine Holidays for the range
        $holidays = PhilippineHolidayService::getHolidaysInRange($gridStart, $gridEnd);

        $todayStr = now()->format('Y-m-d');
        $monthStr = $currentMonth->format('Y-m');

        $calendarDays = [];
        $tempDate = $gridStart->copy();

        $totalScheduledMonth = 0;
        $totalConductedMonth = 0;
        $totalPresentMonth = 0;
        $todayClasses = [];

        while ($tempDate->lte($gridEnd)) {
            $dateStr = $tempDate->format('Y-m-d');
            $isoWeekday = $tempDate->dayOfWeekIso; // 1 (Mon) - 7 (Sun)
            $isCurrentMonth = $tempDate->format('Y-m') === $monthStr;
            $isToday = $dateStr === $todayStr;
            $isPast = $dateStr < $todayStr;
            $holiday = $holidays[$dateStr] ?? null;

            $classesOnDay = [];

            foreach ($sections as $section) {
                // Check if date falls within section term
                $termStart = $section->academicTerm?->starts_on?->format('Y-m-d') ?? '2000-01-01';
                $termEnd = $section->academicTerm?->ends_on?->format('Y-m-d') ?? '2099-12-31';

                if ($dateStr < $termStart || $dateStr > $termEnd) {
                    continue;
                }

                // Check section schedules for this weekday
                foreach ($section->schedules as $schedule) {
                    if ($schedule->day_of_week === $isoWeekday) {
                        $key = $section->id . '_' . $dateStr;
                        $session = $attendanceSessions->get($key)?->first();

                        // If the date is a Philippine holiday, do not include scheduled subjects unless a class was conducted
                        if ($holiday && ! $session) {
                            continue;
                        }

                        $presentCount = 0;
                        $lateCount = 0;
                        $excusedCount = 0;
                        $absentCount = 0;

                        if ($session) {
                            foreach ($session->records as $record) {
                                if ($record->status === 'present') $presentCount++;
                                elseif ($record->status === 'late') $lateCount++;
                                elseif ($record->status === 'excused') $excusedCount++;
                                elseif ($record->status === 'absent') $absentCount++;
                            }
                        }

                        $isConducted = $session !== null;

                        if ($isCurrentMonth) {
                            $totalScheduledMonth++;
                            if ($isConducted) {
                                $totalConductedMonth++;
                                $totalPresentMonth += ($presentCount + $lateCount);
                            }
                        }

                        $classItem = [
                            'section_id' => $section->id,
                            'section_name' => $section->name,
                            'subject_code' => $section->subject_code,
                            'subject_title' => $section->subject_title,
                            'room' => $schedule->room ?? $section->room,
                            'schedule_type' => $schedule->schedule_type ?? 'lecture',
                            'starts_at' => substr($schedule->starts_at, 0, 5),
                            'ends_at' => substr($schedule->ends_at, 0, 5),
                            'enrolled_count' => $section->students_count,
                            'is_conducted' => $isConducted,
                            'attendance_session_id' => $session?->id,
                            'present_count' => $presentCount,
                            'late_count' => $lateCount,
                            'excused_count' => $excusedCount,
                            'absent_count' => $absentCount,
                            'status' => $isConducted
                                ? 'conducted'
                                : ($isToday ? 'today_pending' : ($isPast ? 'no_record' : 'upcoming')),
                        ];

                        $classesOnDay[] = $classItem;

                        if ($isToday) {
                            $todayClasses[] = $classItem;
                        }
                    }
                }
            }

            // Sort classes by start time
            usort($classesOnDay, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));

            $calendarDays[] = [
                'date' => $dateStr,
                'day_number' => $tempDate->day,
                'day_name' => $tempDate->format('D'),
                'is_current_month' => $isCurrentMonth,
                'is_today' => $isToday,
                'is_past' => $isPast,
                'holiday' => $holiday,
                'classes' => $classesOnDay,
            ];

            $tempDate->addDay();
        }

        return Inertia::render('schedule/Index', [
            'month' => $monthStr,
            'monthLabel' => $currentMonth->format('F Y'),
            'prevMonth' => $currentMonth->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $currentMonth->copy()->addMonth()->format('Y-m'),
            'todayDate' => $todayStr,
            'selectedSectionId' => $sectionFilterId,
            'sections' => $allUserSections,
            'currentTerm' => [
                'id' => $currentTerm->id,
                'name' => $currentTerm->name,
                'school_year' => $currentTerm->school_year,
                'starts_on' => $currentTerm->starts_on->format('Y-m-d'),
                'ends_on' => $currentTerm->ends_on->format('Y-m-d'),
            ],
            'calendarDays' => $calendarDays,
            'todayClasses' => $todayClasses,
            'stats' => [
                'total_scheduled_month' => $totalScheduledMonth,
                'total_conducted_month' => $totalConductedMonth,
                'conducted_percentage' => $totalScheduledMonth > 0 ? round(($totalConductedMonth / $totalScheduledMonth) * 100) : 0,
                'total_present_month' => $totalPresentMonth,
                'today_classes_count' => count($todayClasses),
            ],
        ]);
    }
}
