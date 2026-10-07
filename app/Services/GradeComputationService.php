<?php

namespace App\Services;

use InvalidArgumentException;

class GradeComputationService
{
    public const DEFAULT_TRANSMUTATION_TABLE = [
        ['minimum_percentage' => 97.00, 'maximum_percentage' => 100.00, 'grade_equivalent' => '1.00', 'remarks' => 'Passed'],
        ['minimum_percentage' => 94.00, 'maximum_percentage' => 96.99, 'grade_equivalent' => '1.25', 'remarks' => 'Passed'],
        ['minimum_percentage' => 91.00, 'maximum_percentage' => 93.99, 'grade_equivalent' => '1.50', 'remarks' => 'Passed'],
        ['minimum_percentage' => 88.00, 'maximum_percentage' => 90.99, 'grade_equivalent' => '1.75', 'remarks' => 'Passed'],
        ['minimum_percentage' => 85.00, 'maximum_percentage' => 87.99, 'grade_equivalent' => '2.00', 'remarks' => 'Passed'],
        ['minimum_percentage' => 82.00, 'maximum_percentage' => 84.99, 'grade_equivalent' => '2.25', 'remarks' => 'Passed'],
        ['minimum_percentage' => 79.00, 'maximum_percentage' => 81.99, 'grade_equivalent' => '2.50', 'remarks' => 'Passed'],
        ['minimum_percentage' => 76.00, 'maximum_percentage' => 78.99, 'grade_equivalent' => '2.75', 'remarks' => 'Passed'],
        ['minimum_percentage' => 75.00, 'maximum_percentage' => 75.99, 'grade_equivalent' => '3.00', 'remarks' => 'Passed'],
        ['minimum_percentage' => 0.00,  'maximum_percentage' => 74.99, 'grade_equivalent' => '5.00', 'remarks' => 'Failed'],
    ];

    /**
     * Non-component configuration keys that should be filtered out from component weights.
     */
    protected const NON_COMPONENT_KEYS = [
        'passing_rates',
        'reporting_frequency',
        'midterm_weight',
        'final_weight',
        'transmutation_table',
        'equivalency_table',
    ];

    /**
     * Compute a single component's numerical grade directly from score ratio using:
     * Component Grade = 5 - (4 * (TotalEarned / TotalMaximum))
     *
     * Intermediate calculations are kept with full decimal precision (not rounded).
     */
    public function computeComponentGrade(float $totalEarned, float $totalMaximum): ?float
    {
        if ($totalMaximum <= 0.0) {
            return null;
        }

        $scoreRatio = $totalEarned / $totalMaximum;

        return 5.0 - (4.0 * $scoreRatio);
    }

    /**
     * Excel-like ROUNDDOWN: truncates a number down to the specified decimal places.
     */
    public function roundDown(float $value, int $decimals = 1): float
    {
        $factor = 10 ** $decimals;

        return floor(round($value * $factor, 8)) / $factor;
    }

    /**
     * Validate that configured grading component weights sum to exactly 100% (or 1.00).
     *
     * @param  array<string, mixed>  $weights
     *
     * @throws InvalidArgumentException
     */
    public function validateWeights(array $weights): void
    {
        $filtered = $this->filterComponentWeights($weights);

        if (empty($filtered)) {
            throw new InvalidArgumentException('No grading components configured. At least one component is required.');
        }

        $totalWeight = 0.0;
        foreach ($filtered as $component => $weight) {
            $numWeight = (float) $weight;
            if ($numWeight < 0.0) {
                throw new InvalidArgumentException("Component weight for '{$component}' cannot be negative. Got {$numWeight}%.");
            }
            $totalWeight += $numWeight;
        }

        $isHundred = abs($totalWeight - 100.0) <= 0.001;
        $isOne = abs($totalWeight - 1.0) <= 0.001;

        if (! $isHundred && ! $isOne) {
            throw new InvalidArgumentException("Total component weights must equal exactly 100%. Current total: {$totalWeight}%.");
        }
    }

    /**
     * Filter out metadata keys from grading weights.
     *
     * @param  array<string, mixed>  $weights
     * @return array<string, float>
     */
    public function filterComponentWeights(array $weights): array
    {
        $filtered = [];
        foreach ($weights as $key => $value) {
            if (in_array(strtolower((string) $key), self::NON_COMPONENT_KEYS, true)) {
                continue;
            }
            if (is_numeric($value)) {
                $filtered[(string) $key] = (float) $value;
            }
        }

        return $filtered;
    }

