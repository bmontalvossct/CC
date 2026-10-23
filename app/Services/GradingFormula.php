<?php

namespace App\Services;

class GradingFormula
{
    public const DEFAULTS = [
        'best_grade' => 1.0,
        'worst_grade' => 5.0,
        'passing_grade' => 3.0,
        'failed_grade' => 5.0,
        'midterm_weight' => 50,
        'final_weight' => 50,
        'decimal_places' => 1,
        'period_rounding' => 'nearest',
        'final_rounding' => 'down',
        'replace_failing_grade' => true,
        'recitation_mode' => 'bonus',
        'reporting_frequency' => 'once_per_sem',
        'weights' => ['activity' => 20, 'laboratory' => 0, 'quiz' => 20, 'exam' => 25, 'project' => 20, 'attendance' => 15, 'recitation' => 5],
        'passing_rates' => ['activity' => 50, 'quiz' => 50, 'exam' => 50, 'project' => 50],
    ];

    public static function resolve(?array $settings = null): array
    {
        return array_replace_recursive(self::DEFAULTS, $settings ?? []);
    }

    public static function component(float $earned, float $possible, array $formula): ?float
    {
        if ($possible <= 0) {
            return null;
        }

        return $formula['worst_grade'] - (($formula['worst_grade'] - $formula['best_grade']) * ($earned / $possible));
    }

    public static function rounded(float $value, string $mode, int $decimals): float
    {
        $factor = 10 ** $decimals;

        return match ($mode) {
            'down' => floor(round($value * $factor, 8)) / $factor,
            'up' => ceil(round($value * $factor, 8)) / $factor,
            default => round($value, $decimals, PHP_ROUND_HALF_UP),
        };
    }
}
