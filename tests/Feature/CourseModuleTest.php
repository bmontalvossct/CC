<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\CourseModule;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourseModuleTest extends TestCase
{
    use RefreshDatabase;

    private function createSection(User $teacher): Section
    {
        $term = AcademicTerm::create([
            'user_id' => $teacher->id,
            'name' => 'First Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-12-18',
        ]);

        return Section::create([
            'user_id' => $teacher->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS101',
            'subject_title' => 'Introduction to Computing',
            'name' => 'Section 1A',
        ]);
    }

    public function test_teacher_can_view_modules_index_page(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 1',
            'title' => 'Computer Architecture Basics',
            'description' => 'CPU, Memory, and I/O overview.',
            'link_url' => 'https://docs.google.com/presentation/d/example123',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($teacher)->get(route('sections.modules.index', $section));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('modules/Index')
            ->has('section')
            ->has('modules', 1)
            ->where('modules.0.module_number', 'Module 1')
            ->where('modules.0.title', 'Computer Architecture Basics')
            ->where('modules.0.link_url', 'https://docs.google.com/presentation/d/example123')
        );
    }

    public function test_teacher_can_create_module_with_presentation_link(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $response = $this->actingAs($teacher)->post(route('sections.modules.store', $section), [
            'module_number' => 'Module 1',
            'title' => 'Introduction to Cloud Computing',
            'description' => 'Overview of cloud services and models.',
            'link_url' => 'https://docs.google.com/presentation/d/slides-abc-123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('course_modules', [
            'section_id' => $section->id,
            'module_number' => 'Module 1',
            'title' => 'Introduction to Cloud Computing',
            'link_url' => 'https://docs.google.com/presentation/d/slides-abc-123',
        ]);
    }

    public function test_teacher_can_create_module_with_file_upload_up_to_50mb(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $file = UploadedFile::fake()->create('lecture-01-slides.pptx', 15000, 'application/vnd.openxmlformats-officedocument.presentationml.presentation');

        $response = $this->actingAs($teacher)->post(route('sections.modules.store', $section), [
            'module_number' => 'Module 2',
            'title' => 'Operating Systems & Processes',
            'file' => $file,
        ]);

        $response->assertRedirect();

        $module = CourseModule::first();
        $this->assertNotNull($module);
        $this->assertSame('Module 2', $module->module_number);
        $this->assertSame('lecture-01-slides.pptx', $module->file_name);
        $this->assertNotNull($module->file_path);
        Storage::disk('local')->assertExists($module->file_path);
    }

    public function test_file_upload_rejects_files_exceeding_50mb(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        // 52 MB file (52 * 1024 = 53248 KB)
        $file = UploadedFile::fake()->create('huge-slides.zip', 53248);

        $response = $this->actingAs($teacher)->post(route('sections.modules.store', $section), [
            'module_number' => 'Module 3',
            'title' => 'Large Presentation',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, CourseModule::count());
    }

    public function test_teacher_can_update_module_and_replace_file(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $oldFile = UploadedFile::fake()->create('old-slides.pdf', 1000, 'application/pdf');
        $oldPath = $oldFile->store("classcheck/modules/{$section->id}", 'local');

        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 1',
            'title' => 'Original Title',
            'file_path' => $oldPath,
            'file_name' => 'old-slides.pdf',
            'file_size' => 1000 * 1024,
            'file_mime' => 'application/pdf',
        ]);

        Storage::disk('local')->assertExists($oldPath);

        $newFile = UploadedFile::fake()->create('new-slides.pptx', 2000);

        $response = $this->actingAs($teacher)->post(route('sections.modules.update', [$section, $module]), [
            'module_number' => 'Module 01 (Revised)',
            'title' => 'Updated Title',
            'link_url' => 'https://canva.com/design/example',
            'file' => $newFile,
        ]);

        $response->assertRedirect();
        $module->refresh();

        $this->assertSame('Module 01 (Revised)', $module->module_number);
        $this->assertSame('Updated Title', $module->title);
        $this->assertSame('https://canva.com/design/example', $module->link_url);
        $this->assertSame('new-slides.pptx', $module->file_name);

        // Old file deleted, new file exists
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($module->file_path);
    }

    public function test_teacher_can_download_module_file(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $file = UploadedFile::fake()->create('module-notes.pdf', 500, 'application/pdf');
        $path = $file->store("classcheck/modules/{$section->id}", 'local');

        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 1',
            'title' => 'Lecture Notes',
            'file_path' => $path,
            'file_name' => 'module-notes.pdf',
            'file_size' => 500 * 1024,
            'file_mime' => 'application/pdf',
        ]);

        $response = $this->actingAs($teacher)->get(route('sections.modules.download', [$section, $module]));
        $response->assertOk();
    }

    public function test_teacher_can_delete_module_and_clean_up_storage(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $file = UploadedFile::fake()->create('module-cleanup.pdf', 500, 'application/pdf');
        $path = $file->store("classcheck/modules/{$section->id}", 'local');

        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 9',
            'title' => 'To Delete',
            'file_path' => $path,
            'file_name' => 'module-cleanup.pdf',
            'file_size' => 500 * 1024,
            'file_mime' => 'application/pdf',
        ]);

        Storage::disk('local')->assertExists($path);

        $response = $this->actingAs($teacher)->delete(route('sections.modules.destroy', [$section, $module]));
        $response->assertRedirect();

        $this->assertDatabaseMissing('course_modules', ['id' => $module->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_another_teacher_cannot_access_or_modify_section_modules(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $section = $this->createSection($owner);

        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 1',
            'title' => 'Confidential Material',
        ]);

        $this->actingAs($outsider)->get(route('sections.modules.index', $section))->assertForbidden();
        $this->actingAs($outsider)->post(route('sections.modules.store', $section), [
            'module_number' => 'Module 2',
            'title' => 'Unauthorized',
        ])->assertForbidden();
        $this->actingAs($outsider)->post(route('sections.modules.update', [$section, $module]), [
            'module_number' => 'Module 1',
            'title' => 'Hacked',
        ])->assertForbidden();
        $this->actingAs($outsider)->delete(route('sections.modules.destroy', [$section, $module]))->assertForbidden();
    }
}