    /**
     * Validate individual assessment scores and maximum points.
     *
     * @throws InvalidArgumentException
     */
    public function validateAssessmentScore(float $maxScore, ?float $score = null, bool $allowBonus = false): void
    {
        if ($maxScore <= 0.0) {
            throw new InvalidArgumentException("Maximum score must be greater than 0. Got {$maxScore}.");
        }

        if ($score !== null) {
            if ($score < 0.0) {
                throw new InvalidArgumentException("Student score cannot be lower than 0. Got {$score}.");
            }
            if (! $allowBonus && $score > $maxScore) {
                throw new InvalidArgumentException("Student score ({$score}) cannot exceed maximum score ({$maxScore}) unless bonus points are explicitly allowed.");
            }
        }
    }

    /**
     * Compute a single component from multiple items combining raw scores first:
     * ComponentGrade = 5 - (4 * (SUM(StudentScores) / SUM(MaximumScores)))
     *
     * Excused / Not Applicable assessments are excluded from BOTH earned and possible scores.
     * Null/blank scores are distinguished from 0 (valid score).
     *
     * @param  array<int, array|object>  $scores
     * @param  array<string, mixed>  $options
     * @return array{
     *     raw_score: float,
     *     max_score: float,
     *     score_ratio: ?float,
     *     component_grade: ?float,
     *     formatted_component_grade: string,
     *     percentage: ?float,
     *     assessment_count: int,
     *     valid_count: int,
     *     excused_count: int,
     *     unrecorded_count: int,
     *     is_complete: bool
     * }
     */
    public function computeComponentPercentage(array $scores, array $options = []): array
    {
        $allowBonus = (bool) ($options['allow_bonus'] ?? config('grading.allow_bonus_points', false));
        $absentPolicy = (string) ($options['absent_policy'] ?? config('grading.absent_policy', 'zero'));

        $sumScores = 0.0;
        $sumMaxScores = 0.0;
        $validCount = 0;
        $excusedCount = 0;
        $unrecordedCount = 0;

        foreach ($scores as $item) {
            $score = is_array($item) ? ($item['score'] ?? null) : ($item->score ?? null);
            $maxScore = is_array($item)
                ? (float) ($item['max_score'] ?? $item['max_points'] ?? 100)
                : (float) ($item->max_score ?? $item->max_points ?? 100);
            $remarks = is_array($item) ? ($item['remarks'] ?? '') : ($item->remarks ?? '');
            $status = is_array($item) ? ($item['status'] ?? '') : ($item->status ?? '');
            $isAbsent = is_array($item) ? (! empty($item['is_absent']) || ! empty($item['absent'])) : (! empty($item->is_absent) || ! empty($item->absent));
            $isExcused = is_array($item) ? (! empty($item['is_excused']) || ! empty($item['excused'])) : (! empty($item->is_excused) || ! empty($item->excused));
            $isNotApplicable = is_array($item) ? (! empty($item['is_not_applicable']) || ! empty($item['not_applicable'])) : (! empty($item->is_not_applicable) || ! empty($item->not_applicable));

            $remarksStr = is_string($remarks) ? strtolower(trim($remarks)) : '';
            $statusStr = is_string($status) ? strtolower(trim($status)) : '';

            // 1. Excused or Not Applicable: Exclude both earned score and max score from computation
            if ($isExcused || $isNotApplicable
                || in_array($statusStr, ['excused', 'not_applicable', 'not applicable', 'n/a'], true)
                || preg_match('/\b(excused|not_applicable|not applicable|n\/a)\b/i', $remarksStr)
            ) {
                $excusedCount++;

                continue;
            }

            $this->validateAssessmentScore($maxScore, $score !== null ? (float) $score : null, $allowBonus);

            // 2. Score is NULL (Unrecorded)
            if ($score === null || $score === '') {
                if ($isAbsent || $statusStr === 'absent' || preg_match('/\babsent\b/i', $remarksStr)) {
                    if ($absentPolicy === 'zero') {
                        $sumScores += 0.0;
                        $sumMaxScores += $maxScore;
                        $validCount++;

                        continue;
                    }
                }

                $unrecordedCount++;

                continue;
            }

            // 3. Score is recorded (0 is a valid score)
            $numericScore = (float) $score;
            $sumScores += $numericScore;
            $sumMaxScores += $maxScore;
            $validCount++;
        }

        $scoreRatio = $sumMaxScores > 0.0 ? ($sumScores / $sumMaxScores) : null;
        $componentGrade = $scoreRatio !== null ? (5.0 - (4.0 * $scoreRatio)) : null;
        $percentage = $scoreRatio !== null ? ($scoreRatio * 100.0) : null;

        return [
            'raw_score' => $sumScores,
            'max_score' => $sumMaxScores,
            'score_ratio' => $scoreRatio,
            'component_grade' => $componentGrade,
            'formatted_component_grade' => $componentGrade !== null ? number_format($componentGrade, 4) : '—',
            'percentage' => $percentage,
            'assessment_count' => count($scores),
            'valid_count' => $validCount,
            'excused_count' => $excusedCount,
            'unrecorded_count' => $unrecordedCount,
            'is_complete' => ($unrecordedCount === 0),
        ];
    }

