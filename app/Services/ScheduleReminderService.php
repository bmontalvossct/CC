<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

class ScheduleReminderService
{
    /**
     * Get all scheduled classes for a user for today with reminder status.
     *
     * @return array{
     *     today_date: string,
     *     day_name: string,
     *     current_time: string,
     *     is_holiday: bool,
     *     holiday_name: ?string,
     *     term_name: ?string,
     *     classes: list<array{
     *         section_id: int,
     *         section_name: string,
     *         subject_code: string,
     *         subject_title: string,
     *         room: string,
     *         schedule_type: string,
     *         starts_at: string,
     *         ends_at: string,
     *         starts_at_formatted: string,
     *         ends_at_formatted: string,
     *         minutes_until_start: int,
     *         is_conducted: bool,
     *         attendance_session_id: ?int,
     *         attendance_url: string,
     *         status: 'conducted' | 'starting_soon' | 'in_progress' | 'upcoming' | 'completed'
     *     }>
     * }
     */
    public function getTodayClassesForUser(User $user, ?Carbon $now = null): array
    {
        $now = $now ? $now->copy() : now();
        $todayStr = $now->format('Y-m-d');
        $currentTimeStr = $now->format('H:i');
        $isoWeekday = $now->dayOfWeekIso; // 1 (Mon) - 7 (Sun)

        $currentTerm = $user->currentAcademicTerm();
        $termStart = $currentTerm->starts_on?->format('Y-m-d') ?? '2000-01-01';
        $termEnd = $currentTerm->ends_on?->format('Y-m-d') ?? '2099-12-31';

        // Check if today falls within active academic term
        $isWithinTerm = ($todayStr >= $termStart && $todayStr <= $termEnd);

        // Fetch Philippine Holidays for today
        $holidays = PhilippineHolidayService::getHolidaysInRange($now->copy()->startOfDay(), $now->copy()->endOfDay());
        $holiday = $holidays[$todayStr] ?? null;

        if (! $isWithinTerm) {
            return [
                'today_date' => $todayStr,
                'day_name' => $now->format('l'),
                'current_time' => $currentTimeStr,
                'is_holiday' => (bool) $holiday,
                'holiday_name' => $holiday['name'] ?? null,
                'term_name' => $currentTerm->name ?? null,
                'classes' => [],
            ];
        }

        // Fetch active sections and schedules matching today's weekday
        $sections = Section::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->with(['schedules' => function ($q) use ($isoWeekday) {
                $q->where('day_of_week', $isoWeekday);
            }, 'academicTerm'])
            ->get();

        $sectionIds = $sections->pluck('id')->all();

        // Check if any attendance sessions were already conducted today
        $attendanceSessions = AttendanceSession::query()
            ->whereIn('section_id', $sectionIds)
            ->whereDate('session_date', $todayStr)
            ->get()
            ->groupBy('section_id');

        $classes = [];

        foreach ($sections as $section) {
            // Respect section-specific term if present
            $secTermStart = $section->academicTerm?->starts_on?->format('Y-m-d') ?? $termStart;
            $secTermEnd = $section->academicTerm?->ends_on?->format('Y-m-d') ?? $termEnd;

            if ($todayStr < $secTermStart || $todayStr > $secTermEnd) {
                continue;
            }

            foreach ($section->schedules as $schedule) {
                $session = $attendanceSessions->get($section->id)?->first();

                // If holiday and no conducted session, skip
                if ($holiday && ! $session) {
                    continue;
                }

                $startsAtShort = substr($schedule->starts_at, 0, 5);
                $endsAtShort = substr($schedule->ends_at, 0, 5);

                $startTime = Carbon::createFromFormat('Y-m-d H:i', "{$todayStr} {$startsAtShort}", $now->getTimezone());
                $endTime = Carbon::createFromFormat('Y-m-d H:i', "{$todayStr} {$endsAtShort}", $now->getTimezone());

                $minutesUntilStart = (int) round($now->diffInMinutes($startTime, false));
                $isConducted = $session !== null;

                if ($isConducted) {
                    $status = 'conducted';
                } elseif ($now->gt($endTime)) {
                    $status = 'completed';
                } elseif ($now->gte($startTime) && $now->lte($endTime)) {
                    $status = 'in_progress';
                } elseif ($minutesUntilStart <= 20 && $minutesUntilStart >= 0) {
                    $status = 'starting_soon';
                } else {
                    $status = 'upcoming';
                }

                $classes[] = [
                    'section_id' => $section->id,
                    'section_name' => $section->name,
                    'subject_code' => $section->subject_code ?? '',
                    'subject_title' => $section->subject_title ?? '',
                    'room' => $schedule->room ?: ($section->room ?: 'TBA'),
                    'schedule_type' => $schedule->schedule_type ?: 'lecture',
                    'starts_at' => $startsAtShort,
                    'ends_at' => $endsAtShort,
                    'starts_at_formatted' => $startTime->format('g:i A'),
                    'ends_at_formatted' => $endTime->format('g:i A'),
                    'minutes_until_start' => $minutesUntilStart,
                    'is_conducted' => $isConducted,
                    'attendance_session_id' => $session?->id,
                    'attendance_url' => route('attendance.sections.index', $section->id),
                    'status' => $status,
                ];
            }
        }

        // Sort chronologically by starts_at
        usort($classes, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));

        return [
            'today_date' => $todayStr,
            'day_name' => $now->format('l'),
            'current_time' => $currentTimeStr,
            'is_holiday' => (bool) $holiday,
            'holiday_name' => $holiday['name'] ?? null,
            'term_name' => $currentTerm->name ?? null,
            'classes' => $classes,
        ];
    }
}
