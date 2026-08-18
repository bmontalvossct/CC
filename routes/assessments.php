<?php

use App\Http\Controllers\Assessments\AssessmentAttachmentController;
use App\Http\Controllers\Assessments\AssessmentController;
use App\Http\Controllers\Assessments\AssessmentExportController;
use App\Http\Controllers\Assessments\AssessmentReportController;
use App\Http\Controllers\Assessments\AssessmentScoreController;
use App\Http\Controllers\Assessments\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('sections/{section}/assessments', [AssessmentController::class, 'index'])->name('sections.assessments.index');
    Route::post('sections/{section}/assessments', [AssessmentController::class, 'store'])->name('sections.assessments.store');
    Route::get('sections/{section}/assessments/{assessment}', [AssessmentController::class, 'show'])->name('sections.assessments.show');
    Route::match(['put', 'patch'], 'sections/{section}/assessments/{assessment}', [AssessmentController::class, 'update'])->name('sections.assessments.update');
    Route::delete('sections/{section}/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('sections.assessments.destroy');
    Route::post('sections/{section}/assessments/{assessment}/scores/batch', [AssessmentScoreController::class, 'batchUpdate'])->name('sections.assessments.scores.batch');
    Route::match(['put', 'patch'], 'sections/{section}/assessments/{assessment}/scores', [AssessmentScoreController::class, 'batchUpdate'])->name('sections.assessments.scores.bulk');
    Route::patch('sections/{section}/assessments/{assessment}/scores/{student}', [AssessmentScoreController::class, 'update'])->name('sections.assessments.scores.update');
    Route::get('sections/{section}/assessments/{assessment}/attachment', AssessmentAttachmentController::class)->name('sections.assessments.attachment');
    Route::get('sections/{section}/assessments/{assessment}/export', [AssessmentExportController::class, 'assessment'])->name('sections.exports.assessment');

    // Projects & Group Reporting
    Route::get('sections/{section}/projects', [ProjectController::class, 'index'])->name('sections.projects.index');
    Route::post('sections/{section}/projects', [ProjectController::class, 'store'])->name('sections.projects.store');
    Route::get('sections/{section}/projects/{project}', [ProjectController::class, 'show'])->name('sections.projects.show');
    Route::match(['put', 'patch'], 'sections/{section}/projects/{project}', [ProjectController::class, 'update'])->name('sections.projects.update');
    Route::delete('sections/{section}/projects/{project}', [ProjectController::class, 'destroy'])->name('sections.projects.destroy');
    Route::get('sections/{section}/projects/{project}/attachment', [ProjectController::class, 'attachment'])->name('sections.projects.attachment');
    Route::post('sections/{section}/projects/{project}/randomize', [ProjectController::class, 'randomize'])->name('sections.projects.randomize');
    Route::post('sections/{section}/projects/{project}/groups', [ProjectController::class, 'storeGroup'])->name('sections.projects.groups.store');
    Route::patch('sections/{section}/projects/{project}/groups/{group}', [ProjectController::class, 'updateGroup'])->name('sections.projects.groups.update');
    Route::delete('sections/{section}/projects/{project}/groups/{group}', [ProjectController::class, 'destroyGroup'])->name('sections.projects.groups.destroy');
    Route::post('sections/{section}/projects/{project}/groups/{group}/members', [ProjectController::class, 'addMember'])->name('sections.projects.groups.members.store');
    Route::patch('sections/{section}/projects/{project}/groups/{group}/members/{student}', [ProjectController::class, 'updateMember'])->name('sections.projects.groups.members.update');
    Route::delete('sections/{section}/projects/{project}/groups/{group}/members/{student}', [ProjectController::class, 'removeMember'])->name('sections.projects.groups.members.destroy');
    Route::post('sections/{section}/projects/{project}/move-member', [ProjectController::class, 'moveMember'])->name('sections.projects.members.move');
    Route::get('sections/{section}/projects/{project}/print', [ProjectController::class, 'print'])->name('sections.projects.print');

    Route::get('sections/{section}/reports/gradebook', [AssessmentReportController::class, 'gradebook'])->name('sections.reports.gradebook');
    Route::get('sections/{section}/reports/gradebook/print', [AssessmentReportController::class, 'print'])->name('sections.reports.gradebook.print');
    Route::put('sections/{section}/grading-weights', [AssessmentReportController::class, 'updateWeights'])->name('sections.grading-weights.update');
    Route::get('sections/{section}/exports/roster', [AssessmentExportController::class, 'roster'])->name('sections.exports.roster');
    Route::get('sections/{section}/exports/attendance', [AssessmentExportController::class, 'attendance'])->name('sections.exports.attendance');
    Route::get('sections/{section}/exports/gradebook', [AssessmentExportController::class, 'gradebook'])->name('sections.exports.gradebook');
});
