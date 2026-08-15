<?php

use App\Http\Controllers\LayoutBlockController;
use App\Http\Controllers\PublicJoinController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('sections', SectionController::class);
    Route::patch('sections/{section}/enrollment', [SectionController::class, 'enrollment'])->name('sections.enrollment');
    Route::post('sections/{section}/enrollment-token', [SectionController::class, 'regenerateToken'])->name('sections.enrollment-token');
    Route::patch('sections/{section}/archive', [SectionController::class, 'archive'])->name('sections.archive');
    Route::post('sections/{section}/seats/auto-assign', [SectionController::class, 'autoAssign'])->name('sections.seats.auto-assign');
    Route::post('sections/{section}/seats/reset', [SectionController::class, 'resetSeats'])->name('sections.seats.reset');
    Route::get('sections/{section}/roster/print', [SectionController::class, 'printRoster'])->name('sections.roster.print');
    Route::put('sections/{section}/floor-plan', [LayoutBlockController::class, 'replace'])->name('sections.floor-plan.replace');
    Route::post('sections/{section}/layout-blocks', [LayoutBlockController::class, 'store'])->name('sections.layout-blocks.store');
    Route::delete('sections/{section}/layout-blocks/{layoutBlock}', [LayoutBlockController::class, 'destroy'])->name('sections.layout-blocks.destroy');
    Route::post('sections/{section}/students', [StudentController::class, 'store'])->name('sections.students.store');
    Route::post('sections/{section}/students-import', [StudentController::class, 'import'])->name('sections.students.import');
    Route::patch('sections/{section}/students/{student}', [StudentController::class, 'update'])->name('sections.students.update');
    Route::patch('sections/{section}/students/{student}/seat', [StudentController::class, 'move'])->name('sections.students.move');
    Route::delete('sections/{section}/students/{student}', [StudentController::class, 'destroy'])->name('sections.students.destroy');
    Route::get('sections/{section}/students/{student}/photo', [StudentController::class, 'photo'])->name('sections.students.photo');
});

Route::get('join/{token}', [PublicJoinController::class, 'show'])->name('join.show');
Route::post('join/{token}', [PublicJoinController::class, 'store'])->middleware('throttle:10,1')->name('join.store');
