<?php

namespace App\Services\Autochecker;

use App\Models\CourseModule;
use App\Models\Section;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ModuleRagService
{
    /**
     * Stopwords excluded from BM25 retrieval tokens.
     */
    protected static array $stopwords = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'aren\'t', 'as', 'at',
        'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by', 'can', 'can\'t', 'cannot',
        'could', 'couldn\'t', 'did', 'didn\'t', 'do', 'does', 'doesn\'t', 'doing', 'don\'t', 'down', 'during', 'each',
        'few', 'for', 'from', 'further', 'had', 'hadn\'t', 'has', 'hasn\'t', 'have', 'haven\'t', 'having', 'he', 'he\'d',
        'he\'ll', 'he\'s', 'her', 'here', 'here\'s', 'hers', 'herself', 'him', 'himself', 'his', 'how', 'how\'s', 'i',
        'i\'d', 'i\'ll', 'i\'m', 'i\'ve', 'if', 'in', 'into', 'is', 'isn\'t', 'it', 'it\'s', 'its', 'itself', 'let\'s',
        'me', 'more', 'most', 'mustn\'t', 'my', 'myself', 'no', 'nor', 'not', 'of', 'off', 'on', 'once', 'only', 'or',
        'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over', 'own', 'same', 'shan\'t', 'she', 'she\'d', 'she\'ll',
        'she\'s', 'should', 'shouldn\'t', 'so', 'some', 'such', 'than', 'that', 'that\'s', 'the', 'their', 'theirs',
        'them', 'themselves', 'then', 'there', 'there\'s', 'these', 'they', 'they\'d', 'they\'ll', 'they\'re', 'they\'ve',
        'this', 'those', 'through', 'to', 'too', 'under', 'until', 'up', 'very', 'was', 'wasn\'t', 'we', 'we\'d', 'we\'ll',
        'we\'re', 'we\'ve', 'were', 'weren\'t', 'what', 'what\'s', 'when', 'when\'s', 'where', 'where\'s', 'which', 'while',
        'who', 'who\'s', 'whom', 'why', 'why\'s', 'with', 'won\'t', 'would', 'wouldn\'t', 'you', 'you\'d', 'you\'ll', 'you\'re',
        'you\'ve', 'your', 'yours', 'yourself', 'yourselves',
    ];

    public function __construct(
        protected FileContentExtractorService $extractor
    ) {}

    /**
     * Resolve the absolute filesystem path for a stored relative file path.
     */
    public function resolveFilePath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        if (file_exists(storage_path('app/'.$path))) {
            return storage_path('app/'.$path);
        }

        if (file_exists(storage_path('app/private/'.$path))) {
            return storage_path('app/private/'.$path);
        }

        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    /**
     * Extract the complete textual representation of a course module with caching.
     */
    public function extractModuleText(CourseModule $module): string
    {
        $fileMtime = 0;
        $resolvedPath = $this->resolveFilePath($module->file_path);
        if ($resolvedPath && file_exists($resolvedPath)) {
            $fileMtime = filemtime($resolvedPath) ?: 0;
        }

        $cacheKey = "module_rag_text_{$module->id}_{$module->updated_at?->timestamp}_{$fileMtime}";

        return Cache::remember($cacheKey, 86400, function () use ($module, $resolvedPath) {
            $sections = [];

            // 1. Module metadata header
            $sections[] = "=== {$module->module_number}: {$module->title} ===";

            // 2. Module syllabus/overview description
            if (! empty(trim($module->description ?? ''))) {
                $sections[] = "Description & Topics:\n".trim($module->description);
            }

            // 3. Presentation link URL if present
            if (! empty(trim($module->link_url ?? ''))) {
                $sections[] = 'Presentation Link: '.trim($module->link_url);
            }

            // 4. File content extraction (PDF, DOCX, PPTX, TXT, code, etc.)
            if ($resolvedPath && file_exists($resolvedPath)) {
                try {
                    $extraction = $this->extractor->extract($resolvedPath, $module->file_name);
                    $content = trim($extraction['content'] ?? '');
                    if ($content !== '') {
                        $sections[] = "--- Handout / Slides Content ({$module->file_name}) ---\n".$content;
                    }
                } catch (Exception $e) {
                    Log::warning("ModuleRagService failed extracting file for module #{$module->id}: ".$e->getMessage());
                }
            }

            return implode("\n\n", $sections);
        });
    }

    /**
     * Segment a course module's text into structured, searchable semantic chunks with metadata.
     *
     * @return array<int, array{
     *     id: string,
     *     module_id: int,
     *     module_number: string,
     *     module_title: string,
     *     file_name: ?string,
     *     chunk_index: int,
     *     content: string,
     *     tokens: array<string, int>
     * }>
     */
    public function chunkModuleText(CourseModule $module, int $chunkSize = 650, int $overlap = 120): array
    {
        $fileMtime = 0;
        $resolvedPath = $this->resolveFilePath($module->file_path);
        if ($resolvedPath && file_exists($resolvedPath)) {
            $fileMtime = filemtime($resolvedPath) ?: 0;
        }

        $cacheKey = "module_rag_chunks_{$module->id}_{$module->updated_at?->timestamp}_{$fileMtime}_{$chunkSize}_{$overlap}";

        return Cache::remember($cacheKey, 86400, function () use ($module, $chunkSize, $overlap) {
            $fullText = $this->extractModuleText($module);
            if (trim($fullText) === '') {
                return [];
            }

            $rawPassages = [];

            // Case A: Presentation slides detected ([Slide 1], [Slide 2], etc.)
            if (preg_match('/\[Slide\s+\d+\]/i', $fullText)) {
                $slideBlocks = preg_split('/(?=\[Slide\s+\d+\])/i', $fullText, -1, PREG_SPLIT_NO_EMPTY);
                $currentGroup = '';

                foreach ($slideBlocks as $block) {
                    $trimmed = trim($block);
                    if ($trimmed === '') {
                        continue;
                    }

                    if (mb_strlen($currentGroup."\n\n".$trimmed) <= $chunkSize) {
                        $currentGroup = $currentGroup !== '' ? $currentGroup."\n\n".$trimmed : $trimmed;
                    } else {
                        if ($currentGroup !== '') {
                            $rawPassages[] = $currentGroup;
                        }
                        $currentGroup = $trimmed;
                    }
                }

                if ($currentGroup !== '') {
                    $rawPassages[] = $currentGroup;
                }
            } else {
                // Case B: Paragraphs & sliding-window segmentation
                $paragraphs = preg_split('/\n{2,}/', $fullText, -1, PREG_SPLIT_NO_EMPTY);
                $currentBuffer = '';

                foreach ($paragraphs as $para) {
                    $pTrimmed = trim($para);
                    if ($pTrimmed === '') {
                        continue;
                    }

                    // If a single paragraph is very long, break by sentences
                    if (mb_strlen($pTrimmed) > $chunkSize) {
                        if ($currentBuffer !== '') {
                            $rawPassages[] = $currentBuffer;
                            $currentBuffer = '';
                        }

                        $sentences = preg_split('/(?<=[.?!])\s+/u', $pTrimmed, -1, PREG_SPLIT_NO_EMPTY);
                        $sentBuffer = '';

                        foreach ($sentences as $sentence) {
                            if (mb_strlen($sentBuffer.' '.$sentence) <= $chunkSize) {
                                $sentBuffer = $sentBuffer !== '' ? $sentBuffer.' '.$sentence : $sentence;
                            } else {
                                if ($sentBuffer !== '') {
                                    $rawPassages[] = $sentBuffer;
                                    // Retain overlap from end of previous buffer
                                    $tail = mb_substr($sentBuffer, max(0, mb_strlen($sentBuffer) - $overlap));
                                    $sentBuffer = $tail.' '.$sentence;
                                } else {
                                    $rawPassages[] = mb_substr($sentence, 0, $chunkSize);
                                    $sentBuffer = '';
                                }
                            }
                        }

                        if ($sentBuffer !== '') {
                            $rawPassages[] = $sentBuffer;
                        }
                    } elseif (mb_strlen($currentBuffer."\n\n".$pTrimmed) <= $chunkSize) {
                        $currentBuffer = $currentBuffer !== '' ? $currentBuffer."\n\n".$pTrimmed : $pTrimmed;
                    } else {
                        if ($currentBuffer !== '') {
                            $rawPassages[] = $currentBuffer;
                            $tail = mb_substr($currentBuffer, max(0, mb_strlen($currentBuffer) - $overlap));
                            $currentBuffer = $tail."\n\n".$pTrimmed;
                        } else {
                            $currentBuffer = $pTrimmed;
                        }
                    }
                }

                if ($currentBuffer !== '') {
                    $rawPassages[] = $currentBuffer;
                }
            }

            // Format passages into structured chunk objects with token maps
            $chunks = [];
            foreach ($rawPassages as $idx => $passage) {
                $cleanPassage = trim($passage);
                if (mb_strlen($cleanPassage) < 20) {
                    continue; // Skip trivial lines
                }

                $chunks[] = [
                    'id' => "mod_{$module->id}_c".($idx + 1),
                    'module_id' => $module->id,
                    'module_number' => $module->module_number,
                    'module_title' => $module->title,
                    'file_name' => $module->file_name,
                    'chunk_index' => $idx + 1,
                    'content' => $cleanPassage,
                    'tokens' => $this->tokenize($cleanPassage),
                ];
            }

            return $chunks;
        });
    }

    /**
     * Search across section modules using BM25 ranking algorithm with title boosting.
     *
     * @param  array<int>|null  $moduleIds
     * @return array<int, array{
     *     chunk_id: string,
     *     module_id: int,
     *     module_number: string,
     *     module_title: string,
     *     file_name: ?string,
     *     chunk_index: int,
     *     score: float,
     *     content: string,
     *     citation: string
     * }>
     */
    public function search(Section|int $section, string $query, ?array $moduleIds = null, int $limit = 6): array
    {
        $sectionId = $section instanceof Section ? $section->id : (int) $section;
        $cleanQuery = trim($query);

        if ($cleanQuery === '') {
            return [];
        }

        // 1. Fetch relevant modules for this section
        $modulesQuery = CourseModule::where('section_id', $sectionId)->orderBy('sort_order')->orderBy('id');
        if (! empty($moduleIds)) {
            $modulesQuery->whereIn('id', $moduleIds);
        }
        $modules = $modulesQuery->get();

        if ($modules->isEmpty()) {
            return [];
        }

        // 2. Gather all chunks across modules
        $corpus = [];
        foreach ($modules as $mod) {
            $chunks = $this->chunkModuleText($mod);
            foreach ($chunks as $c) {
                $corpus[] = $c;
            }
        }

        $totalDocs = count($corpus);
        if ($totalDocs === 0) {
            return [];
        }

        // 3. Extract query tokens
        $queryTokens = array_keys($this->tokenize($cleanQuery));
        if (empty($queryTokens)) {
            return [];
        }

        // 4. Calculate Document Frequency (DF) across corpus for query tokens
        $df = [];
        $totalDocLen = 0;

        foreach ($corpus as $doc) {
            $docLen = array_sum($doc['tokens']);
            $totalDocLen += $docLen;

            foreach ($queryTokens as $term) {
                if (isset($doc['tokens'][$term])) {
                    $df[$term] = ($df[$term] ?? 0) + 1;
                }
            }
        }

        $avgDocLen = $totalDocs > 0 ? ($totalDocLen / $totalDocs) : 1.0;
        $k1 = 1.2;
        $b = 0.75;

        // 5. Calculate BM25 score for each chunk
        $scored = [];
        $lowerQuery = mb_strtolower($cleanQuery);

        foreach ($corpus as $doc) {
            $score = 0.0;
            $docLen = max(1, array_sum($doc['tokens']));

            foreach ($queryTokens as $term) {
                $n = $df[$term] ?? 0;
                if ($n === 0) {
                    continue;
                }

                // Standard Lucene/BM25 IDF
                $idf = log(1.0 + ($totalDocs - $n + 0.5) / ($n + 0.5));
                $tf = $doc['tokens'][$term] ?? 0;

                if ($tf > 0) {
                    $denom = $tf + $k1 * (1.0 - $b + $b * ($docLen / $avgDocLen));
                    $bm25 = $idf * (($tf * ($k1 + 1.0)) / $denom);
                    $score += $bm25;
                }
            }

            // Title and Module Number Boost
            $titleTokens = $this->tokenize($doc['module_title'].' '.$doc['module_number']);
            foreach ($queryTokens as $term) {
                if (isset($titleTokens[$term])) {
                    $score += 1.5;
                }
            }

            // Exact Phrase Match Boost
            $lowerContent = mb_strtolower($doc['content']);
            if (mb_strpos($lowerContent, $lowerQuery) !== false) {
                $score += 2.5;
            }

            if ($score > 0.05) {
                $citation = "[{$doc['module_number']}: {$doc['module_title']}".($doc['file_name'] ? " ({$doc['file_name']})" : '')." - Excerpt #{$doc['chunk_index']}]";

                $scored[] = [
                    'chunk_id' => $doc['id'],
                    'module_id' => $doc['module_id'],
                    'module_number' => $doc['module_number'],
                    'module_title' => $doc['module_title'],
                    'file_name' => $doc['file_name'],
                    'chunk_index' => $doc['chunk_index'],
                    'score' => round($score, 3),
                    'content' => $doc['content'],
                    'citation' => $citation,
                ];
            }
        }

        // Sort descending by score
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Format retrieved search results into a clean markdown grounding block for LLM prompts.
     */
    public function formatGroundingContext(array $searchResults, int $maxTotalChars = 12000): string
    {
        if (empty($searchResults)) {
            return '';
        }

        $lines = [];
        $lines[] = '=== GROUNDED COURSE MODULE CURRICULUM (RAG CONTEXT) ===';
        $lines[] = 'AUTHORITY RULE: Ground your output strictly on the official syllabus, slide handouts, and definitions taught in these course modules. Do not contradict or hallucinate outside this material.';
        $lines[] = '';

        $accumulatedChars = 0;

        foreach ($searchResults as $result) {
            $citation = $result['citation'] ?? "[{$result['module_number']}: {$result['module_title']}]";
            $content = trim($result['content'] ?? '');

            $entry = "--- {$citation} ---\n{$content}\n";
            $entryLen = mb_strlen($entry);

            if ($accumulatedChars + $entryLen > $maxTotalChars) {
                $allowed = max(200, $maxTotalChars - $accumulatedChars - 50);
                $entry = "--- {$citation} ---\n".mb_substr($content, 0, $allowed)."... [excerpt continues]\n";
                $lines[] = $entry;
                break;
            }

            $lines[] = $entry;
            $accumulatedChars += $entryLen;
        }

        return implode("\n", $lines);
    }

    /**
     * Retrieve and synthesize rich curriculum grounding for exam/quiz generation from selected modules.
     *
     * @param  Collection<int, CourseModule>  $modules
     * @return array<int, array{
     *     id: int,
     *     module_number: string,
     *     title: string,
     *     description: ?string,
     *     link_url: ?string,
     *     excerpt: string,
     *     has_file: bool,
     *     file_name: ?string
     * }>
     */
    public function retrieveForCurriculum(Collection $modules, string $queryContext, int $maxTotalChars = 14000): array
    {
        $curriculum = [];
        $moduleCount = max(1, $modules->count());
        $perModuleBudget = (int) floor($maxTotalChars / $moduleCount);

        foreach ($modules as $module) {
            $excerpt = '';
            $hasFile = ! empty($module->file_path) && $this->resolveFilePath($module->file_path);
            $fileName = $module->file_name;

            // 1. Include syllabus / topics description
            if (! empty(trim($module->description ?? ''))) {
                $excerpt .= "Syllabus Objectives & Topics:\n".trim($module->description)."\n\n";
            }

            // 2. Perform RAG query on this specific module's content if it has file or text
            if ($hasFile) {
                // Query chunks of this module using teacher query context
                $chunks = $this->search(
                    section: $module->section_id,
                    query: $queryContext ?: $module->title,
                    moduleIds: [$module->id],
                    limit: 5
                );

                if (! empty($chunks)) {
                    $excerpt .= "[Key Lecture & Handout Excerpts ({$fileName})]:\n";
                    foreach ($chunks as $c) {
                        $excerpt .= '- '.trim($c['content'])."\n\n";
                    }
                } else {
                    // Fallback to initial content if search had low match
                    $full = $this->extractModuleText($module);
                    $cleaned = preg_replace('/\s+/', ' ', $full) ?? $full;
                    $excerpt .= "[Handout Content ({$fileName})]:\n".mb_substr($cleaned, 0, 2500)."\n";
                }
            }

            // Keep within per-module budget
            if (mb_strlen($excerpt) > $perModuleBudget) {
                $excerpt = mb_substr($excerpt, 0, $perModuleBudget).'... [content continues]';
            }

            $curriculum[] = [
                'id' => $module->id,
                'module_number' => $module->module_number,
                'title' => $module->title,
                'description' => $module->description,
                'link_url' => $module->link_url,
                'excerpt' => trim($excerpt),
                'has_file' => (bool) $hasFile,
                'file_name' => $fileName,
            ];
        }

        return $curriculum;
    }

    /**
     * Invalidate cached text and chunk data for a course module.
     */
    public function clearModuleCache(CourseModule $module): void
    {
        $fileMtime = 0;
        $resolvedPath = $this->resolveFilePath($module->file_path);
        if ($resolvedPath && file_exists($resolvedPath)) {
            $fileMtime = filemtime($resolvedPath) ?: 0;
        }

        Cache::forget("module_rag_text_{$module->id}_{$module->updated_at?->timestamp}_{$fileMtime}");
        Cache::forget("module_rag_chunks_{$module->id}_{$module->updated_at?->timestamp}_{$fileMtime}_650_120");
    }

    /**
     * Normalize and tokenize text into term frequencies, excluding stopwords.
     *
     * @return array<string, int>
     */
    protected function tokenize(string $text): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}_\/-]/u', ' ', mb_strtolower($text));
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

        $freq = [];
        foreach ($words as $w) {
            $len = mb_strlen($w);
            if ($len < 2 || in_array($w, self::$stopwords, true) || is_numeric($w)) {
                continue;
            }
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }

        return $freq;
    }
}
