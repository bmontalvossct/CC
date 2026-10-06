<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\UpdateAttendanceRecordRequest;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AttendanceRecordController extends Controller
{
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $record): JsonResponse|RedirectResponse
    {
        $status = $request->validated('status');
        $isExcused = $status === AttendanceRecord::STATUS_EXCUSED;
        $pointsAwarded = $isExcused ? (bool) $request->input('points_awarded', false) : false;
        $excuseReason = $isExcused ? $request->input('excuse_reason') : null;

        $attendedMinutes = 0;
        if (in_array($status, [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_LATE])) {
            $attendedMinutes = $record->session->duration_minutes;
        } elseif ($isExcused && $pointsAwarded) {
            $attendedMinutes = $record->session->duration_minutes;
        }

        $record->update([
            'status' => $status,
            'attended_minutes' => $attendedMinutes,
            'excuse_reason' => $excuseReason,
            'points_awarded' => $pointsAwarded,
        ]);

        if ($request->boolean('clear_absences')) {
            $unclearedRecords = AttendanceRecord::query()
                ->where('student_id', $record->student_id)
                ->where('status', AttendanceRecord::STATUS_ABSENT)
                ->where(function ($q) {
                    $q->whereNull('cleared_by_letter')->orWhere('cleared_by_letter', false);
                })
                ->whereHas('session', fn ($q) => $q->where('section_id', $record->session->section_id))
                ->where('id', '!=', $record->id)
                ->orderByDesc('created_at')
                ->limit(3)
                ->pluck('id');

            if ($unclearedRecords->isNotEmpty()) {
                AttendanceRecord::whereIn('id', $unclearedRecords)->update([
                    'cleared_by_letter' => true,
                    'cleared_at' => now(),
                ]);
            }
        }

        $unclearedCount = AttendanceRecord::query()
            ->where('student_id', $record->student_id)
            ->where('status', AttendanceRecord::STATUS_ABSENT)
            ->where(function ($q) {
                $q->whereNull('cleared_by_letter')->orWhere('cleared_by_letter', false);
            })
            ->whereHas('session', fn ($q) => $q->where('section_id', $record->session->section_id))
            ->count();

        if ($request->header('X-Inertia')) {
            return back(303);
        }

        return response()->json([
            'record' => [
                'id' => $record->id,
                'status' => $record->status,
                'attended_minutes' => $record->attended_minutes,
                'excuse_reason' => $record->excuse_reason,
                'points_awarded' => (bool) $record->points_awarded,
                'cleared_by_letter' => (bool) $record->cleared_by_letter,
                'updated_at' => $record->updated_at->toISOString(),
            ],
            'student_uncleared_absent_count' => $unclearedCount,
        ]);
    }
}
