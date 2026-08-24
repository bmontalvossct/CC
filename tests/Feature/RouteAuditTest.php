<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CourseModule;
use App\Models\LayoutBlock;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\Recitation;
use App\Models\Seat;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RouteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_public_and_guest_routes_render_without_500_errors(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->get('/up')->assertOk();

        // Join route
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);
        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'IT 101',
            'subject_title' => 'Intro to IT',
            'name' => 'BSIT 1-A',
        ]);

        $this->get(route('join.show', $section->enrollment_token))->assertOk();
    }

    public function test_all_authenticated_app_and_section_routes_render_without_500_errors(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'IT 101',
            'subject_title' => 'Intro to IT',
            'name' => 'BSIT 1-A',
        ]);

        $block = LayoutBlock::create([
            'section_id' => $section->id,
            'label' => 'Main Area',
            'block_row' => 1,
            'block_column' => 1,
            'internal_rows' => 2,
            'internal_columns' => 2,
        ]);

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => '2026-0001',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'is_active' => true,
        ]);

        $seat = Seat::create([
            'layout_block_id' => $block->id,
            'student_id' => $student->id,
            'row_number' => 1,
            'column_number' => 1,
            'label' => 'R1-C1',
        ]);

        // Attendance
        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => now()->toDateString(),
            'status' => 'conducted',
            'schedule_type' => 'lecture',
            'starts_at' => '08:00',
            'ends_at' => '09:30',
            'duration_minutes' => 90,
        ]);

        $attRecord = AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        // Recitation
        $recitation = Recitation::create([
            'section_id' => $section->id,
            'student_id' => $student->id,
            'conducted_on' => now()->toDateString(),
            'score' => 9,
            'max_score' => 10,
        ]);

        // Assessment
        $assessmentFile = UploadedFile::fake()->create('quiz.pdf', 100);
        $attachmentPath = $assessmentFile->store('assessments', 'local');
        $assessment = Assessment::create([
            'section_id' => $section->id,
            'title' => 'Quiz 1',
            'type' => 'quiz',
            'max_points' => 50,
            'conducted_on' => now()->toDateString(),
            'attachment_path' => $attachmentPath,
            'attachment_name' => 'quiz.pdf',
        ]);

        AssessmentScore::create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 45,
        ]);

        // Course Module
        $moduleFile = UploadedFile::fake()->create('lecture1.pdf', 200);
        $modulePath = $moduleFile->store("modules/{$section->id}", 'local');
        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => '1',
            'title' => 'Module 1: Overview',
            'file_path' => $modulePath,
            'file_name' => 'lecture1.pdf',
            'file_size' => 204800,
            'file_mime' => 'application/pdf',
            'sort_order' => 1,
        ]);

        // Project
        $projFile = UploadedFile::fake()->create('rubric.pdf', 150);
        $projPath = $projFile->store('projects', 'local');
        $project = Project::create([
            'section_id' => $section->id,
            'title' => 'Final Presentation',
            'type' => 'reporting',
            'format' => 'group',
            'conducted_on' => now()->toDateString(),
            'max_points' => 100,
            'attachment_path' => $projPath,
            'attachment_name' => 'rubric.pdf',
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'group_number' => 1,
            'name' => 'Group 1',
            'topic' => 'AI Applications',
            'description' => 'Detailed AI analysis',
            'score' => 95,
        ]);

        $group->members()->create([
            'student_id' => $student->id,
            'role' => 'Leader',
            'score' => 95,
        ]);

        // Individual Project
        $indProject = Project::create([
            'section_id' => $section->id,
            'title' => 'Individual Report',
            'type' => 'reporting',
            'format' => 'individual',
            'conducted_on' => now()->toDateString(),
            'max_points' => 50,
        ]);

        $indGroup = ProjectGroup::create([
            'project_id' => $indProject->id,
            'group_number' => 1,
            'name' => 'Presenter: Ada Lovelace',
            'topic' => 'Analytical Engine',
            'description' => 'Hardware design',
            'score' => 48,
        ]);

        $indGroup->members()->create([
            'student_id' => $student->id,
            'role' => 'Presenter',
            'score' => 48,
        ]);

        // ACTING AS USER
        $this->actingAs($user);

        // General routes
        $this->get('/dashboard')->assertOk();
        $this->get('/schedule')->assertOk();
        $this->get('/settings')->assertRedirect('/settings/profile');
        $this->get('/settings/profile')->assertOk();
        $this->get('/settings/academic-term')->assertOk();
        $this->get('/settings/password')->assertOk();
        $this->get('/settings/appearance')->assertOk();

        // Sections
        $this->get('/sections')->assertOk();
        $this->get('/sections/create')->assertOk();
        $this->get('/sections/archived')->assertOk();
        $this->get(route('sections.show', $section))->assertOk();
        $this->get(route('sections.edit', $section))->assertOk();
        $this->get(route('sections.roster.print', $section))->assertOk();
        $this->get(route('sections.students.template', $section))->assertOk();

        // Recitation
        $this->get(route('sections.recitation.index', $section))->assertOk();

        // Attendance
        $this->get(route('attendance.sections.index', $section))->assertOk();
        $this->get(route('attendance.sessions.show', $session))->assertOk();

        // Modules
        $this->get(route('sections.modules.index', $section))->assertOk();
        $this->get(route('sections.modules.download', [$section, $module]))->assertOk();

        // Assessments & Gradebook
        $this->get(route('sections.assessments.index', $section))->assertOk();
        $this->get(route('sections.assessments.show', [$section, $assessment]))->assertOk();
        $this->get(route('sections.assessments.attachment', [$section, $assessment]))->assertOk();
        $this->get(route('sections.exports.assessment', [$section, $assessment]))->assertOk();
        $this->get(route('sections.reports.gradebook', $section))->assertOk();
        $this->get(route('sections.reports.gradebook.print', $section))->assertOk();
        $this->get(route('sections.exports.roster', $section))->assertOk();
        $this->get(route('sections.exports.attendance', $section))->assertOk();
        $this->get(route('sections.exports.gradebook', $section))->assertOk();

        // Projects
        $this->get(route('sections.projects.index', $section))->assertOk();
        $this->get(route('sections.projects.show', [$section, $project]))->assertOk();
        $this->get(route('sections.projects.show', [$section, $indProject]))->assertOk();
        $this->get(route('sections.projects.attachment', [$section, $project]))->assertOk();
        $this->get(route('sections.projects.print', [$section, $project]))->assertOk();
        $this->get(route('sections.projects.print', [$section, $indProject]))->assertOk();
        $this->get(route('sections.projects.export', [$section, $project]))->assertOk();
        $this->get(route('sections.projects.export', [$section, $indProject]))->assertOk();
    }
}
