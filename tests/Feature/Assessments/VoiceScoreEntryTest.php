<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\GradebookCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceScoreEntryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create(['user_id' => $user->id, 'name' => 'Semester', 'school_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-20']);
        $section = Section::create(['user_id' => $user->id, 'academic_term_id' => $term->id, 'subject_code' => 'MATH', 'subject_title' => 'Mathematics', 'name' => 'A']);
        $student = Student::create(['section_id' => $section->id, 'student_number' => '001', 'first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $session = AttendanceSession::create(['section_id' => $section->id, 'session_date' => '2026-08-10', 'starts_at' => '08:00', 'ends_at' => '09:00', 'duration_minutes' => 60]);
        $attendance = AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'attended_minutes' => 60]);
        $assessment = Assessment::create(['section_id' => $section->id, 'attendance_session_id' => $session->id, 'type' => 'activity', 'title' => 'Activity 2', 'conducted_on' => '2026-08-10', 'max_points' => 100]);
        $url = route('sections.assessments.scores.update', [$section, $assessment, $student]);

        return [$user, $section, $student, $assessment, $attendance, $url];
    }

    public function test_activity_absence_persists_and_recalculates_without_changing_attendance(): void
    {
        [$user, $section, $student, $assessment, $attendance, $url] = $this->fixture();
        app(GradebookCalculationService::class)->calculateGradebook($section);
        $this->actingAs($user)->patchJson($url, ['score' => null, 'mark_absent' => true, 'remarks' => 'Class presentation'])
            ->assertOk()->assertJsonPath('marked_absent', true)->assertJsonPath('score', null);
        $this->assertSame('present', $attendance->fresh()->status);
        $this->assertSame(60, $attendance->fresh()->attended_minutes);
        $this->get(route('sections.assessments.show', [$section, $assessment]))
            ->assertInertia(fn ($page) => $page->where('students.0.marked_absent', true)->where('summary.absent', 1)->where('summary.missing', 0));
        $data = app(GradebookCalculationService::class)->calculateGradebook($section);
        $this->assertTrue($data['rows'][0]['score_absences'][$assessment->id]);
        $this->assertSame(0, $data['rows'][0]['categories']['activity']['missing']);
        $this->assertStringContainsString('Absent', $this->get(route('sections.exports.assessment', [$section, $assessment]))->streamedContent());
        $this->assertStringContainsString('Absent', $this->get(route('sections.exports.gradebook', $section))->streamedContent());
        $this->patchJson($url, ['score' => 0, 'mark_absent' => false, 'remarks' => 'Class presentation'])->assertOk()->assertJsonPath('marked_absent', false)->assertJsonPath('score', '0.00');
        $this->assertSame('Class presentation', $assessment->scores()->firstOrFail()->remarks);
    }

    public function test_activity_absence_does_not_bypass_ownership_or_accept_a_simultaneous_score(): void
    {
        [$user, , , $assessment, , $url] = $this->fixture();
        $this->actingAs($user)->patchJson($url, ['score' => 20, 'mark_absent' => true])->assertUnprocessable();
        $other = User::factory()->create();
        $this->actingAs($other)->patchJson($url, ['score' => null, 'mark_absent' => true])->assertForbidden();
        $this->assertSame(0, $assessment->scores()->count());
    }

    public function test_batch_save_preserves_absence_for_older_clients_and_clears_it_for_a_numeric_score(): void
    {
        [$user, $section, $student, $assessment, , $url] = $this->fixture();
        $this->actingAs($user)->patchJson($url, ['score' => null, 'mark_absent' => true])->assertOk();
        $batch = route('sections.assessments.scores.batch', [$section, $assessment]);
        $this->postJson($batch, ['scores' => [$student->id => null]])->assertOk();
        $this->assertTrue($assessment->scores()->firstOrFail()->marked_absent);
        $this->postJson($batch, ['scores' => [$student->id => 8]])->assertOk();
        $this->assertFalse($assessment->scores()->firstOrFail()->marked_absent);
        $this->postJson($batch, ['scores' => [$student->id => 10], 'marked_absent' => [$student->id => true]])->assertUnprocessable();
        $this->assertSame('8.00', $assessment->scores()->firstOrFail()->score);
    }

    public function test_marking_activity_absent_is_allowed_for_an_absent_attendance_record_but_scoring_still_needs_override(): void
    {
        [$user, , , , $attendance, $url] = $this->fixture();
        $attendance->update(['status' => 'absent', 'attended_minutes' => 0]);
        $this->actingAs($user)->patchJson($url, ['score' => null, 'mark_absent' => true])->assertOk();
        $this->patchJson($url, ['score' => 9])->assertUnprocessable();
        $this->assertSame('absent', $attendance->fresh()->status);
    }
}
