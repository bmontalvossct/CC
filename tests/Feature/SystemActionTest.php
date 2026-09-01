<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_request_open_file_location(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester 2026-2027',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-31',
            'is_current' => true,
        ]);
        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'name' => 'BSIT 4A',
            'subject_code' => 'IT 413',
            'subject_title' => 'Information Assurance',
        ]);

        $file = UploadedFile::fake()->create('lab1_sample.json', 10, 'application/json');
        $path = $file->store("assessments/{$section->id}", 'local');

        $assessment = Assessment::create([
            'section_id' => $section->id,
            'title' => 'Lab 1',
            'type' => 'laboratory',
            'max_points' => 100,
            'conducted_on' => now(),
            'attachment_path' => $path,
            'attachment_name' => 'lab1_sample.json',
        ]);

        $response = $this->actingAs($user)->postJson('/system/open-file-location', [
            'file_url' => "/sections/{$section->id}/assessments/{$assessment->id}/attachment",
            'file_name' => 'lab1_sample.json',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'message']);
    }
}
