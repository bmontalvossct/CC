<?php

namespace App\Http\Controllers;

use App\Http\Requests\JoinSectionRequest;
use App\Models\Seat;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicJoinController extends Controller
{
    public function show(string $token): Response
    {
        $section = Section::query()
            ->where('enrollment_token', $token)
            ->with([
                'academicTerm:id,name,school_year',
                'layoutBlocks' => fn ($query) => $query->orderBy('block_row')->orderBy('block_column'),
                'layoutBlocks.seats' => fn ($query) => $query->orderBy('row_number')->orderBy('column_number'),
            ])
            ->firstOrFail();

        return Inertia::render('join/Show', [
            'section' => [
                'subject_code' => $section->subject_code,
                'subject_title' => $section->subject_title,
                'name' => $section->name,
                'room' => $section->room,
                'academic_term' => $section->academicTerm,
                'enrollment_open' => $section->enrollment_open,
                'blocks' => $section->layoutBlocks->map(fn ($block) => [
                    'id' => $block->id,
                    'label' => $block->label,
                    'block_row' => $block->block_row,
                    'block_column' => $block->block_column,
                    'internal_rows' => $block->internal_rows,
                    'internal_columns' => $block->internal_columns,
                    'seats' => $block->seats->map(fn ($seat) => [
                        'id' => $seat->id,
                        'label' => $seat->label,
                        'row_number' => $seat->row_number,
                        'column_number' => $seat->column_number,
                        'is_disabled' => $seat->is_disabled,
                        'is_available' => ! $seat->is_disabled && $seat->student_id === null,
                    ]),
                ]),
            ],
            'token' => $token,
        ]);
    }

    public function store(JoinSectionRequest $request, string $token): RedirectResponse
    {
        $section = Section::query()->where('enrollment_token', $token)->firstOrFail();
        if (! $section->enrollment_open) {
            throw ValidationException::withMessages(['enrollment' => 'Enrollment is currently closed. Ask your teacher to open it.']);
        }

        $photoPath = null;
        try {
            DB::transaction(function () use ($request, $section, &$photoPath) {
                $seat = Seat::query()->with('layoutBlock')->lockForUpdate()->findOrFail($request->integer('seat_id'));
                if ($seat->layoutBlock->section_id !== $section->id || $seat->is_disabled || $seat->student_id) {
                    throw ValidationException::withMessages(['seat_id' => 'That chair was just claimed. Please choose another.']);
                }

                $student = Student::query()
                    ->where('section_id', $section->id)
                    ->where('student_number', $request->string('student_number')->toString())
                    ->lockForUpdate()
                    ->first();

                if ($student?->seat()->exists()) {
                    throw ValidationException::withMessages(['student_number' => 'This student number already has a chair.']);
                }

                if ($request->hasFile('photo')) {
                    $photoPath = $request->file('photo')->store('classcheck/students', 'local');
                }

                $attributes = [
                    'first_name' => $request->string('first_name')->toString(),
                    'middle_name' => $request->filled('middle_name') ? $request->string('middle_name')->toString() : null,
                    'last_name' => $request->string('last_name')->toString(),
                ];
                if ($photoPath) {
                    $attributes['photo_path'] = $photoPath;
                }

                if ($student) {
                    $student->update([...$attributes, 'is_active' => true]);
                } else {
                    $student = $section->students()->create([
                        ...$attributes,
                        'student_number' => $request->string('student_number')->toString(),
                    ]);
                }
                $seat->update(['student_id' => $student->id]);
            }, 3);
        } catch (QueryException $exception) {
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }
            throw ValidationException::withMessages(['seat_id' => 'That chair or student number is no longer available. Please refresh and try again.']);
        } catch (\Throwable $exception) {
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }
            throw $exception;
        }

        return back()->with('success', 'Your chair is reserved. You may close this page.');
    }
}
