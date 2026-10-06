<?php

use App\Http\Controllers\Assessments\ActivityFileController;
use App\Http\Controllers\Assessments\AssessmentAttachmentController;
use App\Http\Controllers\Assessments\AssessmentController;
use App\Http\Controllers\Assessments\AssessmentExportController;
use App\Http\Controllers\Assessments\AssessmentReportController;
use App\Http\Controllers\Assessments\AssessmentScoreController;
use App\Http\Controllers\Assessments\AutocheckerController;
use App\Http\Controllers\Assessments\ExamGeneratorController;
use App\Http\Controllers\Assessments\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('assessments', 'sections');
    Route::redirect('projects', 'sections');
    Route::redirect('gradebook', 'sections');
    Route::get('sections/{section}/assessments', [AssessmentController::class, 'index'])->name('sections.assessments.index');
    Route::post('sections/{section}/assessments', [AssessmentController::class, 'store'])->name('sections.assessments.store');
    Route::get('sections/{section}/assessments/{assessment}', [AssessmentController::class, 'show'])->name('sections.assessments.show');
    Route::match(['put', 'patch'], 'sections/{section}/assessments/{assessment}', [AssessmentController::class, 'update'])->name('sections.assessments.update');
    Route::delete('sections/{section}/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('sections.assessments.destroy');
    Route::post('sections/{section}/assessments/{assessment}/scores/batch', [AssessmentScoreController::class, 'batchUpdate'])->name('sections.assessments.scores.batch');
    Route::match(['put', 'patch'], 'sections/{section}/assessments/{assessment}/scores', [AssessmentScoreController::class, 'batchUpdate'])->name('sections.assessments.scores.bulk');
    Route::patch('sections/{section}/assessments/{assessment}/scores/{student}', [AssessmentScoreController::class, 'update'])->name('sections.assessments.scores.update');
    Route::get('sections/{section}/assessments/{assessment}/scores/{student}/attachment', [AssessmentScoreController::class, 'streamAttachment'])->name('sections.assessments.scores.attachment');
    Route::post('sections/{section}/assessments/{assessment}/scores/{student}/attachment', [AssessmentScoreController::class, 'uploadAttachment'])->name('sections.assessments.scores.attachment.upload');
    Route::delete('sections/{section}/assessments/{assessment}/scores/{student}/attachment', [AssessmentScoreController::class, 'destroyAttachment'])->name('sections.assessments.scores.attachment.destroy');
    Route::post('sections/{section}/assessments/{assessment}/scores/{student}/ai-check', [AssessmentScoreController::class, 'aiCheck'])->name('sections.assessments.scores.ai-check');
    Route::get('sections/{section}/assessments/{assessment}/attachment', AssessmentAttachmentController::class)->name('sections.assessments.attachment');
    Route::post('sections/{section}/assessments/{assessment}/attachment', [AssessmentController::class, 'reuploadAttachment'])->name('sections.assessments.attachment.reupload');
    Route::delete('sections/{section}/assessments/{assessment}/attachment', [AssessmentController::class, 'destroyAttachment'])->name('sections.assessments.attachment.destroy');
    Route::post('sections/{section}/assessments/{assessment}/rubrics', [AssessmentController::class, 'saveRubric'])->name('sections.assessments.rubrics.save');
    Route::post('sections/{section}/assessments/{assessment}/rubrics/study', [AssessmentController::class, 'studyRubric'])->name('sections.assessments.rubrics.study');
    Route::get('sections/{section}/assessments/{assessment}/export', [AssessmentExportController::class, 'assessment'])->name('sections.exports.assessment');

    Route::match(['get', 'post'], 'sections/{section}/assessments/{assessment}/activity-file', [ActivityFileController::class, 'assessment'])->name('sections.assessments.activity-file');
    Route::match(['get', 'post'], 'sections/{section}/projects/{project}/activity-file', [ActivityFileController::class, 'project'])->name('sections.projects.activity-file');
    Route::post('sections/{section}/projects/{project}/rubrics', [ProjectController::class, 'saveRubric'])->name('sections.projects.rubrics.save');
    Route::post('sections/{section}/projects/{project}/rubrics/study', [ProjectController::class, 'studyRubric'])->name('sections.projects.rubrics.study');

    // Assessment Autochecker (Bulk upload + Ollama LLM evaluator)
    Route::get('sections/{section}/assessments/{assessment}/autochecker/status', [AutocheckerController::class, 'status'])->name('sections.assessments.autochecker.status');
    Route::post('sections/{section}/assessments/{assessment}/autochecker/inspect', [AutocheckerController::class, 'inspectFiles'])->name('sections.assessments.autochecker.inspect');
    Route::post('sections/{section}/assessments/{assessment}/autochecker/evaluate', [AutocheckerController::class, 'evaluateSingle'])->name('sections.assessments.autochecker.evaluate');
    Route::post('sections/{section}/assessments/{assessment}/evaluate', [AutocheckerController::class, 'evaluateSingle']);
    Route::post('sections/{section}/assessments/{assessment}/autochecker/run-sandbox', [AutocheckerController::class, 'runPythonSandbox'])->name('sections.assessments.autochecker.run-sandbox');
    Route::post('sections/{section}/assessments/{assessment}/autochecker/apply-scores', [AutocheckerController::class, 'applyScores'])->name('sections.assessments.autochecker.apply-scores');

    // Projects & Group Reporting
    Route::get('sections/{section}/projects', [ProjectController::class, 'index'])->name('sections.projects.index');
    Route::post('sections/{section}/projects', [ProjectController::class, 'store'])->name('sections.projects.store');
    Route::get('sections/{section}/projects/{project}', [ProjectController::class, 'show'])->name('sections.projects.show');
    Route::match(['put', 'patch'], 'sections/{section}/projects/{project}', [ProjectController::class, 'update'])->name('sections.projects.update');
    Route::delete('sections/{section}/projects/{project}', [ProjectController::class, 'destroy'])->name('sections.projects.destroy');
    Route::get('sections/{section}/projects/{project}/attachment', [ProjectController::class, 'attachment'])->name('sections.projects.attachment');
    Route::post('sections/{section}/projects/{project}/attachment', [ProjectController::class, 'reuploadAttachment'])->name('sections.projects.attachment.reupload');
    Route::delete('sections/{section}/projects/{project}/attachment', [ProjectController::class, 'destroyAttachment'])->name('sections.projects.attachment.destroy');
    Route::post('sections/{section}/projects/{project}/randomize', [ProjectController::class, 'randomize'])->name('sections.projects.randomize');
    Route::post('sections/{section}/projects/{project}/groups', [ProjectController::class, 'storeGroup'])->name('sections.projects.groups.store');
    Route::patch('sections/{section}/projects/{project}/groups/{group}', [ProjectController::class, 'updateGroup'])->name('sections.projects.groups.update');
    Route::delete('sections/{section}/projects/{project}/groups/{group}', [ProjectController::class, 'destroyGroup'])->name('sections.projects.groups.destroy');
    Route::get('sections/{section}/projects/{project}/groups/{group}/attachment', [ProjectController::class, 'groupAttachment'])->name('sections.projects.groups.attachment');
    Route::post('sections/{section}/projects/{project}/groups/{group}/attachment', [ProjectController::class, 'uploadGroupAttachment'])->name('sections.projects.groups.attachment.upload');
    Route::delete('sections/{section}/projects/{project}/groups/{group}/attachment', [ProjectController::class, 'destroyGroupAttachment'])->name('sections.projects.groups.attachment.destroy');
    Route::post('sections/{section}/projects/{project}/groups/{group}/ai-check', [ProjectController::class, 'aiCheckGroup'])->name('sections.projects.groups.ai-check');
    Route::post('sections/{section}/projects/{project}/groups/{group}/members', [ProjectController::class, 'addMember'])->name('sections.projects.groups.members.store');
    Route::patch('sections/{section}/projects/{project}/groups/{group}/members/{student}', [ProjectController::class, 'updateMember'])->name('sections.projects.groups.members.update');
    Route::delete('sections/{section}/projects/{project}/groups/{group}/members/{student}', [ProjectController::class, 'removeMember'])->name('sections.projects.groups.members.destroy');
    Route::get('sections/{section}/projects/{project}/groups/{group}/members/{student}/attachment', [ProjectController::class, 'memberAttachment'])->name('sections.projects.groups.members.attachment');
    Route::post('sections/{section}/projects/{project}/groups/{group}/members/{student}/attachment', [ProjectController::class, 'uploadMemberAttachment'])->name('sections.projects.groups.members.attachment.upload');
    Route::delete('sections/{section}/projects/{project}/groups/{group}/members/{student}/attachment', [ProjectController::class, 'destroyMemberAttachment'])->name('sections.projects.groups.members.attachment.destroy');
    Route::post('sections/{section}/projects/{project}/groups/{group}/members/{student}/ai-check', [ProjectController::class, 'aiCheckMember'])->name('sections.projects.groups.members.ai-check');
    Route::post('sections/{section}/projects/{project}/move-member', [ProjectController::class, 'moveMember'])->name('sections.projects.members.move');
    Route::post('sections/{section}/projects/{project}/copy-grouping', [ProjectController::class, 'copyGrouping'])->name('sections.projects.copy-grouping');
    Route::post('sections/{section}/projects/{project}/save-all', [ProjectController::class, 'saveAll'])->name('sections.projects.save-all');
    Route::get('sections/{section}/projects/{project}/export', [ProjectController::class, 'export'])->name('sections.projects.export');
    Route::get('sections/{section}/projects/{project}/print', [ProjectController::class, 'print'])->name('sections.projects.print');

    Route::get('sections/{section}/reports/gradebook', [AssessmentReportController::class, 'gradebook'])->name('sections.reports.gradebook');
    Route::get('sections/{section}/reports/gradebook/print', [AssessmentReportController::class, 'print'])->name('sections.reports.gradebook.print');
    Route::post('sections/{section}/reports/gradebook/override-oral', [AssessmentReportController::class, 'overrideOralPoints'])->name('sections.reports.gradebook.override-oral');
    Route::put('sections/{section}/grading-weights', [AssessmentReportController::class, 'updateWeights'])->name('sections.grading-weights.update');
    Route::get('sections/{section}/exports/roster', [AssessmentExportController::class, 'roster'])->name('sections.exports.roster');
    Route::get('sections/{section}/exports/attendance', [AssessmentExportController::class, 'attendance'])->name('sections.exports.attendance');
    Route::get('sections/{section}/exports/gradebook', [AssessmentExportController::class, 'gradebook'])->name('sections.exports.gradebook');

    // Hermes-Powered Exam Generator
    Route::get('sections/{section}/exam-generator/modules', [ExamGeneratorController::class, 'modules'])->name('sections.exam-generator.modules');
    Route::get('sections/{section}/exam-generator/status', [ExamGeneratorController::class, 'status'])->name('sections.exam-generator.status');
    Route::post('sections/{section}/exam-generator/generate', [ExamGeneratorController::class, 'generate'])->name('sections.exam-generator.generate');
    Route::post('sections/{section}/exam-generator/save-assessment', [ExamGeneratorController::class, 'saveAssessment'])->name('sections.exam-generator.save-assessment');
    Route::post('sections/{section}/exam-generator/export-docx', [ExamGeneratorController::class, 'exportDocx'])->name('sections.exam-generator.export-docx');
});
