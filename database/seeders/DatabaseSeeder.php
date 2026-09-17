<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LayoutBlock;
use App\Models\Seat;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $teacher = User::query()->updateOrCreate(
            ['email' => 'teacher@classcheck.test'],
            [
                'name' => 'Maria Santos',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $term = AcademicTerm::query()->updateOrCreate(
            ['user_id' => $teacher->id, 'name' => 'First Semester', 'school_year' => '2026-2027'],
            ['starts_on' => '2026-06-01', 'ends_on' => '2026-10-31'],
        );

        $section = Section::query()->updateOrCreate(
            ['user_id' => $teacher->id, 'academic_term_id' => $term->id, 'name' => 'Narra'],
            [
                'subject_code' => 'SCI-10',
                'subject_title' => 'Science 10',
                'room' => 'Room 204',
                'enrollment_open' => true,
                'archived_at' => null,
            ],
        );

        $section->schedules()->updateOrCreate(
            ['day_of_week' => 1, 'starts_at' => '08:00'],
            ['ends_at' => '09:00'],
        );

        $names = [
            ['2026-001', 'Andrea', null, 'Reyes'],
            ['2026-002', 'Ben', 'M.', 'Cruz'],
            ['2026-003', 'Carla', null, 'Flores'],
            ['2026-004', 'Daniel', null, 'Garcia'],
            ['2026-005', 'Elena', 'P.', 'Mendoza'],
            ['2026-006', 'Francis', null, 'Lim'],
            ['2026-007', 'Grace', null, 'Navarro'],
            ['2026-008', 'Harold', 'T.', 'Ocampo'],
            ['2026-009', 'Ivy', null, 'Perez'],
            ['2026-010', 'Joshua', null, 'Ramos'],
            ['2026-011', 'Katrina', 'L.', 'Santos'],
            ['2026-012', 'Luis', null, 'Torres'],
        ];

        $students = collect($names)->map(fn (array $name) => Student::query()->updateOrCreate(
            ['section_id' => $section->id, 'student_number' => $name[0]],
            [
                'first_name' => $name[1],
                'middle_name' => $name[2],
                'last_name' => $name[3],
                'is_active' => true,
            ],
        ));

        $seatIndex = 0;
        foreach (['Window block', 'Door block'] as $blockColumn => $label) {
            $block = LayoutBlock::query()->updateOrCreate(
                ['section_id' => $section->id, 'block_row' => 1, 'block_column' => $blockColumn + 1],
                ['label' => $label, 'internal_rows' => 4, 'internal_columns' => 2],
            );

            for ($row = 1; $row <= 4; $row++) {
                for ($column = 1; $column <= 2; $column++) {
                    Seat::query()->updateOrCreate(
                        ['layout_block_id' => $block->id, 'row_number' => $row, 'column_number' => $column],
                        [
                            'label' => sprintf('%s-R%d-C%d', $blockColumn === 0 ? 'A' : 'B', $row, $column),
                            'student_id' => $students->get($seatIndex)?->id,
                            'is_disabled' => false,
                        ],
                    );
                    $seatIndex++;
                }
            }
        }

        $session = AttendanceSession::query()->updateOrCreate(
            ['section_id' => $section->id, 'session_date' => '2026-08-10', 'starts_at' => '08:00'],
            ['ends_at' => '09:00', 'duration_minutes' => 60, 'notes' => 'Demo roll call'],
        );

        foreach ($students as $index => $student) {
            $present = $index !== 6;
            AttendanceRecord::query()->updateOrCreate(
                ['attendance_session_id' => $session->id, 'student_id' => $student->id],
                ['status' => $present ? 'present' : 'absent', 'attended_minutes' => $present ? 60 : 0],
            );
        }

        $assessment = Assessment::query()->updateOrCreate(
            ['section_id' => $section->id, 'type' => 'quiz', 'title' => 'Cells and systems'],
            [
                'attendance_session_id' => $session->id,
                'description' => 'Short formative quiz',
                'conducted_on' => '2026-08-10',
                'max_points' => 20,
            ],
        );

        foreach ($students as $index => $student) {
            $assessment->scores()->updateOrCreate(
                ['student_id' => $student->id],
                ['score' => $index === 6 ? null : max(10, 19 - $index), 'absence_override' => false],
            );
        }
    }
}