    /**
     * Compute a period grade (Midterm Grade MD or Final-period Grade FD) using the Excel formula:
     * MD / FD = ROUND(Σ(ComponentGrade_i * ComponentWeight_i), 1)
     *
     * Intermediate component numerical grades are kept with full decimal precision.
     *
     * @param  array<string, float|int>  $components
     * @param  array<string, array<int, mixed>>  $assessmentsGroupedByComponent
     * @param  array<string, mixed>  $options
     */
    public function computePeriodGrade(
        array $components,
        array $assessmentsGroupedByComponent,
        array $options = []
    ): array {
        $validateWeights = (bool) ($options['validate_weights'] ?? true);
        $allowIncomplete = (bool) ($options['allow_incomplete'] ?? config('grading.allow_incomplete_computation', false));

        $componentWeights = $this->filterComponentWeights($components);

        if ($validateWeights) {
            $this->validateWeights($componentWeights);
        }

        $rawTotalWeight = array_sum($componentWeights);
        $isPercentageWeights = $rawTotalWeight > 1.5;

        $componentsResult = [];
        $sumOfWeightedGrades = 0.0;
        $sumEvaluatedDecimalWeights = 0.0;
        $totalWeight = 0.0;
        $hasEvaluatedComponent = false;
        $isAllComponentsComplete = true;

        foreach ($componentWeights as $componentName => $weight) {
            $numWeight = (float) $weight;
            $decimalWeight = $isPercentageWeights ? ($numWeight / 100.0) : $numWeight;
            $totalWeight += $numWeight;

            $items = $this->findItemsForComponent($componentName, $assessmentsGroupedByComponent);
            $calc = $this->computeComponentPercentage($items, $options);

            if (! $calc['is_complete'] || empty($items)) {
                $isAllComponentsComplete = false;
            }

            $compGrade = $calc['component_grade']; // full precision
            $weightedGrade = ($compGrade !== null && $decimalWeight > 0.0)
                ? ($compGrade * $decimalWeight)
                : 0.0;

            if ($compGrade !== null && $decimalWeight > 0.0) {
                $sumOfWeightedGrades += $weightedGrade;
                $sumEvaluatedDecimalWeights += $decimalWeight;
                $hasEvaluatedComponent = true;
            }

            $componentsResult[$componentName] = [
                'component' => (string) $componentName,
                'weight' => $numWeight,
                'decimal_weight' => $decimalWeight,
                'raw_score' => $calc['raw_score'],
                'max_score' => $calc['max_score'],
                'score_ratio' => $calc['score_ratio'],
                'component_grade' => $compGrade,
                'formatted_component_grade' => $compGrade !== null ? number_format($compGrade, 4) : '—',
                'weighted_score' => $weightedGrade,
                'formatted_weighted_score' => number_format($weightedGrade, 4),
                'percentage' => $calc['percentage'],
                'formatted_percentage' => $calc['percentage'] !== null ? number_format($calc['percentage'], 2).'%' : '—',
                'assessment_count' => $calc['assessment_count'],
                'excused_count' => $calc['excused_count'],
                'unrecorded_count' => $calc['unrecorded_count'],
                'is_complete' => $calc['is_complete'],
            ];
        }

        // Section 10: If required components are incomplete, MD / FD should remain uncomputed/null
        if (! $allowIncomplete && ! $isAllComponentsComplete) {
            return [
                'components' => $componentsResult,
                'total_weight' => $totalWeight,
                'period_grade' => null,
                'numerical_grade' => '—',
                'scale_grade' => '—',
                'final_percentage' => null,
                'remarks' => 'INCOMPLETE',
                'is_complete' => false,
                'is_passing' => null,
            ];
        }

        if (! $hasEvaluatedComponent) {
            return [
                'components' => $componentsResult,
                'total_weight' => $totalWeight,
                'period_grade' => null,
                'numerical_grade' => '—',
                'scale_grade' => '—',
                'final_percentage' => null,
                'remarks' => 'NO SCORES',
                'is_complete' => $isAllComponentsComplete,
                'is_passing' => null,
            ];
        }

        $weightedSum = $sumEvaluatedDecimalWeights > 0.0
            ? ($sumOfWeightedGrades / ($allowIncomplete && $sumEvaluatedDecimalWeights < 1.0 ? $sumEvaluatedDecimalWeights : 1.0))
            : 0.0;

        $periodGrade = round($weightedSum, 1, PHP_ROUND_HALF_UP);
        $formattedGrade = number_format($periodGrade, 1);
        $remarks = $periodGrade <= 3.0 ? 'Passed' : 'Failed';

        return [
            'components' => $componentsResult,
            'total_weight' => $totalWeight,
            'period_grade' => $periodGrade,
            'numerical_grade' => $formattedGrade,
            'scale_grade' => $formattedGrade,
            'final_percentage' => $periodGrade,
            'remarks' => $remarks,
            'is_complete' => $isAllComponentsComplete,
            'is_passing' => ($periodGrade <= 3.0),
        ];
    }

