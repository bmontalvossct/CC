<?php

namespace Tests\Feature\Settings;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\GradebookCalculationService;
use App\Services\GradeComputationService;
use App\Services\GradingFormula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingFormulaTest extends TestCase
{
    use RefreshDatabase;

    public function test_formula_settings_require_authentication_and_only_update_the_current_user(): void
    {
        $this->get('/settings/grading-formula')->assertRedirect('/login');
        $this->put('/settings/grading-formula', GradingFormula::DEFAULTS)->assertRedirect('/login');
        $teacher = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($teacher)->get('/settings/grading-formula')->assertInertia(fn ($page) => $page
            ->component('settings/GradingFormula')->where('formula.best_grade', 1)->where('hasCustomFormula', false));

        $settings = GradingFormula::resolve(['passing_grade' => 2.5]);
        $this->put('/settings/grading-formula', [...$settings, 'user_id' => $other->id])
            ->assertRedirect('/settings/grading-formula')->assertSessionHasNoErrors();
        $this->assertEquals(2.5, $teacher->fresh()->grading_formula['passing_grade']);
        $this->assertNull($other->fresh()->grading_formula);
    }

    public function test_invalid_formulas_are_rejected_without_replacing_saved_settings(): void
    {
        $teacher = User::factory()->create();
        $this->actingAs($teacher);
        $cases = [
            [['best_grade' => 6], 'best_grade'],
            [['passing_grade' => 5], 'passing_grade'],
            [['failed_grade' => 2], 'failed_grade'],
            [['midterm_weight' => 40], 'final_weight'],
            [['decimal_places' => 5], 'decimal_places'],
            [['final_rounding' => 'execute'], 'final_rounding'],
            [['weights' => ['activity' => 30]], 'weights'],
            [['passing_rates' => ['quiz' => 'invalid']], 'passing_rates.quiz'],
        ];
        foreach ($cases as [$change, $error]) {
            $this->put('/settings/grading-formula', GradingFormula::resolve($change))
                ->assertSessionHasErrors($error);
            $this->assertNull($teacher->fresh()->grading_formula);
        }
    }

    public function test_saved_formula_recalculates_warm_cached_gradebooks_insights_print_and_exports(): void
    {
        [$teacher, $section, $student] = $this->fixture();
        $service = app(GradebookCalculationService::class);
        $before = $service->calculateGradebook($section);
        $this->assertSame('2.0', $before['rows']->first()['scale_grade']);
        $this->assertTrue($before['rows']->first()['is_passing']);

        $settings = GradingFormula::resolve([
            'passing_grade' => 2,
            'midterm_weight' => 80,
            'final_weight' => 20,
            'weights' => ['activity' => 0, 'laboratory' => 0, 'quiz' => 100, 'exam' => 0, 'project' => 0, 'attendance' => 0, 'recitation' => 0],
        ]);
        $this->actingAs($teacher)->put('/settings/grading-formula', $settings)->assertSessionHasNoErrors();
        // Even a late response restoring an old cache entry cannot serve an old formula.
        cache()->put("section_{$section->id}_gradebook_calc", $before, 60);
        $after = $service->calculateGradebook($section);
        $this->assertSame('5.0', $after['rows']->first()['scale_grade']);
        $this->assertFalse($after['rows']->first()['is_passing']);
        $this->assertEquals(80, $after['gradingWeights']['midterm_weight']);
        $insights = $service->getSectionInsights($section);
        $this->assertSame(0, $insights['passing_count']);
        $this->assertSame(1, $insights['failing_count']);
        $this->assertCount(1, $insights['at_risk_students']);
        foreach (['', '/print'] as $suffix) {
            $this->get("/sections/{$section->id}/reports/gradebook{$suffix}")->assertInertia(fn ($page) => $page
                ->where('rows.0.scale_grade', '5.0')->where('gradingFormula.passing_grade', 2));
        }
        $csv = $this->get("/sections/{$section->id}/exports/gradebook")->assertOk()->streamedContent();
        $this->assertStringContainsString('5.0', $csv);
        $this->assertSame(['50.00', '100.00'], AssessmentScore::where('student_id', $student->id)->orderBy('id')->pluck('score')->all());

        $settings['passing_grade'] = 3;
        $this->put('/settings/grading-formula', $settings)->assertSessionHasNoErrors();
        $this->assertSame('2.6', $service->calculateGradebook($section)['rows']->first()['scale_grade']);
        $this->assertSame(1, $service->getSectionInsights($section)['passing_count']);
    }

    public function test_component_weights_and_scale_change_results_for_only_the_owner(): void
    {
        [$teacher, $section] = $this->fixture();
        [$other, $otherSection] = $this->fixture();
        $settings = GradingFormula::resolve([
            'best_grade' => 0, 'worst_grade' => 4, 'passing_grade' => 2,
            'failed_grade' => 4, 'decimal_places' => 2,
            'weights' => ['activity' => 0, 'laboratory' => 0, 'quiz' => 100, 'exam' => 0, 'project' => 0, 'attendance' => 0, 'recitation' => 0],
        ]);
        $this->actingAs($teacher)->put('/settings/grading-formula', $settings)->assertSessionHasNoErrors();
        $service = app(GradebookCalculationService::class);
        $this->assertSame('1.00', $service->calculateGradebook($section)['rows']->first()['scale_grade']);
        $this->assertSame('2.0', $service->calculateGradebook($otherSection)['rows']->first()['scale_grade']);
        $this->assertNull($other->fresh()->grading_formula);
    }

    public function test_weighted_oral_recitation_must_be_included_in_the_weight_total(): void
    {
        $this->actingAs(User::factory()->create());
        $settings = GradingFormula::resolve(['recitation_mode' => 'weighted']);
        $this->put('/settings/grading-formula', $settings)->assertSessionHasErrors('weights');
        $settings['weights']['activity'] = 15;
        $this->put('/settings/grading-formula', $settings)->assertSessionHasNoErrors();
    }

    public function test_rounding_and_zero_weight_periods_follow_the_saved_formula(): void
    {
        $service = new GradeComputationService;
        $formula = GradingFormula::resolve(['midterm_weight' => 0, 'final_weight' => 100, 'decimal_places' => 2, 'final_rounding' => 'up', 'replace_failing_grade' => false]);
        $this->assertSame('1.24', $service->computeOverallFinalGrade(null, 1.231, $formula)['formatted_final_grade']);
        $formula['final_rounding'] = 'down';
        $this->assertSame('1.23', $service->computeOverallFinalGrade(null, 1.239, $formula)['formatted_final_grade']);
        $this->assertSame('3.20', $service->computeOverallFinalGrade(null, 3.2, $formula)['formatted_final_grade']);
        $this->assertFalse($service->computeOverallFinalGrade(null, 3.2, $formula)['is_passing']);
        $this->assertNull($service->computeOverallFinalGrade(null, null, $formula)['final_grade']);
    }

    public function test_weighted_recitation_does_not_also_add_an_activity_bonus(): void
    {
        [$teacher, $section, $student] = $this->fixture();
        foreach (['2026-09-01', '2026-11-15'] as $index => $date) {
            $assessment = Assessment::create(['section_id' => $section->id, 'type' => 'activity', 'term_period' => $index === 0 ? 'midterm' : 'final', 'title' => 'Activity', 'conducted_on' => $date, 'max_points' => 100]);
            AssessmentScore::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 50]);
            Recitation::create(['section_id' => $section->id, 'student_id' => $student->id, 'score' => 10, 'conducted_on' => $date]);
        }
        $settings = GradingFormula::resolve(['recitation_mode' => 'weighted', 'weights' => ['activity' => 50, 'laboratory' => 0, 'quiz' => 0, 'exam' => 0, 'project' => 0, 'attendance' => 0, 'recitation' => 50]]);
        $this->actingAs($teacher)->put('/settings/grading-formula', $settings)->assertSessionHasNoErrors();
        $row = app(GradebookCalculationService::class)->calculateGradebook($section)['rows']->first();
        $this->assertSame('2.0', $row['scale_grade']);
        $this->assertEquals(0, $row['midterm']['recitation']['bonus_points']);
        $this->assertEquals(50, $row['midterm']['categories']['activity']['earned']);
        $this->assertEquals(75, $row['midterm']['weighted_grade']);
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create();
        $term = AcademicTerm::create(['user_id' => $teacher->id, 'name' => 'First semester', 'school_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-31', 'is_current' => true]);
        $section = Section::create(['user_id' => $teacher->id, 'academic_term_id' => $term->id, 'name' => 'Formula test', 'subject_code' => 'TEST101', 'subject_title' => 'Formula testing', 'grading_weights' => ['activity' => 0, 'laboratory' => 0, 'quiz' => 100, 'exam' => 0, 'project' => 0, 'attendance' => 0, 'recitation' => 0]]);
        $student = Student::create(['section_id' => $section->id, 'student_number' => 'TEST-001', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'is_active' => true]);
        foreach ([['midterm', '2026-09-01', 50], ['final', '2026-11-15', 100]] as [$period, $date, $score]) {
            $assessment = Assessment::create(['section_id' => $section->id, 'type' => 'quiz', 'term_period' => $period, 'title' => "{$period} quiz", 'conducted_on' => $date, 'max_points' => 100]);
            AssessmentScore::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => $score]);
        }

        return [$teacher, $section, $student];
    }
}
