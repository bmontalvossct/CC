<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LayoutBlock;
use App\Models\Recitation;
use App\Models\Seat;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        DB::transaction(function () {
            $this->seedSampleData();
        });
    }

    private function seedSampleData(): void
    {
        // 1. Primary Demo Teacher Account
        $teacher = User::query()->updateOrCreate(
            ['email' => 'teacher@classcheck.test'],
            [
                'name' => 'Prof. Maria Santos',
                'username' => 'mariasantos',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        // 2. Academic Terms
        $currentTerm = AcademicTerm::query()->updateOrCreate(
            ['user_id' => $teacher->id, 'name' => 'First Semester', 'school_year' => '2026-2027'],
            ['starts_on' => '2026-08-01', 'ends_on' => '2026-12-20'],
        );

        $pastTerm = AcademicTerm::query()->updateOrCreate(
            ['user_id' => $teacher->id, 'name' => 'Second Semester', 'school_year' => '2025-2026'],
            ['starts_on' => '2026-01-15', 'ends_on' => '2026-05-30'],
        );

        // 3. Section 1: CS 101 - Introduction to Computer Science
        $this->seedSection1($teacher, $currentTerm);

        // 4. Section 2: IT 202 - Web Systems and Technologies
        $this->seedSection2($teacher, $currentTerm);

        // 5. Section 3: DS 301 - Data Structures and Algorithms
        $this->seedSection3($teacher, $currentTerm);

        // 6. Section 4: Archived past section
        $this->seedArchivedSection($teacher, $pastTerm);
    }

    private function seedSection1(User $teacher, AcademicTerm $term): void
    {
        $section = Section::query()->updateOrCreate(
            ['user_id' => $teacher->id, 'academic_term_id' => $term->id, 'name' => 'BSCS 1-A'],
            [
                'subject_code' => 'CS 101',
                'subject_title' => 'Introduction to Computer Science',
                'room' => 'Lab 301',
                'enrollment_open' => true,
                'grading_weights' => [
                    'attendance' => 10,
                    'activities' => 30,
                    'quizzes' => 30,
                    'exams' => 30,
                ],
                'archived_at' => null,
            ],
        );

        // Schedules: Mon & Wed 08:00-09:30, Fri 08:00-10:00
        $section->schedules()->delete();
        $section->schedules()->createMany([
            ['day_of_week' => 1, 'starts_at' => '08:00', 'ends_at' => '09:30'],
            ['day_of_week' => 3, 'starts_at' => '08:00', 'ends_at' => '09:30'],
            ['day_of_week' => 5, 'starts_at' => '08:00', 'ends_at' => '10:00'],
        ]);

        // Students
        $names = [
            ['2026-0101', 'Andrea Mae', 'C.', 'Alcantara'],
            ['2026-0102', 'Benedict', 'M.', 'Bautista'],
            ['2026-0103', 'Carla Jane', null, 'Castillo'],
            ['2026-0104', 'Daniel John', 'S.', 'De la Cruz'],
            ['2026-0105', 'Elena Rose', 'P.', 'Espiritu'],
            ['2026-0106', 'Francis Kyle', null, 'Flores'],
            ['2026-0107', 'Grace Anne', 'L.', 'Garcia'],
            ['2026-0108', 'Harold Dean', 'T.', 'Hernandez'],
            ['2026-0109', 'Ivy Joy', null, 'Ilagan'],
            ['2026-0110', 'Joshua Paolo', 'R.', 'Jimenez'],
            ['2026-0111', 'Katrina Bella', 'G.', 'Lim'],
            ['2026-0112', 'Luis Gabriel', null, 'Mendoza'],
            ['2026-0113', 'Mia Nicole', 'B.', 'Navarro'],
            ['2026-0114', 'Nathaniel', 'D.', 'Ocampo'],
            ['2026-0115', 'Patricia Faye', null, 'Pascual'],
            ['2026-0116', 'Rafael Luis', 'V.', 'Quizon'],
            ['2026-0117', 'Samantha Mae', 'E.', 'Ramos'],
            ['2026-0118', 'Tristan James', null, 'Salazar'],
            ['2026-0119', 'Vanessa Rae', 'A.', 'Torres'],
            ['2026-0120', 'William Mark', 'Z.', 'Villanueva'],
        ];

        $students = collect($names)->map(function (array $n) use ($section) {
            return Student::query()->updateOrCreate(
                ['section_id' => $section->id, 'student_number' => $n[0]],
                [
                    'first_name' => $n[1],
                    'middle_name' => $n[2],
                    'last_name' => $n[3],
                    'is_active' => true,
                ],
            );
        });

        // Classroom Floor Plan with 2 Blocks (Left & Right)
        $section->seats()->update(['student_id' => null]);

        $leftBlock = LayoutBlock::query()->updateOrCreate(
            ['section_id' => $section->id, 'block_row' => 1, 'block_column' => 1],
            [
                'label' => 'Left Wing',
                'internal_rows' => 5,
                'internal_columns' => 2,
                'aisle_after_rows' => [2],
                'aisle_after_columns' => [],
            ],
        );

        $rightBlock = LayoutBlock::query()->updateOrCreate(
            ['section_id' => $section->id, 'block_row' => 1, 'block_column' => 2],
            [
                'label' => 'Right Wing',
                'internal_rows' => 5,
                'internal_columns' => 2,
                'aisle_after_rows' => [2],
                'aisle_after_columns' => [],
            ],
        );

        // Assign seats
        $studentIndex = 0;
        foreach ([$leftBlock, $rightBlock] as $bIndex => $block) {
            $prefix = $bIndex === 0 ? 'L' : 'R';
            for ($r = 1; $r <= 5; $r++) {
                for ($c = 1; $c <= 2; $c++) {
                    $student = $students->get($studentIndex);
                    Seat::query()->updateOrCreate(
                        ['layout_block_id' => $block->id, 'row_number' => $r, 'column_number' => $c],
                        [
                            'label' => "{$prefix}-R{$r}-C{$c}",
                            'student_id' => $student?->id,
                            'is_disabled' => false,
                        ],
                    );
                    $studentIndex++;
                }
            }
        }

        // Attendance Sessions across 4 class meetings
        $sessionDates = [
            ['2026-08-03', '08:00', '09:30', 'Course syllabus and environment setup'],
            ['2026-08-05', '08:00', '09:30', 'Introduction to algorithms and flowcharts'],
            ['2026-08-10', '08:00', '09:30', 'Variables, data types, and operations'],
            ['2026-08-12', '08:00', '09:30', 'Conditional statements and branch logic'],
            ['2026-08-14', '08:00', '10:00', 'Hands-on lab exercises and code review'],
        ];

        $sessions = collect();
        foreach ($sessionDates as $sData) {
            $session = AttendanceSession::query()->updateOrCreate(
                ['section_id' => $section->id, 'session_date' => $sData[0], 'starts_at' => $sData[1]],
                ['ends_at' => $sData[2], 'duration_minutes' => 90, 'notes' => $sData[3]],
            );
            $sessions->push($session);

            foreach ($students as $idx => $st) {
                // Realistic attendance distribution
                $status = AttendanceRecord::STATUS_PRESENT;
                $mins = 90;
                if (($idx + Carbon::parse($sData[0])->day) % 11 === 0) {
                    $status = AttendanceRecord::STATUS_ABSENT;
                    $mins = 0;
                } elseif (($idx + Carbon::parse($sData[0])->day) % 7 === 0) {
                    $status = AttendanceRecord::STATUS_LATE;
                    $mins = 65;
                }

                AttendanceRecord::query()->updateOrCreate(
                    ['attendance_session_id' => $session->id, 'student_id' => $st->id],
                    ['status' => $status, 'attended_minutes' => $mins],
                );
            }
        }

        // Assessments
        $assessmentsData = [
            [
                'type' => 'activity',
                'title' => 'Lab 1: Development Environment Setup & First Program',
                'description' => 'Install VS Code, configure compiler, and write hello-world program.',
                'conducted_on' => '2026-08-05',
                'max_points' => 50,
                'session_idx' => 1,
            ],
            [
                'type' => 'quiz',
                'title' => 'Quiz 1: Logic Gates & Number Systems',
                'description' => 'Conversion between Binary, Octal, Decimal, Hexadecimal.',
                'conducted_on' => '2026-08-10',
                'max_points' => 30,
                'session_idx' => 2,
            ],
            [
                'type' => 'activity',
                'title' => 'Lab 2: Branching Statements & Input Validation',
                'description' => 'Interactive CLI calculator with error handling.',
                'conducted_on' => '2026-08-14',
                'max_points' => 50,
                'session_idx' => 4,
            ],
            [
                'type' => 'exam',
                'title' => 'Midterm Hands-On Practical Exam',
                'description' => 'Algorithmic problem solving and debugging assessment.',
                'conducted_on' => '2026-08-15',
                'max_points' => 100,
                'session_idx' => null,
            ],
        ];

        foreach ($assessmentsData as $aData) {
            $session = $aData['session_idx'] !== null ? $sessions->get($aData['session_idx']) : null;
            $assessment = Assessment::query()->updateOrCreate(
                ['section_id' => $section->id, 'title' => $aData['title']],
                [
                    'attendance_session_id' => $session?->id,
                    'type' => $aData['type'],
                    'description' => $aData['description'],
                    'conducted_on' => $aData['conducted_on'],
                    'max_points' => $aData['max_points'],
                ],
            );

            foreach ($students as $idx => $st) {
                $max = (float) $aData['max_points'];
                // Score between 70% and 100%
                $base = $max * 0.72;
                $variance = ($idx * 3.7) % ($max * 0.28);
                $score = round(min($max, $base + $variance), 1);

                $assessment->scores()->updateOrCreate(
                    ['student_id' => $st->id],
                    ['score' => $score, 'absence_override' => false],
                );
            }
        }

        // Recitations / Oral Participation
        $recitations = [
            [$students[0], '2026-08-05', 5, 5, 100, 'Excellent explanation of binary two\'s complement.'],
            [$students[3], '2026-08-05', 4, 4, 85, 'Good attempt on Boolean algebra simplification.'],
            [$students[6], '2026-08-10', 5, 4, 92, 'Accurately solved conditional branching question on the board.'],
            [$students[8], '2026-08-10', 3, 3, 75, 'Understood switch-case but needed assistance with break statements.'],
            [$students[11], '2026-08-12', 5, 5, 98, 'Very clear walkthrough of nested loops.'],
            [$students[14], '2026-08-14', 4, 5, 90, 'Demonstrated debug breakpoints effectively.'],
        ];

        foreach ($recitations as $rec) {
            Recitation::query()->updateOrCreate(
                ['section_id' => $section->id, 'student_id' => $rec[0]->id, 'conducted_on' => $rec[1]],
                [
                    'accuracy' => $rec[2],
                    'delivery' => $rec[3],
                    'score' => $rec[4],
                    'comments' => $rec[5],
                ],
            );
        }
    }

    private function seedSection2(User $teacher, AcademicTerm $term): void
    {
        $section = Section::query()->updateOrCreate(
            ['user_id' => $teacher->id, 'academic_term_id' => $term->id, 'name' => 'BSIT 2-B'],
            [
                'subject_code' => 'IT 202',
                'subject_title' => 'Web Systems and Technologies',
                'room' => 'Multimedia Lab 2',
                'enrollment_open' => true,
                'grading_weights' => [
                    'attendance' => 15,
                    'activities' => 25,
                    'quizzes' => 25,
                    'exams' => 35,
                ],
                'archived_at' => null,
            ],
        );

        $section->schedules()->delete();
        $section->schedules()->createMany([
            ['day_of_week' => 2, 'starts_at' => '10:00', 'ends_at' => '11:30'],
            ['day_of_week' => 4, 'starts_at' => '10:00', 'ends_at' => '11:30'],
        ]);

        $names = [
            ['2026-0201', 'Aaron Paul', 'C.', 'Aquino'],
            ['2026-0202', 'Bianca', 'M.', 'Bernardo'],
            ['2026-0203', 'Christian', null, 'Corpuz'],
            ['2026-0204', 'Danielle', 'G.', 'Dizon'],
            ['2026-0205', 'Elijah', 'P.', 'Enriquez'],
            ['2026-0206', 'Fatima', null, 'Fernandez'],
            ['2026-0207', 'Gabriel', 'S.', 'Gonzales'],
            ['2026-0208', 'Hannah', 'T.', 'Hilario'],
            ['2026-0209', 'Ian Joseph', null, 'Ignacio'],
            ['2026-0210', 'Jasmine', 'R.', 'Javier'],
            ['2026-0211', 'Kyle Matthew', 'L.', 'Laurel'],
            ['2026-0212', 'Lyka Mae', null, 'Magno'],
            ['2026-0213', 'Mark Anthony', 'B.', 'Nuñez'],
            ['2026-0214', 'Olivia', 'D.', 'Ochoa'],
            ['2026-0215', 'Paul Christian', null, 'Pineda'],
            ['2026-0216', 'Rhea Mae', 'V.', 'Roxas'],
        ];

        $students = collect($names)->map(function (array $n) use ($section) {
            return Student::query()->updateOrCreate(
                ['section_id' => $section->id, 'student_number' => $n[0]],
                [
                    'first_name' => $n[1],
                    'middle_name' => $n[2],
                    'last_name' => $n[3],
                    'is_active' => true,
                ],
            );
        });

        // Floor Plan with 2 Blocks (Door Block & Window Block)
        $section->seats()->update(['student_id' => null]);

        $block1 = LayoutBlock::query()->updateOrCreate(
            ['section_id' => $section->id, 'block_row' => 1, 'block_column' => 1],
            ['label' => 'Door Block', 'internal_rows' => 4, 'internal_columns' => 2, 'aisle_after_rows' => [], 'aisle_after_columns' => []],
        );

        $block2 = LayoutBlock::query()->updateOrCreate(
            ['section_id' => $section->id, 'block_row' => 1, 'block_column' => 2],
            ['label' => 'Window Block', 'internal_rows' => 4, 'internal_columns' => 2, 'aisle_after_rows' => [], 'aisle_after_columns' => []],
        );

        $sIdx = 0;
        foreach ([$block1, $block2] as $bIdx => $block) {
            $prefix = $bIdx === 0 ? 'D' : 'W';
            for ($r = 1; $r <= 4; $r++) {
                for ($c = 1; $c <= 2; $c++) {
                    $student = $students->get($sIdx);
                    Seat::query()->updateOrCreate(
                        ['layout_block_id' => $block->id, 'row_number' => $r, 'column_number' => $c],
                        [
                            'label' => "{$prefix}-R{$r}-C{$c}",
                            'student_id' => $student?->id,
                            'is_disabled' => false,
                        ],
                    );
                    $sIdx++;
                }
            }
        }

        // Attendance
        $sessions = [
            ['2026-08-04', '10:00', '11:30', 'Modern Web Standards & Responsive Design'],
            ['2026-08-06', '10:00', '11:30', 'CSS Flexbox and Grid Deep-dive'],
            ['2026-08-11', '10:00', '11:30', 'JavaScript ES6+ and DOM Manipulation'],
            ['2026-08-13', '10:00', '11:30', 'Fetch API and Asynchronous Programming'],
        ];

        foreach ($sessions as $sData) {
            $session = AttendanceSession::query()->updateOrCreate(
                ['section_id' => $section->id, 'session_date' => $sData[0], 'starts_at' => $sData[1]],
                ['ends_at' => $sData[2], 'duration_minutes' => 90, 'notes' => $sData[3]],
            );

            foreach ($students as $idx => $st) {
                $status = ($idx % 8 === 0 && Carbon::parse($sData[0])->day > 6) ? AttendanceRecord::STATUS_ABSENT : AttendanceRecord::STATUS_PRESENT;
                AttendanceRecord::query()->updateOrCreate(
                    ['attendance_session_id' => $session->id, 'student_id' => $st->id],
                    ['status' => $status, 'attended_minutes' => $status === 'present' ? 90 : 0],
                );
            }
        }

        // Assessments
        $assessments = [
            ['activity', 'Lab 1: Responsive Portfolio Website', 'Design a responsive personal portfolio using Tailwind CSS.', '2026-08-06', 40],
            ['quiz', 'Quiz 1: DOM Events & Async JS', 'Multiple choice and code output tracing on promises and async/await.', '2026-08-13', 25],
        ];

        foreach ($assessments as $a) {
            $ass = Assessment::query()->updateOrCreate(
                ['section_id' => $section->id, 'title' => $a[1]],
                [
                    'type' => $a[0],
                    'description' => $a[2],
                    'conducted_on' => $a[3],
                    'max_points' => $a[4],
                ],
            );

            foreach ($students as $idx => $st) {
                $max = (float) $a[4];
                $score = round($max * (0.80 + (($idx % 5) * 0.04)), 1);
                $ass->scores()->updateOrCreate(
                    ['student_id' => $st->id],
                    ['score' => min($max, $score), 'absence_override' => false],
                );
            }
        }
    }

    private function seedSection3(User $teacher, AcademicTerm $term): void
    {
        $section = Section::query()->updateOrCreate(
            ['user_id' => $teacher->id, 'academic_term_id' => $term->id, 'name' => 'BSCS 3-A'],
            [
                'subject_code' => 'DS 301',
                'subject_title' => 'Data Structures and Algorithms',
                'room' => 'Room 405',
                'enrollment_open' => true,
                'grading_weights' => [
                    'attendance' => 10,
                    'activities' => 30,
                    'quizzes' => 30,
                    'exams' => 30,
                ],
                'archived_at' => null,
            ],
        );

        $section->schedules()->delete();
        $section->schedules()->createMany([
            ['day_of_week' => 1, 'starts_at' => '13:00', 'ends_at' => '14:30'],
            ['day_of_week' => 3, 'starts_at' => '13:00', 'ends_at' => '14:30'],
        ]);

        $names = [
            ['2026-0301', 'Alexander', 'J.', 'Agoncillo'],
            ['2026-0302', 'Beatrice', 'S.', 'Buencamino'],
            ['2026-0303', 'Carlo', 'M.', 'Crisostomo'],
            ['2026-0304', 'Diana', null, 'David'],
            ['2026-0305', 'Eric', 'T.', 'Esteban'],
            ['2026-0306', 'Fiona', 'R.', 'Fabian'],
            ['2026-0307', 'George', null, 'Guinto'],
            ['2026-0308', 'Hazel', 'K.', 'Hizon'],
            ['2026-0309', 'Ivan', 'P.', 'Imperial'],
            ['2026-0310', 'Julia', 'L.', 'Jacinto'],
            ['2026-0311', 'Kevin', null, 'Katigbak'],
            ['2026-0312', 'Leah', 'N.', 'Ledesma'],
        ];

        $students = collect($names)->map(function (array $n) use ($section) {
            return Student::query()->updateOrCreate(
                ['section_id' => $section->id, 'student_number' => $n[0]],
                [
                    'first_name' => $n[1],
                    'middle_name' => $n[2],
                    'last_name' => $n[3],
                    'is_active' => true,
                ],
            );
        });

        // Floor plan: 1 single central block
        $section->seats()->update(['student_id' => null]);

        $block = LayoutBlock::query()->updateOrCreate(
            ['section_id' => $section->id, 'block_row' => 1, 'block_column' => 1],
            ['label' => 'Lecture Hall Center', 'internal_rows' => 4, 'internal_columns' => 4, 'aisle_after_rows' => [], 'aisle_after_columns' => [2]],
        );

        $sIdx = 0;
        for ($r = 1; $r <= 4; $r++) {
            for ($c = 1; $c <= 4; $c++) {
                $student = $students->get($sIdx);
                Seat::query()->updateOrCreate(
                    ['layout_block_id' => $block->id, 'row_number' => $r, 'column_number' => $c],
                    [
                        'label' => "R{$r}-C{$c}",
                        'student_id' => $student?->id,
                        'is_disabled' => false,
                    ],
                );
                $sIdx++;
            }
        }

        // Attendance Session
        $session = AttendanceSession::query()->updateOrCreate(
            ['section_id' => $section->id, 'session_date' => '2026-08-10', 'starts_at' => '13:00'],
            ['ends_at' => '14:30', 'duration_minutes' => 90, 'notes' => 'Complexity Analysis and Big O Notation'],
        );

        foreach ($students as $st) {
            AttendanceRecord::query()->updateOrCreate(
                ['attendance_session_id' => $session->id, 'student_id' => $st->id],
                ['status' => 'present', 'attended_minutes' => 90],
            );
        }
    }

    private function seedArchivedSection(User $teacher, AcademicTerm $pastTerm): void
    {
        Section::query()->updateOrCreate(
            ['user_id' => $teacher->id, 'academic_term_id' => $pastTerm->id, 'name' => 'BSCS 2-A'],
            [
                'subject_code' => 'CS 201',
                'subject_title' => 'Object-Oriented Programming (Archived)',
                'room' => 'Room 102',
                'enrollment_open' => false,
                'archived_at' => '2026-05-31 00:00:00',
            ],
        );
    }
}
