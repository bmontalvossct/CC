<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassroomFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_index_provides_day_of_week_trends(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Sem',
            'school_year' => '2026-2027',
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonths(4),
            'is_current' => true,
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'name' => 'Section Beta',
            'subject_code' => 'CS102',
            'subject_title' => 'Data Structures',
            'is_active' => true,
        ]);

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => '2023-0001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'is_active' => true,
        ]);

        // Create attendance on a Monday (2026-08-24 is a Monday)
        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => '2026-08-24',
            'starts_at' => '09:00',
            'ends_at' => '10:30',
            'duration_minutes' => 90,
        ]);

        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
            'attended_minutes' => 90,
        ]);

        $response = $this->actingAs($user)->get("/sections/{$section->id}/attendance");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('attendance/Index')
            ->has('day_of_week_trends', 7)
            ->where('day_of_week_trends.0.day', 'Mon')
            ->where('day_of_week_trends.0.sessions', 1)
            ->where('day_of_week_trends.0.present', 1)
            ->where('day_of_week_trends.0.attendance_rate', 100)
        );
    }

    public function test_teacher_can_bulk_import_student_photos_via_zip(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Sem',
            'school_year' => '2026-2027',
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonths(4),
            'is_current' => true,
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'name' => 'Section Gamma',
            'subject_code' => 'CS103',
            'subject_title' => 'Web Development',
            'is_active' => true,
        ]);

        $student1 = Student::create([
            'section_id' => $section->id,
            'student_number' => '2023-0101',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'is_active' => true,
        ]);

        $student2 = Student::create([
            'section_id' => $section->id,
            'student_number' => '2023-0102',
            'first_name' => 'Ben',
            'last_name' => 'Cruz',
            'is_active' => true,
        ]);

        // Create temporary zip archive
        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Add 1x1 dummy image data
        $dummyImage = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $zip->addFromString('2023-0101.jpg', $dummyImage);
        $zip->addFromString('cruzben.png', $dummyImage);
        $zip->addFromString('unmatched_9999.jpg', $dummyImage);
        $zip->close();

        $uploadedFile = new UploadedFile($zipPath, 'photos.zip', 'application/zip', null, true);

        $response = $this->actingAs($user)->post("/sections/{$section->id}/photos-import", [
            'photos_zip' => $uploadedFile,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $student1->refresh();
        $student2->refresh();

        $this->assertNotNull($student1->photo_path);
        $this->assertNotNull($student2->photo_path);
        $this->assertTrue(Storage::disk('local')->exists($student1->photo_path));
        $this->assertTrue(Storage::disk('local')->exists($student2->photo_path));

        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }
    }

    public function test_unauthorized_user_cannot_import_photos(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $term = AcademicTerm::create([
            'user_id' => $user1->id,
            'name' => '1st Sem',
            'school_year' => '2026-2027',
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonths(4),
            'is_current' => true,
        ]);

        $section = Section::create([
            'user_id' => $user1->id,
            'academic_term_id' => $term->id,
            'name' => 'Section Delta',
            'subject_code' => 'CS104',
            'subject_title' => 'Algorithms',
            'is_active' => true,
        ]);

        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('dummy.jpg', 'fake image');
        $zip->close();

        $uploadedFile = new UploadedFile($zipPath, 'photos.zip', 'application/zip', null, true);

        $response = $this->actingAs($user2)->post("/sections/{$section->id}/photos-import", [
            'photos_zip' => $uploadedFile,
        ]);

        $response->assertForbidden();

        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }
    }
}
