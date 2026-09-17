<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/classroom.php';
require __DIR__.'/attendance.php';
require __DIR__.'/assessments.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
