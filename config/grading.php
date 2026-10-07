<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Grade Equivalency / Transmutation Table
    |--------------------------------------------------------------------------
    |
    | System-configurable transmutation table mapping rounded final percentage
    | grades to university numerical grades (e.g. 1.00 - 5.00) and remarks.
    |
    | Each record contains:
    |   - minimum_percentage: inclusive lower bound
    |   - maximum_percentage: inclusive upper bound
    |   - grade_equivalent: university numerical grade (e.g. '1.00', '1.25')
    |   - remarks: e.g. 'PASSED' or 'FAILED'
    |
    */
    'default_transmutation_table' => [
        [
            'minimum_percentage' => 97.00,
            'maximum_percentage' => 100.00,
            'grade_equivalent' => '1.00',
            'remarks' => 'PASSED',
        ],
        [
            'minimum_percentage' => 94.00,
            'maximum_percentage' => 96.99,
            'grade_equivalent' => '1.25',
            'remarks' => 'PASSED',
        ],
        [
            'minimum_percentage' => 91.00,
            'maximum_percentage' => 93.99,
            'grade_equivalent' => '1.50',
            'remarks' => 'PASSED',
        ],
        [
            'minimum_percentage' => 88.00,
            'maximum_percentage' => 90.99,
            'grade_equivalent' => '1.75',
            'remarks' => 'PASSED',
        ],
        [
            'minimum_percentage' => 85.00,
            'maximum_percentage' => 87.99,
            'grade_equivalent' => '2.00',
            'remarks' => 'PASSED',
        ],
        [
            'minimum_percentage' => 82.00,
            'maximum_percentage' => 84.99,
            'grade_equivalent' => '2.25',
            'remarks' => 'PASSED',
        ],
        [
            'minimum_percentage' => 79.00,
            'maximum_percentage' => 81.99,
            'grade_equivalent' => '2.50',
            'remarks' => 'PASSED',
        ],
        [
            'minimum_percentage' => 76.00,
            'maximum_percentage' => 78.99,
            'grade_equivalent' => '2.75',
            'remarks' => 'PASSED',
        ],
        [
            'minimum_percentage' => 75.00,
            'maximum_percentage' => 75.99,
            'grade_equivalent' => '3.00',
            'remarks' => 'PASSED',
        ],
        [
            'minimum_percentage' => 0.00,
            'maximum_percentage' => 74.99,
            'grade_equivalent' => '5.00',
            'remarks' => 'FAILED',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Grading Policies
    |--------------------------------------------------------------------------
    */
    'absent_policy' => 'zero', // 'zero' or 'unrecorded'
    'allow_incomplete_computation' => false,
    'allow_bonus_points' => false,
];
