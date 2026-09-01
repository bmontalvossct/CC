<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::post('onboarding/quick-setup', [DashboardController::class, 'saveQuickSetup'])
    ->middleware(['auth', 'verified'])
    ->name('onboarding.quick-setup');

Route::get('schedule', [ScheduleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('schedule.index');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('ai-assistant/status', [\App\Http\Controllers\AiAssistantController::class, 'status'])->name('ai-assistant.status');
    Route::post('ai-assistant/warm', [\App\Http\Controllers\AiAssistantController::class, 'warm'])->name('ai-assistant.warm');
    Route::post('ai-assistant/pull', [\App\Http\Controllers\AiAssistantController::class, 'pull'])->name('ai-assistant.pull');
    Route::post('ai-assistant/chat/stream', [\App\Http\Controllers\AiAssistantController::class, 'stream'])->name('ai-assistant.chat.stream');
    Route::post('ai-assistant/actions/execute', [\App\Http\Controllers\AiAssistantController::class, 'executeAction'])->name('ai-assistant.actions.execute');
    Route::get('ai-assistant/suggestions', [\App\Http\Controllers\AiAssistantController::class, 'suggestions'])->name('ai-assistant.suggestions');
});

require __DIR__.'/classroom.php';
require __DIR__.'/attendance.php';
require __DIR__.'/assessments.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
