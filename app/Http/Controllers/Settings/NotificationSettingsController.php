<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ScheduleReminderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function __construct(
        protected ScheduleReminderService $scheduleReminderService
    ) {}

    /**
     * Show notification settings page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $remindersData = $this->scheduleReminderService->getTodayClassesForUser($user);

        return Inertia::render('settings/Notifications', [
            'todayReminders' => $remindersData,
        ]);
    }
}
