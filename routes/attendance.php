<?php

use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('sections/{section}/attendance', [AttendanceController::class, 'index'])
        ->name('attendance.sections.index');
    Route::post('sections/{section}/attendance', [AttendanceController::class, 'store'])
        ->name('attendance.sections.store');
    Route::get('attendance/{attendanceSession}', [AttendanceController::class, 'show'])
        ->name('attendance.sessions.show');
    Route::patch('attendance-records/{record}', [AttendanceRecordController::class, 'update'])
        ->name('attendance.records.update');
});
