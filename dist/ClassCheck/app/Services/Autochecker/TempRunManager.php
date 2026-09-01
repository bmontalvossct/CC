<?php

namespace App\Services\Autochecker;

use App\Models\Student;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class TempRunManager
{
    protected string $storageBase;
    protected FilenameMatcherService $matcherService;
    protected FileContentExtractorService $extractorService;

    public function __construct(
        FilenameMatcherService $matcherService,
        FileContentExtractorService $extractorService
    ) {
        $this->matcherService = $matcherService;
        $this->extractorService = $extractorService;
        $this->storageBase = storage_path('app/temp_runs');

        if (! is_dir($this->storageBase)) {
            @mkdir($this->storageBase, 0755, true);
        }
    }

    /**
     * Create an opaque temporary run from uploaded files or ZIP archive.
     *
     * @param int $userId
     * @param int $assessmentId
     * @param Collection<int, Student> $students
     * @param array<int, UploadedFile>|null $files
     * @param UploadedFile|null $zipFile
     * @return array{run_id: string, items: array<int, array<string, mixed>>, total_files: int, matched_count: int}
     * @throws Exception
     */
    public function createRun(
        int $userId,
        int $assessmentId,
        Collection $students,
        ?array $files = null,
        ?UploadedFile $zipFile = null
    ): array {
        $this->cleanupExpiredRuns();

        $runId = uniqid("run_{$userId}_{$assessmentId}_", true);
        $runDir = "{$this->storageBase}/{$runId}";

        if (! @mkdir($runDir, 0755, true)) {
            throw new Exception("Unable to initialize temporary storage run directory.", 500);
        }

        $limits = config('autochecker.limits', [
            'max_direct_files' => 20,
            'max_zip_entries' => 100,
            'max_file_size_kb' => 10240,
            'max_total_expanded_kb' => 102400,
        ]);

        $maxDirect = (int) ($limits['max_direct_files'] ?? 20);
        $maxZipEntries = (int) ($limits['max_zip_entries'] ?? 100);
        $maxEntryKb = (int) ($limits['max_file_size_kb'] ?? 10240);
        $maxExpandedKb = (int) ($limits['max_total_expanded_kb'] ?? 102400);

        $items = [];
        $totalExpandedBytes = 0;
        $seenBasenames = [];

        // 1. Handle uploaded ZIP file if present
        if ($zipFile && $zipFile->isValid()) {
            $zipPath = $zipFile->getRealPath();
            $zip = new ZipArchive();

            if ($zip->open($zipPath) !== true) {
                $this->deleteRun($runId);
                throw new Exception("Invalid or corrupt ZIP archive uploaded.", 422);
            }

            $numFiles = $zip->numFiles;
            if ($numFiles > $maxZipEntries) {
                $zip->close();
                $this->deleteRun($runId);
                throw new Exception("ZIP archive contains {$numFiles} files, exceeding the limit of {$maxZipEntries} files.", 422);
            }

            for ($i = 0; $i < $numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = $stat['name'] ?? '';
                $entrySize = $stat['size'] ?? 0;

                // Skip directories and system metadata (like __MACOSX)
                if (str_ends_with($entryName, '/') || str_starts_with($entryName, '__MACOSX') || str_starts_with(basename($entryName), '._')) {
                    continue;
                }

                if (($entrySize / 1024) > $maxEntryKb) {
                    $zip->close();
                    $this->deleteRun($runId);
                    throw new Exception("File '{$entryName}' inside ZIP exceeds max allowed size of " . round($maxEntryKb / 1024) . " MB.", 422);
                }

                $totalExpandedBytes += $entrySize;
                if (($totalExpandedBytes / 1024) > $maxExpandedKb) {
                    $zip->close();
                    $this->deleteRun($runId);
                    throw new Exception("Total expanded ZIP archive size exceeds maximum limit of " . round($maxExpandedKb / 1024) . " MB.", 422);
                }

                $filename = basename($entryName);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (! in_array($ext, config('autochecker.supported_extensions', []), true)) {
                    continue;
                }

                $itemId = 'item_' . (count($items) + 1);
                $targetPath = "{$runDir}/{$itemId}_{$filename}";

                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    continue;
                }

                file_put_contents($targetPath, $content);
                $sha256 = hash('sha256', $content);

                $match = $this->matcherService->matchSingle($students, $filename);
                $extraction = $this->extractorService->extract($targetPath, $filename);

                $previewLines = $this->generateNumberedPreview($extraction['content'] ?? '');

                $items[$itemId] = [
                    'item_id' => $itemId,
                    'filename' => $filename,
                    'extension' => $ext,
                    'sha256' => $sha256,
                    'file_path' => $targetPath,
                    'file_size_bytes' => strlen($content),
                    'student_id' => $match['student_id'],
                    'student_number' => $match['student_number'],
                    'student_name' => $match['student_name'],
                    'confidence' => $match['confidence'],
                    'match_type' => $match['match_type'],
                    'match_reason' => $match['reason'],
                    'content_success' => $extraction['success'],
                    'line_count' => substr_count($extraction['content'] ?? '', "\n") + 1,
                    'preview_lines' => $previewLines,
                    'error' => $extraction['error'] ?? null,
                    'proposal' => null, // Populated after LLM evaluation
                ];
            }

            $zip->close();
        }

        // 2. Handle direct uploaded multiple files
        if ($files && is_array($files)) {
            if (count($files) > $maxDirect) {
                $this->deleteRun($runId);
                throw new Exception("Direct upload exceeds maximum of {$maxDirect} files. Please upload a ZIP archive for larger batches.", 422);
            }

            foreach ($files as $file) {
                if (! $file->isValid()) {
                    continue;
                }

                $filename = $file->getClientOriginalName();
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (! in_array($ext, config('autochecker.supported_extensions', []), true)) {
                    continue;
                }

                $size = $file->getSize();
                if (($size / 1024) > $maxEntryKb) {
                    $this->deleteRun($runId);
                    throw new Exception("File '{$filename}' exceeds max size of " . round($maxEntryKb / 1024) . " MB.", 422);
                }

                $itemId = 'item_' . (count($items) + 1);
                $targetPath = "{$runDir}/{$itemId}_{$filename}";

                $file->move($runDir, basename($targetPath));
                $content = file_get_contents($targetPath);
                $sha256 = hash('sha256', $content ?: '');

                $match = $this->matcherService->matchSingle($students, $filename);
                $extraction = $this->extractorService->extract($targetPath, $filename);

                $previewLines = $this->generateNumberedPreview($extraction['content'] ?? '');

                $items[$itemId] = [
                    'item_id' => $itemId,
                    'filename' => $filename,
                    'extension' => $ext,
                    'sha256' => $sha256,
                    'file_path' => $targetPath,
                    'file_size_bytes' => strlen($content ?: ''),
                    'student_id' => $match['student_id'],
                    'student_number' => $match['student_number'],
                    'student_name' => $match['student_name'],
                    'confidence' => $match['confidence'],
                    'match_type' => $match['match_type'],
                    'match_reason' => $match['reason'],
                    'content_success' => $extraction['success'],
                    'line_count' => substr_count($extraction['content'] ?? '', "\n") + 1,
                    'preview_lines' => $previewLines,
                    'error' => $extraction['error'] ?? null,
                    'proposal' => null,
                ];
            }
        }

        if (empty($items)) {
            $this->deleteRun($runId);
            throw new Exception("No valid or supported submission files found in the upload.", 422);
        }

        $manifest = [
            'run_id' => $runId,
            'user_id' => $userId,
            'assessment_id' => $assessmentId,
            'created_at' => time(),
            'expires_at' => time() + (24 * 3600),
            'items' => $items,
        ];

        file_put_contents("{$runDir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));

        // Return lightweight DTO (without full local file paths) to frontend
        $frontendItems = array_map(function ($item) {
            $copy = $item;
            unset($copy['file_path']);
            return $copy;
        }, array_values($items));

        return [
            'run_id' => $runId,
            'items' => $frontendItems,
            'total_files' => count($items),
            'matched_count' => count(array_filter($items, fn ($i) => ! empty($i['student_id']))),
        ];
    }

    /**
     * Retrieve manifest and item details for a run.
     */
    public function getRun(string $runId, int $userId): ?array
    {
        $runDir = "{$this->storageBase}/{$runId}";
        $manifestPath = "{$runDir}/manifest.json";

        if (! file_exists($manifestPath)) {
            return null;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (! is_array($manifest) || ($manifest['user_id'] ?? 0) !== $userId) {
            return null;
        }

        return $manifest;
    }

    /**
     * Get item raw content with numbered lines for LLM grading.
     */
    public function getItemContentForGrading(string $runId, string $itemId, int $userId): ?string
    {
        $manifest = $this->getRun($runId, $userId);
        if (! $manifest || ! isset($manifest['items'][$itemId])) {
            return null;
        }

        $item = $manifest['items'][$itemId];
        $filePath = $item['file_path'] ?? null;

        if (! $filePath || ! file_exists($filePath)) {
            return null;
        }

        $extraction = $this->extractorService->extract($filePath, $item['filename']);
        $raw = $extraction['content'] ?? '';

        return $this->formatLineNumberedCode($raw);
    }

    /**
     * Cache an evaluation proposal on an item within the run manifest.
     */
    public function saveItemProposal(string $runId, string $itemId, int $userId, array $proposal): void
    {
        $manifest = $this->getRun($runId, $userId);
        if (! $manifest || ! isset($manifest['items'][$itemId])) {
            return;
        }

        $manifest['items'][$itemId]['proposal'] = $proposal;
        $manifestPath = "{$this->storageBase}/{$runId}/manifest.json";

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    /**
     * Delete temporary run directory.
     */
    public function deleteRun(string $runId): void
    {
        $runDir = "{$this->storageBase}/{$runId}";
        if (is_dir($runDir)) {
            File::deleteDirectory($runDir);
        }
    }

    /**
     * Garbage collect runs older than 24 hours.
     */
    public function cleanupExpiredRuns(): void
    {
        if (! is_dir($this->storageBase)) {
            return;
        }

        $dirs = glob("{$this->storageBase}/run_*");
        $now = time();

        foreach ($dirs as $dir) {
            $manifestPath = "{$dir}/manifest.json";
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                if (isset($manifest['expires_at']) && $manifest['expires_at'] < $now) {
                    File::deleteDirectory($dir);
                }
            } elseif ((filemtime($dir) + (24 * 3600)) < $now) {
                File::deleteDirectory($dir);
            }
        }
    }

    /**
     * Format line numbered code.
     */
    protected function formatLineNumberedCode(string $code): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $code));
        $numbered = [];

        foreach ($lines as $idx => $line) {
            $lineNum = str_pad((string) ($idx + 1), 4, ' ', STR_PAD_LEFT);
            $numbered[] = "{$lineNum} | {$line}";
        }

        return implode("\n", $numbered);
    }

    /**
     * Generate first 20 lines preview.
     */
    protected function generateNumberedPreview(string $code): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $code));
        $preview = [];

        foreach (array_slice($lines, 0, 25) as $idx => $line) {
            $preview[] = [
                'line' => $idx + 1,
                'content' => $line,
            ];
        }

        return $preview;
    }
}
