<?php

use App\Http\Controllers\Assessments\AssessmentAttachmentController;
use App\Http\Controllers\Assessments\AssessmentController;
use App\Http\Controllers\Assessments\AssessmentExportController;
use App\Http\Controllers\Assessments\AssessmentReportController;
use App\Http\Controllers\Assessments\AssessmentScoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('sections/{section}/assessments', [AssessmentController::class, 'index'])->name('sections.assessments.index');
    Route::post('sections/{section}/assessments', [AssessmentController::class, 'store'])->name('sections.assessments.store');
    Route::get('sections/{section}/assessments/{assessment}', [AssessmentController::class, 'show'])->name('sections.assessments.show');
    Route::patch('sections/{section}/assessments/{assessment}', [AssessmentController::class, 'update'])->name('sections.assessments.update');
    Route::delete('sections/{section}/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('sections.assessments.destroy');
    Route::patch('sections/{section}/assessments/{assessment}/scores/{student}', [AssessmentScoreController::class, 'update'])->name('sections.assessments.scores.update');
    Route::get('sections/{section}/assessments/{assessment}/attachment', AssessmentAttachmentController::class)->name('sections.assessments.attachment');
    Route::get('sections/{section}/assessments/{assessment}/export', [AssessmentExportController::class, 'assessment'])->name('sections.exports.assessment');

    Route::get('sections/{section}/reports/gradebook', [AssessmentReportController::class, 'gradebook'])->name('sections.reports.gradebook');
    Route::get('sections/{section}/reports/gradebook/print', [AssessmentReportController::class, 'print'])->name('sections.reports.gradebook.print');
    Route::get('sections/{section}/exports/roster', [AssessmentExportController::class, 'roster'])->name('sections.exports.roster');
    Route::get('sections/{section}/exports/attendance', [AssessmentExportController::class, 'attendance'])->name('sections.exports.attendance');
    Route::get('sections/{section}/exports/gradebook', [AssessmentExportController::class, 'gradebook'])->name('sections.exports.gradebook');
});
