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

    Route::redirect('settings/academic-terms', 'settings/academic-term');
    Route::get('settings/academic-term', [AcademicTermSettingsController::class, 'edit'])->name('academic-term.edit');
    Route::put('settings/academic-term', [AcademicTermSettingsController::class, 'update'])->name('academic-term.update');
    Route::post('settings/academic-term/{term}/make-current', [AcademicTermSettingsController::class, 'makeCurrent'])->name('academic-term.make-current');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');

    // Backup & Export
    Route::get('settings/backup', [\App\Http\Controllers\Settings\BackupExportController::class, 'index'])->name('backup.index');
    Route::get('settings/backup/export-json', [\App\Http\Controllers\Settings\BackupExportController::class, 'exportJson'])->name('backup.export-json');
    Route::get('settings/backup/download-sqlite', [\App\Http\Controllers\Settings\BackupExportController::class, 'downloadSqlite'])->name('backup.download-sqlite');
    Route::get('settings/backup/export-csv', [\App\Http\Controllers\Settings\BackupExportController::class, 'exportCsv'])->name('backup.export-csv');
    Route::post('settings/backup/create-local-snapshot', [\App\Http\Controllers\Settings\BackupExportController::class, 'createLocalSnapshot'])->name('backup.create-local-snapshot');
    Route::post('settings/backup/restore', [\App\Http\Controllers\Settings\BackupExportController::class, 'restore'])->name('backup.restore');
});
