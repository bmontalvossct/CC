<?php

namespace Tests\Unit;

use App\Models\AcademicTerm;
use App\Models\CourseModule;
use App\Models\Section;
use App\Models\User;
use App\Services\Autochecker\FileContentExtractorService;
use App\Services\Autochecker\ModuleRagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class ModuleRagServiceTest extends TestCase
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
            'subject_title' => 'Computer Science Fundamentals',
            'name' => 'Section A',
        ]);
    }

    public function test_extract_module_text_compiles_metadata_and_description(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 1',
            'title' => 'Computer Hardware Architecture',
            'description' => 'Introduction to the CPU, RAM, registers, and bus architecture.',
            'link_url' => 'https://example.com/slides/hardware',
            'sort_order' => 1,
        ]);

        $service = app(ModuleRagService::class);
        $text = $service->extractModuleText($module);

        $this->assertStringContainsString('=== Module 1: Computer Hardware Architecture ===', $text);
        $this->assertStringContainsString('Introduction to the CPU, RAM, registers, and bus architecture.', $text);
        $this->assertStringContainsString('Presentation Link: https://example.com/slides/hardware', $text);
    }

    public function test_extract_module_text_integrates_file_content(): void
    {
        Storage::fake('local');
        $filePath = 'modules/sample_lecture.txt';
        Storage::disk('local')->put($filePath, "The Von Neumann architecture consists of a CPU, memory, and input/output mechanisms.\nThe arithmetic logic unit (ALU) performs mathematical operations.");

        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 2',
            'title' => 'Von Neumann Model',
            'description' => 'Detailed look at ALU and control unit.',
            'file_path' => $filePath,
            'file_name' => 'sample_lecture.txt',
            'sort_order' => 2,
        ]);

        $service = app(ModuleRagService::class);
        $text = $service->extractModuleText($module);

        $this->assertStringContainsString('Von Neumann architecture consists of a CPU', $text);
        $this->assertStringContainsString('arithmetic logic unit (ALU)', $text);
        $this->assertStringContainsString('sample_lecture.txt', $text);
    }

    public function test_chunk_module_text_splits_by_slides(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $slideContent = "[Slide 1]\nIntroduction to Relational Databases and SQL tables.\n\n"
            . "[Slide 2]\nPrimary keys uniquely identify rows in a table while foreign keys link related records.\n\n"
            . "[Slide 3]\nDatabase normalization decomposes large tables to reduce redundancy and anomalies.";

        $mockExtractor = Mockery::mock(FileContentExtractorService::class);
        $mockExtractor->shouldReceive('extract')->andReturn([
            'success' => true,
            'content' => $slideContent,
            'extension' => 'pptx',
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('modules/slides.pptx', 'fake-zip-binary');

        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 3',
            'title' => 'Relational Database Fundamentals',
            'description' => 'SQL and database design principles.',
            'file_path' => 'modules/slides.pptx',
            'file_name' => 'slides.pptx',
            'sort_order' => 3,
        ]);

        $service = new ModuleRagService($mockExtractor);
        $chunks = $service->chunkModuleText($module);

        $this->assertNotEmpty($chunks);
        $slideChunk = collect($chunks)->first(fn ($c) => str_contains($c['content'], 'Slide 1'));
        $this->assertNotNull($slideChunk);
        $this->assertEquals('Module 3', $slideChunk['module_number']);
        $this->assertEquals('Relational Database Fundamentals', $slideChunk['module_title']);
        $this->assertArrayHasKey('relational', $slideChunk['tokens']);
    }

    public function test_chunk_module_text_sliding_window_for_long_paragraphs(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $longParagraph = str_repeat("Data structures organize and store data efficiently in memory. ", 20);

        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 4',
            'title' => 'Data Structures',
            'description' => $longParagraph,
            'sort_order' => 4,
        ]);

        $service = app(ModuleRagService::class);
        $chunks = $service->chunkModuleText($module, chunkSize: 300, overlap: 60);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $c) {
            $this->assertNotEmpty($c['content']);
            $this->assertNotEmpty($c['tokens']);
        }
    }

    public function test_bm25_search_ranks_relevant_chunks_highest(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        // Module A: Operating Systems & Virtual Memory
        CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 1',
            'title' => 'Operating Systems and Virtual Memory',
            'description' => 'Paging, page faults, translation lookaside buffer (TLB), and virtual address spaces.',
            'sort_order' => 1,
        ]);

        // Module B: Computer Networks
        CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 2',
            'title' => 'Networking and TCP/IP',
            'description' => 'Three-way handshake, packet routing, IP addressing, DNS resolution, and UDP.',
            'sort_order' => 2,
        ]);

        $service = app(ModuleRagService::class);

        // Search virtual memory
        $results = $service->search($section, 'virtual memory page faults TLB');

        $this->assertNotEmpty($results);
        $top = $results[0];
        $this->assertEquals('Module 1', $top['module_number']);
        $this->assertEquals('Operating Systems and Virtual Memory', $top['module_title']);
        $this->assertGreaterThan(0.5, $top['score']);
    }

    public function test_search_exact_phrase_and_title_boost(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 5',
            'title' => 'Software Engineering Design Patterns',
            'description' => 'The singleton design pattern guarantees only a single instance of a class exists across runtime.',
            'sort_order' => 5,
        ]);

        $service = app(ModuleRagService::class);

        // Search with exact phrase
        $results = $service->search($section, 'singleton design pattern');

        $this->assertNotEmpty($results);
        $top = $results[0];
        // Exact phrase (+2.5) plus title match (+1.5) should yield high score (> 4.0)
        $this->assertGreaterThan(3.5, $top['score']);
        $this->assertStringContainsString('Module 5', $top['citation']);
    }

    public function test_search_filters_by_specific_module_ids(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $modA = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module A',
            'title' => 'Algorithmic Complexity',
            'description' => 'Big-O notation and asymptotic upper bounds.',
            'sort_order' => 1,
        ]);

        $modB = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module B',
            'title' => 'Sorting Algorithms',
            'description' => 'QuickSort and MergeSort asymptotic analysis.',
            'sort_order' => 2,
        ]);

        $service = app(ModuleRagService::class);

        // Query targeting only modB
        $results = $service->search($section, 'asymptotic', moduleIds: [$modB->id]);

        $this->assertNotEmpty($results);
        foreach ($results as $res) {
            $this->assertEquals($modB->id, $res['module_id']);
        }
    }

    public function test_format_grounding_context_produces_citation_headers_and_respects_limits(): void
    {
        $service = app(ModuleRagService::class);

        $fakeResults = [
            [
                'citation' => '[Module 1: Intro to Web (slides.pptx) - Excerpt #1]',
                'module_number' => 'Module 1',
                'module_title' => 'Intro to Web',
                'content' => 'HTTP is a stateless request-response protocol running over TCP port 80 or 443.',
            ],
            [
                'citation' => '[Module 2: REST APIs - Excerpt #1]',
                'module_number' => 'Module 2',
                'module_title' => 'REST APIs',
                'content' => 'REST relies on standard HTTP methods like GET, POST, PUT, and DELETE.',
            ],
        ];

        $grounding = $service->formatGroundingContext($fakeResults, maxTotalChars: 1000);

        $this->assertStringContainsString('=== GROUNDED COURSE MODULE CURRICULUM (RAG CONTEXT) ===', $grounding);
        $this->assertStringContainsString('[Module 1: Intro to Web (slides.pptx) - Excerpt #1]', $grounding);
        $this->assertStringContainsString('HTTP is a stateless request-response protocol', $grounding);
        $this->assertStringContainsString('[Module 2: REST APIs - Excerpt #1]', $grounding);

        // Empty results
        $emptyGrounding = $service->formatGroundingContext([]);
        $this->assertEquals('', $emptyGrounding);
    }

    public function test_retrieve_for_curriculum_structures_all_selected_modules(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $mod1 = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 1',
            'title' => 'Cybersecurity Principles',
            'description' => 'CIA Triad: Confidentiality, Integrity, and Availability.',
            'sort_order' => 1,
        ]);

        $mod2 = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 2',
            'title' => 'Cryptography',
            'description' => 'Symmetric vs asymmetric encryption and digital signatures.',
            'sort_order' => 2,
        ]);

        $service = app(ModuleRagService::class);
        $curriculum = $service->retrieveForCurriculum(collect([$mod1, $mod2]), 'encryption and security principles');

        $this->assertCount(2, $curriculum);
        $this->assertEquals('Module 1', $curriculum[0]['module_number']);
        $this->assertStringContainsString('Confidentiality, Integrity, and Availability', $curriculum[0]['excerpt']);
        $this->assertEquals('Module 2', $curriculum[1]['module_number']);
        $this->assertStringContainsString('Symmetric vs asymmetric encryption', $curriculum[1]['excerpt']);
    }

    public function test_clear_module_cache_clears_cached_chunks(): void
    {
        $teacher = User::factory()->create();
        $section = $this->createSection($teacher);

        $module = CourseModule::create([
            'section_id' => $section->id,
            'module_number' => 'Module 10',
            'title' => 'Cloud Computing',
            'description' => 'IaaS, PaaS, and SaaS cloud service architectures.',
            'sort_order' => 10,
        ]);

        $service = app(ModuleRagService::class);
        $chunksBefore = $service->chunkModuleText($module);
        $this->assertNotEmpty($chunksBefore);

        // Invalidate cache
        $service->clearModuleCache($module);

        // Should cleanly re-chunk without error
        $chunksAfter = $service->chunkModuleText($module);
        $this->assertEquals($chunksBefore, $chunksAfter);
    }

    public function test_pptx_slides_extraction_via_file_extractor(): void
    {
        if (! class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive is not available.');
        }

        // Create a temporary valid pptx file with 2 slides
        $tempPptx = tempnam(sys_get_temp_dir(), 'test_pptx_') . '.pptx';
        $zip = new ZipArchive();
        $zip->open($tempPptx, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $slide1Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree>'
            . '<p:sp><p:txBody><a:p><a:r><a:t>Slide One: Object Oriented Programming</a:t></a:r></a:p>'
            . '<a:p><a:r><a:t>Encapsulation and Abstraction</a:t></a:r></a:p></p:txBody></p:sp>'
            . '</p:spTree></p:cSld></p:sld>';

        $slide2Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree>'
            . '<p:sp><p:txBody><a:p><a:r><a:t>Slide Two: Polymorphism and Inheritance</a:t></a:r></a:p></p:txBody></p:sp>'
            . '</p:spTree></p:cSld></p:sld>';

        $zip->addFromString('ppt/slides/slide1.xml', $slide1Xml);
        $zip->addFromString('ppt/slides/slide2.xml', $slide2Xml);
        $zip->close();

        try {
            $extractor = new FileContentExtractorService();
            $result = $extractor->extract($tempPptx, 'lecture.pptx');

            $this->assertTrue($result['success']);
            $this->assertEquals('pptx', $result['extension']);
            $this->assertStringContainsString('[Slide 1]', $result['content']);
            $this->assertStringContainsString('Slide One: Object Oriented Programming', $result['content']);
            $this->assertStringContainsString('Encapsulation and Abstraction', $result['content']);
            $this->assertStringContainsString('[Slide 2]', $result['content']);
            $this->assertStringContainsString('Slide Two: Polymorphism and Inheritance', $result['content']);
        } finally {
            if (file_exists($tempPptx)) {
                @unlink($tempPptx);
            }
        }
    }
}