    /**
     * Compute Overall Final Grade (FG) and Remarks from Midterm Grade (MD) and Final-period Grade (FD).
     *
     * AverageGrade = (MD + FD) / 2
     *
     * IF ROUND((MD + FD) / 2, 1) > 3.0
     *     FG = 5.0
     * ELSE
     *     FG = ROUNDDOWN((MD + FD) / 2, 1)
     *
     * Remarks:
     * IF FG <= 3.0: Remarks = "Passed"
     * IF FG == 5.0: Remarks = "Failed"
     *
     * @return array{
     *     midterm_grade: ?float,
     *     final_period_grade: ?float,
     *     average_grade: ?float,
     *     final_grade: ?float,
     *     formatted_final_grade: string,
     *     remarks: ?string,
     *     is_passing: ?bool,
     *     is_complete: bool
     * }
     */
    public function computeOverallFinalGrade(?float $midtermGrade, ?float $finalPeriodGrade): array
    {
        if ($midtermGrade === null || $finalPeriodGrade === null) {
            return [
                'midterm_grade' => $midtermGrade,
                'final_period_grade' => $finalPeriodGrade,
                'average_grade' => null,
                'final_grade' => null,
                'formatted_final_grade' => '—',
                'remarks' => null,
                'is_passing' => null,
                'is_complete' => false,
            ];
        }

        $averageGrade = round(($midtermGrade + $finalPeriodGrade) / 2.0, 8);
        $roundedAverage = round($averageGrade, 1, PHP_ROUND_HALF_UP);

        if ($roundedAverage > 3.0) {
            $finalGrade = 5.0;
        } else {
            $finalGrade = $this->roundDown($averageGrade, 1);
        }

        $isPassing = $finalGrade <= 3.0;
        $remarks = $isPassing ? 'Passed' : 'Failed';

        return [
            'midterm_grade' => $midtermGrade,
            'final_period_grade' => $finalPeriodGrade,
            'average_grade' => $averageGrade,
            'final_grade' => $finalGrade,
            'formatted_final_grade' => number_format($finalGrade, 1),
            'remarks' => $remarks,
            'is_passing' => $isPassing,
            'is_complete' => true,
        ];
    }

    /**
     * Dynamically compute student grade across arbitrary user-defined grading components.
     * Follows the exact Excel grading calculation system.
     */
    public function computeStudentGrade(
        array $components,
        array $assessmentsGroupedByComponent,
        array $options = []
    ): array {
        return $this->computePeriodGrade($components, $assessmentsGroupedByComponent, $options);
    }

