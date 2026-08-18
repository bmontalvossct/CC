<?php

use App\Http\Controllers\Settings\AcademicTermSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/academic-term', [AcademicTermSettingsController::class, 'edit'])->name('academic-term.edit');
    Route::put('settings/academic-term', [AcademicTermSettingsController::class, 'update'])->name('academic-term.update');
    Route::post('settings/academic-term/{term}/make-current', [AcademicTermSettingsController::class, 'makeCurrent'])->name('academic-term.make-current');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});
