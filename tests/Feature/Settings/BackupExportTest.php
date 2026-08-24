<?php

namespace Tests\Feature\Settings;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BackupExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_settings_page_renders_successfully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('backup.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/BackupExport')
            ->has('driver')
            ->has('isSqlite')
            ->has('stats')
        );
    }

    public function test_user_can_export_full_json_backup(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
            'is_current' => true,
        ]);
        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS101',
            'subject_title' => 'Computer Science 1',
            'name' => 'Section A',
        ]);
        $student = $section->students()->create([
            'student_number' => '2026-0001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);
        $assessment = $section->assessments()->create([
            'title' => 'Quiz 1',
            'type' => 'quiz',
            'assessment_number' => 1,
            'max_points' => 50,
            'conducted_on' => '2026-08-24',
        ]);
        $assessment->scores()->create([
            'student_id' => $student->id,
            'score' => 45,
        ]);

        $response = $this->actingAs($user)->get(route('backup.export-json'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $content = $response->streamedContent();
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertEquals('ClassCheck', $data['meta']['app']);
        $this->assertCount(1, $data['academic_terms']);
        $this->assertCount(1, $data['sections']);
        $this->assertEquals('CS101', $data['sections'][0]['subject_code']);
        $this->assertCount(1, $data['sections'][0]['students']);
        $this->assertEquals('Juan', $data['sections'][0]['students'][0]['first_name']);
        $this->assertCount(1, $data['sections'][0]['assessments']);
        $this->assertEquals('Quiz 1', $data['sections'][0]['assessments'][0]['title']);
    }

    public function test_user_can_export_csv_reports(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
        ]);
        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS101',
            'subject_title' => 'Intro to Computer Science',
            'name' => 'Section A',
        ]);
        $student = $section->students()->create([
            'student_number' => '2026-0001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);

        // Test students CSV
        $respStudents = $this->actingAs($user)->get(route('backup.export-csv', ['type' => 'students']));
        $respStudents->assertOk();
        $respStudents->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Maria', $respStudents->streamedContent());

        // Test attendance CSV
        $session = $section->attendanceSessions()->create([
            'session_date' => '2026-08-24',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'duration_minutes' => 120,
        ]);
        $session->records()->create([
            'student_id' => $student->id,
            'status' => 'present',
            'attended_minutes' => 120,
        ]);

        $respAtt = $this->actingAs($user)->get(route('backup.export-csv', ['type' => 'attendance']));
        $respAtt->assertOk();
        $this->assertStringContainsString('Present', $respAtt->streamedContent());

        // Test grades CSV
        $assessment = $section->assessments()->create([
            'title' => 'Exam 1',
            'type' => 'exam',
            'assessment_number' => 1,
            'max_points' => 100,
            'conducted_on' => '2026-08-24',
        ]);
        $assessment->scores()->create([
            'student_id' => $student->id,
            'score' => 95,
        ]);

        $respGrades = $this->actingAs($user)->get(route('backup.export-csv', ['type' => 'grades']));
        $respGrades->assertOk();
        $this->assertStringContainsString('Exam 1', $respGrades->streamedContent());

        // Test recitations CSV
        $section->recitations()->create([
            'student_id' => $student->id,
            'score' => 10,
            'conducted_on' => '2026-08-24',
            'comments' => 'Great recitation',
        ]);

        $respRec = $this->actingAs($user)->get(route('backup.export-csv', ['type' => 'recitations']));
        $respRec->assertOk();
        $this->assertStringContainsString('Great recitation', $respRec->streamedContent());
    }

    public function test_user_can_restore_backup_json_data(): void
    {
        $user = User::factory()->create();

        $payload = [
            'meta' => [
                'app' => 'ClassCheck',
                'version' => '1.0.0',
            ],
            'academic_terms' => [
                [
                    'id' => 1,
                    'name' => '2nd Semester',
                    'school_year' => '2026-2027',
                    'starts_on' => '2027-01-10',
                    'ends_on' => '2027-05-30',
                    'is_current' => true,
                ],
            ],
            'sections' => [
                [
                    'id' => 10,
                    'academic_term_id' => 1,
                    'subject_code' => 'IT202',
                    'subject_title' => 'Web Development',
                    'name' => 'BSIT 2-B',
                    'schedules' => [
                        [
                            'day_of_week' => 'monday',
                            'starts_at' => '09:00',
                            'ends_at' => '12:00',
                            'room' => 'Lab 3',
                            'schedule_type' => 'lecture',
                        ],
                    ],
                    'students' => [
                        [
                            'id' => 101,
                            'student_number' => '2026-9999',
                            'first_name' => 'Alex',
                            'last_name' => 'Rivera',
                            'is_active' => true,
                        ],
                    ],
                    'layout_blocks' => [
                        [
                            'label' => 'Main Row',
                            'block_row' => 1,
                            'block_column' => 1,
                            'internal_rows' => 1,
                            'internal_columns' => 1,
                            'seats' => [
                                [
                                    'row_number' => 1,
                                    'column_number' => 1,
                                    'label' => 'Seat 1',
                                    'student_id' => 101,
                                ],
                            ],
                        ],
                    ],
                    'attendance_sessions' => [
                        [
                            'session_date' => '2027-01-15',
                            'starts_at' => '09:00',
                            'ends_at' => '12:00',
                            'duration_minutes' => 180,
                            'records' => [
                                [
                                    'student_id' => 101,
                                    'status' => 'present',
                                    'attended_minutes' => 180,
                                ],
                            ],
                        ],
                    ],
                    'assessments' => [
                        [
                            'title' => 'Lab Exercise 1',
                            'type' => 'activity',
                            'assessment_number' => 1,
                            'max_points' => 30,
                            'conducted_on' => '2027-01-15',
                            'scores' => [
                                [
                                    'student_id' => 101,
                                    'score' => 28,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($payload));

        $response = $this->actingAs($user)->post(route('backup.restore'), [
            'backup_file' => $file,
            'clean_replace' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('academic_terms', [
            'user_id' => $user->id,
            'name' => '2nd Semester',
        ]);

        $this->assertDatabaseHas('sections', [
            'user_id' => $user->id,
            'subject_code' => 'IT202',
        ]);

        $this->assertDatabaseHas('students', [
            'first_name' => 'Alex',
            'last_name' => 'Rivera',
        ]);

        $this->assertDatabaseHas('attendance_sessions', [
            'session_date' => '2027-01-15',
        ]);

        $this->assertDatabaseHas('assessments', [
            'title' => 'Lab Exercise 1',
        ]);
    }
}
