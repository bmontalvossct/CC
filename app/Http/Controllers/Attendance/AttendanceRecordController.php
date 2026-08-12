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
        $record->update([
            'status' => $status,
            'attended_minutes' => $status === AttendanceRecord::STATUS_PRESENT
                ? $record->session->duration_minutes
                : 0,
        ]);

        if ($request->header('X-Inertia')) {
            return back(303);
        }

        return response()->json([
            'record' => [
                'id' => $record->id,
                'status' => $record->status,
                'attended_minutes' => $record->attended_minutes,
                'updated_at' => $record->updated_at->toISOString(),
            ],
        ]);
    }
}
