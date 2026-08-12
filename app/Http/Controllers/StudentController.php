<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Models\Seat;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function store(StoreStudentRequest $request, Section $section): RedirectResponse
    {
        DB::transaction(function () use ($request, $section) {
            $data = $request->validated();
            $seatId = $data['seat_id'] ?? null;
            unset($data['seat_id'], $data['photo']);
            if ($request->hasFile('photo')) {
                $data['photo_path'] = $request->file('photo')->store('classcheck/students', 'local');
            }
            $student = $section->students()->create($data);
            if ($seatId) {
                $this->assignSeat($section, $student, (int) $seatId);
            }
        });

        return back()->with('success', 'Student added to the roster.');
    }

    public function update(Request $request, Section $section, Student $student): RedirectResponse
    {
        Gate::authorize('update', $section);
        abort_unless($student->section_id === $section->id, 404);
        $data = $request->validate([
            'student_number' => ['required', 'string', 'max:80', Rule::unique('students')->where('section_id', $section->id)->ignore($student)],
            'first_name' => ['required', 'string', 'max:255'], 'middle_name' => ['nullable', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        if ($request->hasFile('photo')) {
            if ($student->photo_path) {
                Storage::disk('local')->delete($student->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('classcheck/students', 'local');
        }
        unset($data['photo']);
        $student->update($data);

        return back()->with('success', 'Student details updated.');
    }

    public function import(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);
        $request->validate(['roster' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($request->file('roster')->getRealPath(), 'r');
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), fgetcsv($handle) ?: []);
        if (array_diff(['student_number', 'first_name', 'last_name'], $headers)) {
            fclose($handle);
            throw ValidationException::withMessages(['roster' => 'CSV headers must include student_number, first_name, and last_name.']);
        }
        DB::transaction(function () use ($handle, $headers, $section) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($headers)) {
                    continue;
                }
                $data = array_combine($headers, array_map('trim', $row));
                if (! $data['student_number'] || ! $data['first_name'] || ! $data['last_name']) {
                    continue;
                }
                $section->students()->updateOrCreate(
                    ['student_number' => $data['student_number']],
                    ['first_name' => $data['first_name'], 'middle_name' => $data['middle_name'] ?? null, 'last_name' => $data['last_name'], 'is_active' => true],
                );
            }
        });
        fclose($handle);

        return back()->with('success', 'Roster CSV imported. Students are ready to claim chairs.');
    }

    public function move(Request $request, Section $section, Student $student): RedirectResponse
    {
        Gate::authorize('update', $section);
        abort_unless($student->section_id === $section->id, 404);
        $data = $request->validate(['seat_id' => ['nullable', 'integer', 'exists:seats,id']]);
        DB::transaction(function () use ($section, $student, $data) {
            $current = $student->seat()->lockForUpdate()->first();
            if (! ($data['seat_id'] ?? null)) {
                $current?->update(['student_id' => null]);

                return;
            }
            $target = Seat::query()->with('layoutBlock')->lockForUpdate()->findOrFail($data['seat_id']);
            if ($target->layoutBlock->section_id !== $section->id || $target->is_disabled) {
                throw ValidationException::withMessages(['seat_id' => 'That chair cannot be used in this section.']);
            }
            if ($target->student_id === $student->id) {
                return;
            }
            if ($target->student_id && ! $current) {
                throw ValidationException::withMessages(['seat_id' => 'Choose an available chair for an unseated student.']);
            }
            $otherStudent = $target->student_id;
            $target->update(['student_id' => null]);
            $current?->update(['student_id' => $otherStudent]);
            $target->update(['student_id' => $student->id]);
        });

        return back()->with('success', 'Chair assignment updated.');
    }

    public function destroy(Section $section, Student $student): RedirectResponse
    {
        Gate::authorize('update', $section);
        abort_unless($student->section_id === $section->id, 404);
        DB::transaction(function () use ($student) {
            $student->seat()->update(['student_id' => null]);
            $student->update(['is_active' => false]);
        });

        return back()->with('success', 'Student removed from the active roster.');
    }

    public function photo(Section $section, Student $student): StreamedResponse
    {
        Gate::authorize('view', $section);
        abort_unless($student->section_id === $section->id && $student->photo_path, 404);
        abort_unless(Storage::disk('local')->exists($student->photo_path), 404);

        return Storage::disk('local')->download($student->photo_path);
    }

    private function assignSeat(Section $section, Student $student, int $seatId): void
    {
        $seat = Seat::query()->with('layoutBlock')->lockForUpdate()->findOrFail($seatId);
        if ($seat->layoutBlock->section_id !== $section->id || $seat->is_disabled || $seat->student_id) {
            throw ValidationException::withMessages(['seat_id' => 'That chair is not available in this section.']);
        }
        $seat->update(['student_id' => $student->id]);
    }
}
