<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function section(User $user): Section
    {
        $term = AcademicTerm::create([
            'user_id' => $user->id, 'name' => 'First semester', 'school_year' => '2026-2027',
            'starts_on' => '2026-08-01', 'ends_on' => '2026-12-20',
        ]);

        return Section::create([
            'user_id' => $user->id, 'academic_term_id' => $term->id,
            'subject_code' => 'MATH101', 'subject_title' => 'Mathematics', 'name' => 'Section A',
        ]);
    }

    public function test_teacher_can_create_an_assessment_and_matching_session_is_selected(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $session = AttendanceSession::create([
            'section_id' => $section->id, 'session_date' => '2026-08-10',
            'starts_at' => '08:00', 'ends_at' => '09:00', 'duration_minutes' => 60,
        ]);

        $response = $this->actingAs($user)->post(route('sections.assessments.store', $section), [
            'type' => 'quiz', 'title' => 'Fractions quiz',
            'conducted_on' => '2026-08-10', 'max_points' => 20,
        ]);

        $assessment = Assessment::firstOrFail();
        $response->assertRedirect(route('sections.assessments.show', [$section, $assessment]));
        $this->assertSame($session->id, $assessment->attendance_session_id);
        $this->assertSame('quiz', $assessment->type);
    }

    public function test_zero_is_recorded_while_blank_remains_missing(): void
    {
        [$user, $section, $student, $assessment] = $this->scoreFixture();

        $this->actingAs($user)->patchJson(route('sections.assessments.scores.update', [$section, $assessment, $student]), ['score' => 0])
            ->assertOk()->assertJsonPath('score', '0.00');
        $this->assertSame('0.00', $assessment->scores()->firstOrFail()->score);

        $this->actingAs($user)->patchJson(route('sections.assessments.scores.update', [$section, $assessment, $student]), ['score' => null])
            ->assertOk()->assertJsonPath('score', null);
        $this->assertNull($assessment->scores()->firstOrFail()->score);
    }

    public function test_absent_students_require_an_explicit_override(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $student = Student::create([
            'section_id' => $section->id, 'student_number' => '2026-002',
            'first_name' => 'Grace', 'last_name' => 'Hopper',
        ]);
        $session = AttendanceSession::create([
            'section_id' => $section->id, 'session_date' => '2026-08-10',
            'starts_at' => '09:00', 'ends_at' => '10:00', 'duration_minutes' => 60,
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $session->id, 'student_id' => $student->id,
            'status' => 'absent', 'attended_minutes' => 0,
        ]);
        $assessment = Assessment::create([
            'section_id' => $section->id, 'attendance_session_id' => $session->id,
            'type' => 'exam', 'title' => 'Midterm', 'conducted_on' => '2026-08-10', 'max_points' => 100,
        ]);

        $url = route('sections.assessments.scores.update', [$section, $assessment, $student]);
        $this->actingAs($user)->patchJson($url, ['score' => 75])->assertUnprocessable();
        $this->actingAs($user)->patchJson($url, ['score' => 75, 'include_absent' => true])
            ->assertOk()->assertJsonPath('absence_override', true);
    }

    public function test_teachers_cannot_access_another_teachers_assessments(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $section = $this->section($owner);
        $assessment = Assessment::create([
            'section_id' => $section->id, 'type' => 'quiz', 'title' => 'Private quiz',
            'conducted_on' => '2026-08-10', 'max_points' => 10,
        ]);

        $this->actingAs($intruder)->get(route('sections.assessments.show', [$section, $assessment]))->assertForbidden();
        $this->actingAs($intruder)->get(route('sections.exports.gradebook', $section))->assertForbidden();
    }

    public function test_score_cannot_exceed_the_assessment_maximum(): void
    {
        [$user, $section, $student, $assessment] = $this->scoreFixture();

        $this->actingAs($user)
            ->patchJson(route('sections.assessments.scores.update', [$section, $assessment, $student]), ['score' => 10.01])
            ->assertUnprocessable()->assertJsonValidationErrors('score');
    }

    public function test_gradebook_keeps_a_fixed_query_count_for_a_full_grid_workload(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $now = now();
        $studentRows = [];

        foreach (range(0, 59) as $index) {
            $studentRows[] = [
                'section_id' => $section->id,
                'student_number' => '2026-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Student '.$index,
                'last_name' => 'Learner '.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Student::insert($studentRows);
        $assessmentRows = [];

        foreach (range(0, 119) as $index) {
            $assessmentRows[] = [
                'section_id' => $section->id,
                'type' => Assessment::TYPES[$index % count(Assessment::TYPES)],
                'title' => 'Assessment '.$index,
                'conducted_on' => today()->subDays(119 - $index)->toDateString(),
                'max_points' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Assessment::insert($assessmentRows);
        $students = $section->students()->orderBy('id')->get(['id']);
        $assessments = Assessment::where('section_id', $section->id)->orderBy('id')->get(['id']);
        $scoreRows = [];

        foreach ($students as $studentIndex => $student) {
            foreach ($assessments as $assessmentIndex => $assessment) {
                if (($studentIndex + $assessmentIndex) % 5 === 0) {
                    continue;
                }

                $scoreRows[] = [
                    'assessment_id' => $assessment->id,
                    'student_id' => $student->id,
                    'score' => 10,
                    'absence_override' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($scoreRows, 500) as $chunk) {
            AssessmentScore::insert($chunk);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('sections.reports.gradebook', $section));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $firstAssessmentId = $assessments->first()->id;
        $secondAssessmentId = $assessments->get(1)->id;
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reports/Gradebook')
            ->has('assessments', 120)
            ->has('rows', 60)
            ->has('rows.0.scores', 120)
            ->where('rows.0.scores.'.$firstAssessmentId, null)
            ->where('rows.0.scores.'.$secondAssessmentId, '10.00')
            ->where('rows.0.categories.activity.earned', 320)
            ->where('rows.0.categories.activity.possible', 800)
            ->where('rows.0.categories.activity.missing', 8));
        $this->assertLessThanOrEqual(10, $queryCount, 'Gradebook queries must not grow with the matrix size.');
    }

    public function test_teacher_can_update_all_six_grading_rubric_weights(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);

        $payload = [
            'activity' => 15,
            'quiz' => 20,
            'exam' => 30,
            'project' => 15,
            'attendance' => 10,
            'recitation' => 10,
        ];

        $this->actingAs($user)->put(route('sections.grading-weights.update', $section), $payload)
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame($payload, $section->fresh()->grading_weights);

        // Fails if core coursework does not equal 100%
        $invalidPayload = [
            'activity' => 10,
            'quiz' => 10,
            'exam' => 10,
            'project' => 10,
            'attendance' => 10,
            'recitation' => 5,
        ];
        $this->actingAs($user)->put(route('sections.grading-weights.update', $section), $invalidPayload)
            ->assertSessionHasErrors('weights');
    }

    public function test_teacher_can_update_only_recitation_bonus_cap(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);

        $this->actingAs($user)->put(route('sections.grading-weights.update', $section), ['recitation' => 8])
            ->assertRedirect()->assertSessionHas('success');

        $weights = $section->fresh()->grading_weights;
        $this->assertSame(8, $weights['recitation']);
        $this->assertSame(20, $weights['activity']);
        $this->assertSame(20, $weights['quiz']);
        $this->assertSame(25, $weights['exam']);
        $this->assertSame(20, $weights['project']);
        $this->assertSame(15, $weights['attendance']);
    }

    public function test_gradebook_computes_project_and_attendance_scores_correctly(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);

        $studentA = Student::create([
            'section_id' => $section->id, 'student_number' => '2026-001',
            'first_name' => 'John', 'last_name' => 'Doe',
        ]);
        $studentB = Student::create([
            'section_id' => $section->id, 'student_number' => '2026-002',
            'first_name' => 'Jane', 'last_name' => 'Smith',
        ]);

        // Create Attendance Sessions
        $sess1 = AttendanceSession::create([
            'section_id' => $section->id, 'session_date' => '2026-08-10',
            'starts_at' => '08:00', 'ends_at' => '09:00', 'duration_minutes' => 60,
        ]);
        $sess2 = AttendanceSession::create([
            'section_id' => $section->id, 'session_date' => '2026-08-11',
            'starts_at' => '08:00', 'ends_at' => '09:00', 'duration_minutes' => 60,
        ]);

        // Student A: Present in sess1, Late in sess2 -> earned 1.5/2 = 75%
        AttendanceRecord::create(['attendance_session_id' => $sess1->id, 'student_id' => $studentA->id, 'status' => 'present', 'attended_minutes' => 60]);
        AttendanceRecord::create(['attendance_session_id' => $sess2->id, 'student_id' => $studentA->id, 'status' => 'late', 'attended_minutes' => 30]);

        // Student B: Absent in sess1, Present in sess2 -> earned 1.0/2 = 50%
        AttendanceRecord::create(['attendance_session_id' => $sess1->id, 'student_id' => $studentB->id, 'status' => 'absent', 'attended_minutes' => 0]);
        AttendanceRecord::create(['attendance_session_id' => $sess2->id, 'student_id' => $studentB->id, 'status' => 'present', 'attended_minutes' => 60]);

        // Create Project & Group
        $project = Project::create([
            'section_id' => $section->id, 'type' => 'project',
            'title' => 'Term Project', 'max_points' => 50,
        ]);
        $group = $project->groups()->create(['group_number' => 1, 'name' => 'Group 1', 'score' => 45]);
        $group->members()->create(['student_id' => $studentA->id, 'score' => null]); // Uses group score 45/50 (90%)
        $group->members()->create(['student_id' => $studentB->id, 'score' => 40]); // Overrides with member score 40/50 (80%)

        $response = $this->actingAs($user)->get(route('sections.reports.gradebook', $section));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reports/Gradebook')
            ->has('rows', 2)
            ->where('rows.0.attendance.percentage', 75)
            ->where('rows.0.projectSummary.percentage', 90)
            ->where('rows.1.attendance.percentage', 50)
            ->where('rows.1.projectSummary.percentage', 80));
    }

    public function test_oral_recitation_bonus_points_add_to_activity_score_without_increasing_max_points(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $section->update(['grading_weights' => [
            'activity' => 20,
            'quiz' => 20,
            'exam' => 25,
            'project' => 20,
            'attendance' => 15,
            'recitation' => 5,
        ]]);

        $studentA = Student::create([
            'section_id' => $section->id, 'student_number' => '2026-001',
            'first_name' => 'John', 'last_name' => 'Doe',
        ]);
        $studentB = Student::create([
            'section_id' => $section->id, 'student_number' => '2026-002',
            'first_name' => 'Jane', 'last_name' => 'Smith',
        ]);

        $act1 = Assessment::create([
            'section_id' => $section->id, 'type' => 'activity',
            'title' => 'Activity 1', 'conducted_on' => '2026-08-10', 'max_points' => 20,
        ]);
        $act2 = Assessment::create([
            'section_id' => $section->id, 'type' => 'activity',
            'title' => 'Activity 2', 'conducted_on' => '2026-08-11', 'max_points' => 30,
        ]);

        // Student A: scores 16/20 in Act 1, 24/30 in Act 2 = 40/50 raw (80%)
        AssessmentScore::create(['assessment_id' => $act1->id, 'student_id' => $studentA->id, 'score' => 16]);
        AssessmentScore::create(['assessment_id' => $act2->id, 'student_id' => $studentA->id, 'score' => 24]);

        // Student B: scores 16/20 in Act 1, 24/30 in Act 2 = 40/50 raw (80%)
        AssessmentScore::create(['assessment_id' => $act1->id, 'student_id' => $studentB->id, 'score' => 16]);
        AssessmentScore::create(['assessment_id' => $act2->id, 'student_id' => $studentB->id, 'score' => 24]);

        // Student A has a recitation score of 10/10 -> earns (10/10)*5 = 5.0 bonus points
        Recitation::create([
            'section_id' => $section->id,
            'student_id' => $studentA->id,
            'conducted_on' => '2026-08-12',
            'accuracy' => 5,
            'delivery' => 5,
            'score' => 10,
        ]);

        $response = $this->actingAs($user)->get(route('sections.reports.gradebook', $section));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reports/Gradebook')
            ->where('categorySummary.activity.possible', 50) // Max points denominator is strictly 50 (NOT 55)
            ->where('rows.0.categories.activity.raw_earned', 40)
            ->where('rows.0.categories.activity.bonus_earned', 5)
            ->where('rows.0.categories.activity.earned', 45) // 40 + 5 = 45
            ->where('rows.0.categories.activity.possible', 50)
            ->where('rows.0.categories.activity.percentage', 90) // 45 / 50 = 90%
            ->where('rows.1.categories.activity.raw_earned', 40)
            ->where('rows.1.categories.activity.bonus_earned', 0)
            ->where('rows.1.categories.activity.earned', 40) // 40 + 0 = 40
            ->where('rows.1.categories.activity.possible', 50)
            ->where('rows.1.categories.activity.percentage', 80)); // 40 / 50 = 80%
    }

    public function test_teacher_can_create_activity_with_file_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $section = $this->section($user);
        $file = UploadedFile::fake()->create('laboratory_instructions.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($user)->post(route('sections.assessments.store', $section), [
            'type' => 'activity',
            'title' => 'Lab Activity 1',
            'conducted_on' => '2026-08-15',
            'max_points' => 30,
            'description' => 'Complete all exercises in the PDF.',
            'attendance_session_id' => '',
            'attachment' => $file,
        ]);

        $assessment = Assessment::where('title', 'Lab Activity 1')->firstOrFail();
        $response->assertRedirect(route('sections.assessments.show', [$section, $assessment]));
        $this->assertNotNull($assessment->attachment_path);
        $this->assertSame('laboratory_instructions.pdf', $assessment->attachment_name);
        Storage::disk('local')->assertExists($assessment->attachment_path);

        // Can download the attachment
        $this->actingAs($user)->get(route('sections.assessments.attachment', [$section, $assessment]))
            ->assertOk();
    }

    public function test_teacher_can_update_assessment_with_new_attachment_and_details(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $section = $this->section($user);
        $oldFile = UploadedFile::fake()->create('old_guide.docx', 500);

        $assessment = Assessment::create([
            'section_id' => $section->id,
            'type' => 'activity',
            'title' => 'Activity 2',
            'conducted_on' => '2026-08-15',
            'max_points' => 20,
            'attachment_path' => $oldFile->store("assessments/{$section->id}", 'local'),
            'attachment_name' => 'old_guide.docx',
            'attachment_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);

        $newFile = UploadedFile::fake()->create('updated_guide.pdf', 800, 'application/pdf');

        $this->actingAs($user)->put(route('sections.assessments.update', [$section, $assessment]), [
            'type' => 'activity',
            'title' => 'Activity 2 Updated',
            'conducted_on' => '2026-08-16',
            'max_points' => 25,
            'attachment' => $newFile,
        ])->assertRedirect();

        $assessment->refresh();
        $this->assertSame('Activity 2 Updated', $assessment->title);
        $this->assertSame('25.00', $assessment->max_points);
        $this->assertSame('updated_guide.pdf', $assessment->attachment_name);
        Storage::disk('local')->assertExists($assessment->attachment_path);
    }

    public function test_teacher_can_create_quiz_with_file_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $section = $this->section($user);
        $file = UploadedFile::fake()->create('quiz_1_questions.docx', 800, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->actingAs($user)->post(route('sections.assessments.store', $section), [
            'type' => 'quiz',
            'title' => 'Quiz 1 - Algebra',
            'conducted_on' => '2026-08-16',
            'max_points' => 20,
            'description' => 'Answer the questionnaire attached.',
            'attendance_session_id' => '',
            'attachment' => $file,
        ]);

        $quiz = Assessment::where('title', 'Quiz 1 - Algebra')->firstOrFail();
        $response->assertRedirect(route('sections.assessments.show', [$section, $quiz]));
        $this->assertSame('quiz', $quiz->type);
        $this->assertSame('quiz_1_questions.docx', $quiz->attachment_name);
        Storage::disk('local')->assertExists($quiz->attachment_path);

        $this->actingAs($user)->get(route('sections.assessments.attachment', [$section, $quiz]))
            ->assertOk();
    }

    public function test_teacher_can_create_exam_with_file_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $section = $this->section($user);
        $file = UploadedFile::fake()->create('midterm_exam_packet.pdf', 2048, 'application/pdf');

        $response = $this->actingAs($user)->post(route('sections.assessments.store', $section), [
            'type' => 'exam',
            'title' => 'Midterm Examination',
            'conducted_on' => '2026-08-18',
            'max_points' => 100,
            'description' => 'Comprehensive midterm exam.',
            'attendance_session_id' => '',
            'attachment' => $file,
        ]);

        $exam = Assessment::where('title', 'Midterm Examination')->firstOrFail();
        $response->assertRedirect(route('sections.assessments.show', [$section, $exam]));
        $this->assertSame('exam', $exam->type);
        $this->assertSame('midterm_exam_packet.pdf', $exam->attachment_name);
        Storage::disk('local')->assertExists($exam->attachment_path);

        $this->actingAs($user)->get(route('sections.assessments.attachment', [$section, $exam]))
            ->assertOk();
    }

    public function test_teacher_can_batch_save_scores_for_entire_class(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $student1 = Student::create(['section_id' => $section->id, 'student_number' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $student2 = Student::create(['section_id' => $section->id, 'student_number' => 'S2', 'first_name' => 'Alan', 'last_name' => 'Turing']);

        $assessment = Assessment::create([
            'section_id' => $section->id,
            'type' => 'activity',
            'title' => 'Batch Test Activity',
            'conducted_on' => '2026-08-16',
            'max_points' => 50,
        ]);

        $response = $this->actingAs($user)->postJson(route('sections.assessments.scores.batch', [$section, $assessment]), [
            'scores' => [
                $student1->id => 48.5,
                $student2->id => 45,
            ],
            'include_absent' => false,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'All scores have been saved successfully.',
            ]);

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessment->id,
            'student_id' => $student1->id,
            'score' => 48.5,
        ]);

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessment->id,
            'student_id' => $student2->id,
            'score' => 45,
        ]);
    }

    public function test_teacher_cannot_batch_save_scores_exceeding_max_points(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $student = Student::create(['section_id' => $section->id, 'student_number' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $assessment = Assessment::create([
            'section_id' => $section->id,
            'type' => 'quiz',
            'title' => 'Max Points Validation Quiz',
            'conducted_on' => '2026-08-16',
            'max_points' => 20,
        ]);

        $response = $this->actingAs($user)->postJson(route('sections.assessments.scores.batch', [$section, $assessment]), [
            'scores' => [
                $student->id => 25, // exceeds 20
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(["scores.{$student->id}"]);
    }

    public function test_teacher_can_delete_assessment_and_cascade_removes_scores(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $student = Student::create(['section_id' => $section->id, 'student_number' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $assessment = Assessment::create([
            'section_id' => $section->id,
            'type' => 'activity',
            'title' => 'Misentry Activity To Delete',
            'conducted_on' => '2026-08-16',
            'max_points' => 10,
        ]);

        AssessmentScore::create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 9.5,
        ]);

        $this->assertDatabaseHas('assessments', ['id' => $assessment->id]);
        $this->assertDatabaseHas('assessment_scores', ['assessment_id' => $assessment->id]);

        $response = $this->actingAs($user)->delete(route('sections.assessments.destroy', [$section, $assessment]));

        $response->assertRedirect(route('sections.assessments.index', $section))
            ->assertSessionHas('success', 'Assessment deleted.');

        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
        $this->assertDatabaseMissing('assessment_scores', ['assessment_id' => $assessment->id]);
    }

    private function scoreFixture(): array
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $student = Student::create([
            'section_id' => $section->id, 'student_number' => '2026-001',
            'first_name' => 'Ada', 'last_name' => 'Lovelace',
        ]);
        $assessment = Assessment::create([
            'section_id' => $section->id, 'type' => 'activity', 'title' => 'Exercise',
            'conducted_on' => '2026-08-10', 'max_points' => 10,
        ]);

        return [$user, $section, $student, $assessment];
    }
}
