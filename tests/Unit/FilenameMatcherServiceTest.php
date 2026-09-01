<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Services\Autochecker\FilenameMatcherService;
use Tests\TestCase;

class FilenameMatcherServiceTest extends TestCase
{
    public function test_matches_student_by_student_number(): void
    {
        $student1 = new Student([
            'id' => 1,
            'student_number' => '2024-00123',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);
        $student1->id = 1;

        $student2 = new Student([
            'id' => 2,
            'student_number' => '2024-00456',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);
        $student2->id = 2;

        $students = collect([$student1, $student2]);
        $service = new FilenameMatcherService();

        // Exact with hyphen
        $match = $service->matchSingle($students, '2024-00123_Activity1.py');
        $this->assertSame(1, $match['student_id']);
        $this->assertSame('student_number', $match['match_type']);
        $this->assertEquals(1.0, $match['confidence']);

        // Without hyphen in filename
        $match2 = $service->matchSingle($students, 'Activity1_202400456.cpp');
        $this->assertSame(2, $match2['student_id']);
        $this->assertSame('student_number', $match2['match_type']);
    }

    public function test_matches_student_by_full_name(): void
    {
        $student = new Student([
            'id' => 5,
            'student_number' => '2023-99999',
            'first_name' => 'Pedro',
            'last_name' => 'Penduko',
        ]);
        $student->id = 5;

        $students = collect([$student]);
        $service = new FilenameMatcherService();

        $match = $service->matchSingle($students, 'Penduko_Pedro_LabAssignment.pdf');
        $this->assertSame(5, $match['student_id']);
        $this->assertSame('full_name', $match['match_type']);
        $this->assertGreaterThanOrEqual(0.9, $match['confidence']);
    }

    public function test_returns_none_when_unmatched(): void
    {
        $student = new Student([
            'id' => 10,
            'student_number' => '2024-11111',
            'first_name' => 'Alex',
            'last_name' => 'Reyes',
        ]);
        $student->id = 10;

        $students = collect([$student]);
        $service = new FilenameMatcherService();

        $match = $service->matchSingle($students, 'random_unrelated_code.js');
        $this->assertNull($match['student_id']);
        $this->assertSame('none', $match['match_type']);
    }
}
