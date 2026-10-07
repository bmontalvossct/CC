<?php

namespace Tests\Unit;

use App\Services\GradeComputationService;
use InvalidArgumentException;
use Tests\TestCase;

class GradeComputationServiceTest extends TestCase
{
    protected GradeComputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GradeComputationService;
    }

    /**
     * Section 1: Component Numerical Grade Computation
     * ComponentGrade = 5 - (4 * (TotalEarned / TotalMaximum))
     */
    public function test_compute_component_grade_matches_excel_formula_examples(): void
    {
        // 100 / 100: 5 - (4 * 1.00) = 1.00
        $this->assertEquals(1.00, $this->service->computeComponentGrade(100, 100));

        // 32 / 50: 5 - (4 * 0.64) = 2.44
        $this->assertEquals(2.44, $this->service->computeComponentGrade(32, 50));

        // 260 / 310: 5 - (4 * 260/310) = 1.6451612903...
        $grade260 = $this->service->computeComponentGrade(260, 310);
        $this->assertEqualsWithDelta(1.6451612903, $grade260, 0.00001);

        // 112 / 130: 5 - (4 * 112/130) = 1.5538461538...
        $grade112 = $this->service->computeComponentGrade(112, 130);
        $this->assertEqualsWithDelta(1.5538461538, $grade112, 0.00001);

        // 0 / 50: 5 - (4 * 0.0) = 5.00
        $this->assertEquals(5.00, $this->service->computeComponentGrade(0, 50));

        // Max score 0 returns null (no division by zero)
        $this->assertNull($this->service->computeComponentGrade(10, 0));
    }

    /**
     * Section 2: Compute each component from multiple items.
     * Always combine raw scores first:
     * ComponentGrade = 5 - (4 * (SUM(StudentScores) / SUM(MaximumScores)))
     */
    public function test_compute_component_from_multiple_items_combines_raw_scores_first(): void
    {
        // Example from prompt:
        // Quiz 1 = 18 / 20
        // Quiz 2 = 35 / 40
        // Quiz 3 = 27 / 30
        // Total Earned = 80, Total Maximum = 90
        // Quiz Grade = 5 - (4 * (80 / 90)) = 1.444444...
        $scores = [
            ['score' => 18, 'max_score' => 20],
            ['score' => 35, 'max_score' => 40],
            ['score' => 27, 'max_score' => 30],
        ];

        $result = $this->service->computeComponentPercentage($scores);

        $expectedGrade = 5.0 - (4.0 * (80.0 / 90.0));
        $this->assertEquals(80.0, $result['raw_score']);
        $this->assertEquals(90.0, $result['max_score']);
        $this->assertEqualsWithDelta($expectedGrade, $result['component_grade'], 0.000001);
        $this->assertEquals((80 / 90) * 100, $result['percentage']);
    }

    /**
     * Section 3: Midterm Grade (MD) calculation with weighted component numerical grades.
     * Formula: MD = ROUND(SUM(ComponentGrade * ComponentWeight), 1)
     */
    public function test_midterm_grade_calculation_matches_exact_excel_example(): void
    {
        // Example from prompt:
        // Activities Grade = 1.6451612903 (260/310), Weight = 10%
        // Quiz Grade = 1.5538461538 (112/130), Weight = 50%
        // Exam Grade = 2.44 (32/50), Weight = 40%
        // Project Grade = 1.00 (100/100), Weight = 0%
        // MD = ROUND((1.6451612903 * 0.10) + (1.5538461538 * 0.50) + (2.44 * 0.40) + (1.00 * 0.00), 1)
        // MD = ROUND(1.917439..., 1) = 1.9
        $components = [
            'Activities' => 10,
            'Quiz' => 50,
            'Exam' => 40,
            'Project' => 0,
        ];

        $assessments = [
            'Activities' => [
                ['score' => 260, 'max_score' => 310],
            ],
            'Quiz' => [
                ['score' => 112, 'max_score' => 130],
            ],
            'Exam' => [
                ['score' => 32, 'max_score' => 50],
            ],
            'Project' => [
                ['score' => 100, 'max_score' => 100],
            ],
        ];

        $result = $this->service->computePeriodGrade($components, $assessments);

        $this->assertEquals(1.9, $result['period_grade']);
        $this->assertEquals('1.9', $result['numerical_grade']);
        $this->assertEquals('Passed', $result['remarks']);
        $this->assertTrue($result['is_passing']);
    }

    /**
     * Section 5: Final-period Grade (FD) calculation.
     * FD = ROUND(Σ(FinalComponentGrade * FinalComponentWeight), 1)
     */
    public function test_final_period_grade_matches_exact_excel_example(): void
    {
        // Example from prompt:
        // Component A: 65 / 70 -> Grade = 1.285714..., Weight = 10%
        // Component B: 100 / 100 -> Grade = 1.00, Weight = 50%
        // Final Exam: 45 / 50 -> Grade = 1.40, Weight = 40%
        // Project: 100 / 100 -> Grade = 1.00, Weight = 0%
        // FD = ROUND((1.285714 * 0.10) + (1.00 * 0.50) + (1.40 * 0.40) + (1.00 * 0), 1) = 1.2
        $components = [
            'Component A' => 10,
            'Component B' => 50,
            'Final Exam' => 40,
            'Project' => 0,
        ];

        $assessments = [
            'Component A' => [['score' => 65, 'max_score' => 70]],
            'Component B' => [['score' => 100, 'max_score' => 100]],
            'Final Exam' => [['score' => 45, 'max_score' => 50]],
            'Project' => [['score' => 100, 'max_score' => 100]],
        ];

        $result = $this->service->computePeriodGrade($components, $assessments);

        $this->assertEquals(1.2, $result['period_grade']);
        $this->assertEquals('1.2', $result['numerical_grade']);
        $this->assertEquals('Passed', $result['remarks']);
    }

    /**
     * Section 6 & 7: Overall Final Grade (FG) - Passing student with ROUNDDOWN.
     * Average: (1.9 + 1.2) / 2 = 1.55
     * ROUND(1.55, 1) = 1.6 <= 3.0
     * FG = ROUNDDOWN(1.55, 1) = 1.5
     */
    public function test_overall_final_grade_passing_applies_rounddown(): void
    {
        $fg = $this->service->computeOverallFinalGrade(1.9, 1.2);

        $this->assertEquals(1.55, $fg['average_grade']);
        $this->assertEquals(1.5, $fg['final_grade']);
        $this->assertEquals('1.5', $fg['formatted_final_grade']);
        $this->assertEquals('Passed', $fg['remarks']);
        $this->assertTrue($fg['is_passing']);
    }

    /**
     * Section 8: Overall Final Grade (FG) - Failing student becomes 5.0.
     * MD = 3.2, FD = 3.0
     * Average: (3.2 + 3.0) / 2 = 3.1
     * ROUND(3.1, 1) > 3.0 -> FG = 5.0
     */
    public function test_overall_final_grade_failing_exceeding_threshold_becomes_5_point_0(): void
    {
        $fg = $this->service->computeOverallFinalGrade(3.2, 3.0);

        $this->assertEquals(3.1, $fg['average_grade']);
        $this->assertEquals(5.0, $fg['final_grade']);
        $this->assertEquals('5.0', $fg['formatted_final_grade']);
        $this->assertEquals('Failed', $fg['remarks']);
        $this->assertFalse($fg['is_passing']);
    }

    /**
     * Section 8 & 9: Threshold boundary tests for FG.
     */
    public function test_overall_final_grade_threshold_boundaries(): void
    {
        // Boundary 1: Exactly 3.0 average -> passes with 3.0
        $fg30 = $this->service->computeOverallFinalGrade(3.0, 3.0);
        $this->assertEquals(3.0, $fg30['final_grade']);
        $this->assertEquals('Passed', $fg30['remarks']);
        $this->assertTrue($fg30['is_passing']);

        // Boundary 2: Average 3.04 -> ROUND(3.04, 1) = 3.0 <= 3.0 -> ROUNDDOWN(3.04, 1) = 3.0 (Passed)
        $fg304 = $this->service->computeOverallFinalGrade(3.0, 3.08); // avg = 3.04
        $this->assertEquals(3.0, $fg304['final_grade']);
        $this->assertEquals('Passed', $fg304['remarks']);

        // Boundary 3: Average 3.05 -> ROUND(3.05, 1) = 3.1 > 3.0 -> 5.0 (Failed)
        $fg305 = $this->service->computeOverallFinalGrade(3.0, 3.1); // avg = 3.05
        $this->assertEquals(5.0, $fg305['final_grade']);
        $this->assertEquals('Failed', $fg305['remarks']);
        $this->assertFalse($fg305['is_passing']);
    }

    /**
     * Section 10: Blank / Incomplete Data.
     * MD and FD remain null when required components are incomplete.
     * FG remains null until both MD and FD are available.
     */
    public function test_incomplete_scores_prevent_final_grade_calculation(): void
    {
        $components = [
            'Quiz' => 50,
            'Exam' => 50,
        ];

        $assessmentsWithMissing = [
            'Quiz' => [['score' => 40, 'max_score' => 50]],
            'Exam' => [['score' => null, 'max_score' => 100]], // missing
        ];

        $mdResult = $this->service->computePeriodGrade($components, $assessmentsWithMissing, ['allow_incomplete' => false]);
        $this->assertNull($mdResult['period_grade']);
        $this->assertEquals('—', $mdResult['numerical_grade']);
        $this->assertEquals('INCOMPLETE', $mdResult['remarks']);
        $this->assertFalse($mdResult['is_complete']);

        // FG cannot be calculated when MD is null
        $fgResult = $this->service->computeOverallFinalGrade(null, 1.2);
        $this->assertNull($fgResult['final_grade']);
        $this->assertEquals('—', $fgResult['formatted_final_grade']);
        $this->assertNull($fgResult['remarks']);
    }

    /**
     * Full Term Workflow: computeStudentTermGrades produces MD, FD, and FG together.
     */
    public function test_compute_student_term_grades_end_to_end(): void
    {
        $components = [
            'Activities' => 10,
            'Quiz' => 50,
            'Exam' => 40,
        ];

        // Midterm assessments from prompt (1.9)
        $midtermAssessments = [
            'Activities' => [['score' => 260, 'max_score' => 310]], // 1.64516
            'Quiz' => [['score' => 112, 'max_score' => 130]],       // 1.55385
            'Exam' => [['score' => 32, 'max_score' => 50]],         // 2.44
        ];

        // Final assessments from prompt (1.2)
        $finalAssessments = [
            'Activities' => [['score' => 65, 'max_score' => 70]],   // 1.28571
            'Quiz' => [['score' => 100, 'max_score' => 100]],       // 1.00
            'Exam' => [['score' => 45, 'max_score' => 50]],         // 1.40
        ];

        $termResult = $this->service->computeStudentTermGrades($components, $midtermAssessments, $finalAssessments);

        $this->assertEquals(1.9, $termResult['midterm']['period_grade']);
        $this->assertEquals(1.2, $termResult['final_period']['period_grade']);
        $this->assertEquals(1.55, $termResult['average_grade']);
        $this->assertEquals(1.5, $termResult['final_grade']);
        $this->assertEquals('1.5', $termResult['numerical_grade']);
        $this->assertEquals('Passed', $termResult['remarks']);
        $this->assertTrue($termResult['is_passing']);
        $this->assertTrue($termResult['is_complete']);
    }

    /**
     * Score of 0 is valid recorded score vs null unrecorded.
     */
    public function test_zero_is_valid_recorded_score_and_null_is_unrecorded(): void
    {
        $scores = [
            ['score' => 0, 'max_score' => 20],
            ['score' => null, 'max_score' => 30],
            ['score' => 50, 'max_score' => 50],
        ];

        $result = $this->service->computeComponentPercentage($scores);

        // Earned: 50. Max: 70. Component grade = 5 - (4 * (50/70))
        $this->assertEquals(50.0, $result['raw_score']);
        $this->assertEquals(70.0, $result['max_score']);
        $this->assertEqualsWithDelta(5.0 - (4.0 * (50.0 / 70.0)), $result['component_grade'], 0.00001);
        $this->assertEquals(1, $result['unrecorded_count']);
        $this->assertFalse($result['is_complete']);
    }

    /**
     * Excused or Not Applicable assessments exclude both earned and possible scores.
     */
    public function test_excused_assessments_exclude_both_score_and_max(): void
    {
        $scores = [
            ['score' => 18, 'max_score' => 20],
            ['score' => null, 'max_score' => 30, 'remarks' => 'EXCUSED'],
            ['score' => 40, 'max_score' => 50],
        ];

        $result = $this->service->computeComponentPercentage($scores);

        $this->assertEquals(58.0, $result['raw_score']);
        $this->assertEquals(70.0, $result['max_score']);
        $this->assertEqualsWithDelta(5.0 - (4.0 * (58.0 / 70.0)), $result['component_grade'], 0.00001);
        $this->assertEquals(1, $result['excused_count']);
        $this->assertTrue($result['is_complete']);
    }

    /**
     * Validation: Weights must sum to 100% or 1.00.
     */
    public function test_validation_fails_when_weights_do_not_sum_to_100_or_1(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total component weights must equal exactly 100%');

        $this->service->validateWeights([
            'Quiz' => 30,
            'Exam' => 30,
        ]);
    }
}