    /**
     * Compute full term grades (Midterm MD, Final FD, and Overall Final Grade FG).
     */
    public function computeStudentTermGrades(
        array $components,
        array $midtermGrouped,
        array $finalGrouped,
        array $options = []
    ): array {
        $md = $this->computePeriodGrade($components, $midtermGrouped, $options);
        $fd = $this->computePeriodGrade($components, $finalGrouped, $options);

        $fg = $this->computeOverallFinalGrade($md['period_grade'], $fd['period_grade']);

        return [
            'midterm' => $md,
            'final_period' => $fd,
            'average_grade' => $fg['average_grade'],
            'final_grade' => $fg['final_grade'],
            'numerical_grade' => $fg['formatted_final_grade'],
            'scale_grade' => $fg['formatted_final_grade'],
            'remarks' => $fg['remarks'] ?? ($md['is_complete'] && $fd['is_complete'] ? 'NO SCORES' : 'INCOMPLETE'),
            'is_passing' => $fg['is_passing'],
            'is_complete' => $fg['is_complete'],
        ];
    }

    /**
     * Lookup numerical grade equivalent and remarks from optional custom transmutation table.
     */
    public function lookupGradeEquivalent(float $finalPercentage, ?array $transmutationTable = null): array
    {
        $table = $transmutationTable ?? config('grading.default_transmutation_table', self::DEFAULT_TRANSMUTATION_TABLE);
        if (empty($table) || ! is_array($table)) {
            $table = self::DEFAULT_TRANSMUTATION_TABLE;
        }

        usort($table, fn ($a, $b) => ((float) ($b['minimum_percentage'] ?? 0)) <=> ((float) ($a['minimum_percentage'] ?? 0)));

        $roundedPct = round($finalPercentage, 2);

        foreach ($table as $entry) {
            $min = (float) ($entry['minimum_percentage'] ?? 0);
            $max = (float) ($entry['maximum_percentage'] ?? 100);

            if ($roundedPct >= $min && $roundedPct <= $max) {
                return [
                    'grade_equivalent' => (string) $entry['grade_equivalent'],
                    'remarks' => (string) ($entry['remarks'] ?? ($roundedPct >= 75.0 ? 'Passed' : 'Failed')),
                    'minimum_percentage' => $min,
                    'maximum_percentage' => $max,
                ];
            }
        }

        if ($roundedPct > 100.0 && ! empty($table)) {
            $top = $table[0];

            return [
                'grade_equivalent' => (string) $top['grade_equivalent'],
                'remarks' => (string) ($top['remarks'] ?? 'Passed'),
                'minimum_percentage' => (float) ($top['minimum_percentage'] ?? 97),
                'maximum_percentage' => (float) ($top['maximum_percentage'] ?? 100),
            ];
        }

        if (! empty($table)) {
            $bottom = end($table);

            return [
                'grade_equivalent' => (string) $bottom['grade_equivalent'],
                'remarks' => (string) ($bottom['remarks'] ?? 'Failed'),
                'minimum_percentage' => (float) ($bottom['minimum_percentage'] ?? 0),
                'maximum_percentage' => (float) ($bottom['maximum_percentage'] ?? 74.99),
            ];
        }

        return [
            'grade_equivalent' => $roundedPct >= 75.0 ? '3.00' : '5.00',
            'remarks' => $roundedPct >= 75.0 ? 'Passed' : 'Failed',
            'minimum_percentage' => 0.0,
            'maximum_percentage' => 100.0,
        ];
    }

    /**
     * Find matching items in array using case-insensitive and normalized key matching.
     */
    protected function findItemsForComponent(string $componentName, array $assessmentsGroupedByComponent): array
    {
        if (isset($assessmentsGroupedByComponent[$componentName])) {
            return (array) $assessmentsGroupedByComponent[$componentName];
        }

        $needle = strtolower(trim(str_replace(['_', '-'], ' ', $componentName)));
        foreach ($assessmentsGroupedByComponent as $key => $items) {
            $candidate = strtolower(trim(str_replace(['_', '-'], ' ', (string) $key)));
            if ($candidate === $needle) {
                return (array) $items;
            }
        }

        return [];
    }
}
