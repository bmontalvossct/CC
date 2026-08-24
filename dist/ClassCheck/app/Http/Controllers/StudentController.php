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
            'student_number' => ['nullable', 'string', 'max:80', Rule::unique('students')->where('section_id', $section->id)->whereNotNull('student_number')->ignore($student)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
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

    public function downloadTemplate(Section $section): StreamedResponse
    {
        Gate::authorize('view', $section);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$section->subject_code.'-roster-template.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['student_number', 'first_name', 'last_name', 'middle_name']);
            fputcsv($handle, ['2026-0001', 'Juan', 'Dela Cruz', 'Santos']);
            fputcsv($handle, ['2026-0002', 'Maria', 'Santos', 'Reyes']);
            fputcsv($handle, ['', 'John', 'Smith', '']);
            fclose($handle);
        }, 200, $headers);
    }

    public function import(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);
        $request->validate(['roster' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $filePath = $request->file('roster')->getRealPath();
        $content = (string) file_get_contents($filePath);

        // Strip UTF-8 BOM if present
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Detect and normalize character encoding to UTF-8
        $detectedEncoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);
        if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
            $content = (string) mb_convert_encoding($content, 'UTF-8', $detectedEncoding);
        }
        $content = (string) mb_convert_encoding($content, 'UTF-8', 'UTF-8');

        $tempStream = fopen('php://memory', 'r+');
        fwrite($tempStream, $content);
        rewind($tempStream);

        $rawHeaders = fgetcsv($tempStream) ?: [];
        $headers = array_map(function ($header) {
            $cleaned = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/u', '', (string) $header);
            $key = strtolower(trim((string) $cleaned));

            return match ($key) {
                'first name', 'firstname', 'given_name', 'given name' => 'first_name',
                'last name', 'lastname', 'surname', 'family_name', 'family name' => 'last_name',
                'middle name', 'middlename', 'middle initial', 'mi', 'm.i.' => 'middle_name',
                'student number', 'student_id', 'student id', 'id_number', 'id number', 'id' => 'student_number',
                default => $key,
            };
        }, $rawHeaders);

        if (array_diff(['first_name', 'last_name'], $headers)) {
            fclose($tempStream);
            throw ValidationException::withMessages(['roster' => 'CSV headers must include at least first_name and last_name (student_number and middle_name are optional).']);
        }

        $successful = [];
        $failed = [];
        $rowNumber = 1; // Row 1 is header

        DB::transaction(function () use ($tempStream, $headers, $section, &$successful, &$failed, &$rowNumber) {
            while (($row = fgetcsv($tempStream)) !== false) {
                $rowNumber++;

                $cleanRow = array_map(function ($val) {
                    $str = (string) $val;

                    return trim((string) mb_convert_encoding($str, 'UTF-8', 'UTF-8'));
                }, $row);

                // Skip empty lines
                if (empty(array_filter($cleanRow, fn ($val) => $val !== ''))) {
                    continue;
                }

                if (count($cleanRow) !== count($headers)) {
                    $failed[] = [
                        'row' => $rowNumber,
                        'student_number' => $cleanRow[0] ?? '—',
                        'name' => trim(($cleanRow[1] ?? '').' '.($cleanRow[2] ?? '')) ?: 'Unknown',
                        'reason' => 'Column count mismatch (expected '.count($headers).', got '.count($cleanRow).')',
                        'raw' => implode(', ', array_slice($cleanRow, 0, 4)),
                    ];

                    continue;
                }

                $data = array_combine($headers, $cleanRow);
                $studentNumber = ! empty($data['student_number']) ? trim((string) $data['student_number']) : null;
                $firstName = trim((string) ($data['first_name'] ?? ''));
                $lastName = trim((string) ($data['last_name'] ?? ''));
                $middleName = ! empty($data['middle_name']) ? trim((string) $data['middle_name']) : null;

                $missingFields = [];
                if (empty($firstName)) {
                    $missingFields[] = 'first_name';
                }
                if (empty($lastName)) {
                    $missingFields[] = 'last_name';
                }

                if (! empty($missingFields)) {
                    $failed[] = [
                        'row' => $rowNumber,
                        'student_number' => $studentNumber ?: '—',
                        'name' => trim("{$firstName} {$lastName}") ?: 'Missing',
                        'reason' => 'Missing required field(s): '.implode(', ', $missingFields),
                        'raw' => implode(', ', $cleanRow),
                    ];

                    continue;
                }

                $student = null;
                if ($studentNumber) {
                    $student = $section->students()->where('student_number', $studentNumber)->first();
                }

                $action = $student ? 'Updated' : 'Added';

                if ($student) {
                    $student->update([
                        'first_name' => $firstName,
                        'middle_name' => $middleName ?: null,
                        'last_name' => $lastName,
                        'is_active' => true,
                    ]);
                } else {
                    $section->students()->create([
                        'student_number' => $studentNumber ?: null,
                        'first_name' => $firstName,
                        'middle_name' => $middleName ?: null,
                        'last_name' => $lastName,
                        'is_active' => true,
                    ]);
                }

                $fullName = trim("{$firstName} ".($middleName ? "{$middleName} " : '')."{$lastName}");

                $successful[] = [
                    'row' => $rowNumber,
                    'student_number' => $studentNumber ?: '—',
                    'name' => $fullName,
                    'action' => $action,
                ];
            }
        });
        fclose($tempStream);

        $successCount = count($successful);
        $failedCount = count($failed);
        $totalProcessed = $successCount + $failedCount;

        $message = "Import completed: {$successCount} student(s) imported successfully.";
        if ($failedCount > 0) {
            $message .= " {$failedCount} row(s) failed or were skipped.";
        }

        return back()->with([
            'success' => $message,
            'import_results' => [
                'total' => $totalProcessed,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'successful' => $successful,
                'failed' => $failed,
            ],
        ]);
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
