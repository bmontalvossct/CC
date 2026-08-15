<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertLessThanOrEqual(5, $queryCount, 'Gradebook queries must not grow with the matrix size.');
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
