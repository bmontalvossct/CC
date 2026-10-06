<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ExamDocxExportTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->teacher = User::factory()->create();

        $term = AcademicTerm::create([
            'user_id' => $this->teacher->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-31',
            'is_current' => true,
        ]);

        $this->section = Section::create([
            'user_id' => $this->teacher->id,
            'academic_term_id' => $term->id,
            'name' => 'BSCS 3A',
            'subject_code' => 'CS 301',
            'subject_title' => 'Software Engineering',
        ]);
    }

    public function test_unauthorized_user_cannot_export_exam_to_docx(): void
    {
        $otherTeacher = User::factory()->create();

        $response = $this->actingAs($otherTeacher)
            ->postJson(route('sections.exam-generator.export-docx', $this->section), [
                'title' => 'Midterm Exam',
                'exam_content' => 'Sample exam content',
            ]);

        $response->assertStatus(403);
    }

    public function test_export_validates_required_fields(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson(route('sections.exam-generator.export-docx', $this->section), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'exam_content']);
    }

    public function test_teacher_can_export_student_exam_to_docx(): void
    {
        $sampleExam = <<<'MD'
**Part I: Identification (5 items - 1 pt each = 5 pts)**
1. A software development framework emphasizing iterative progress and flexible response to change.
   Answer: ____________________

**Part V: Multiple Choice (5 items - 1 pt each = 5 pts)**
1. Which ceremony occurs at the end of an Agile sprint?
   A) Daily Standup
   B) Sprint Retrospective
   C) Sprint Planning
   D) Backlog Refinement
MD;

        $response = $this->actingAs($this->teacher)
            ->post(route('sections.exam-generator.export-docx', $this->section), [
                'title' => 'CS 301 - Midterm Examination',
                'exam_content' => $sampleExam,
                'max_points' => 50,
                'mode' => 'student',
            ]);

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('content-disposition', 'attachment; filename=cs-301-midterm-examination-student-questionnaire.docx');

        // Verify response body is a valid DOCX (Zip archive containing word/document.xml)
        $binaryContent = $response->streamedContent();
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_test_');
        file_put_contents($tempFile, $binaryContent);

        $zip = new ZipArchive;
        $opened = $zip->open($tempFile);
        $this->assertTrue($opened === true, 'Response is not a valid zip archive');
        $this->assertNotEmpty($zip->getFromName('word/document.xml'), 'DOCX must contain word/document.xml');
        $zip->close();
        @unlink($tempFile);
    }

    public function test_teacher_can_export_exam_with_answer_key(): void
    {
        $sampleExam = '1. Question 1 stem';
        $answerKey = '1. Answer 1 explanation';

        $response = $this->actingAs($this->teacher)
            ->post(route('sections.exam-generator.export-docx', $this->section), [
                'title' => 'CS 301 - Midterm Examination',
                'exam_content' => $sampleExam,
                'answer_key' => $answerKey,
                'max_points' => 60,
                'mode' => 'both',
            ]);

        $response->assertStatus(200)
            ->assertHeader('content-disposition', 'attachment; filename=cs-301-midterm-examination-with-answer-key.docx');

        $binaryContent = $response->streamedContent();
        $this->assertGreaterThan(5000, strlen($binaryContent));
    }

    public function test_teacher_can_export_answer_key_only(): void
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('sections.exam-generator.export-docx', $this->section), [
                'title' => 'CS 301 - Midterm Examination',
                'exam_content' => 'ignored in answers mode',
                'answer_key' => "### Part I Answers\n1. Agile Methodology",
                'max_points' => 60,
                'mode' => 'answers',
            ]);

        $response->assertStatus(200)
            ->assertHeader('content-disposition', 'attachment; filename=cs-301-midterm-examination-answer-key.docx');

        $binaryContent = $response->streamedContent();
        $this->assertGreaterThan(5000, strlen($binaryContent));
    }
}
