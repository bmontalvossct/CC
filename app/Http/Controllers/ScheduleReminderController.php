<?php

namespace App\Http\Controllers;

use App\Services\ScheduleReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleReminderController extends Controller
{
    public function __construct(
        protected ScheduleReminderService $scheduleReminderService
    ) {}

    /**
     * Get today's scheduled classes and reminder statuses for the authenticated user.
     */
    public function todayReminders(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->scheduleReminderService->getTodayClassesForUser($user);

        return response()->json($data);
    }
}
