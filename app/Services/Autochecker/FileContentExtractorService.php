<?php

namespace App\Services\Autochecker;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class FileContentExtractorService
{
    /**
     * Extract text content from an uploaded file.
     *
     * @param  UploadedFile|string  $file
     * @return array{success: bool, content: string, extension: string, error?: string}
     */
    public function extract($file, ?string $originalFilename = null): array
    {
        $filename = $originalFilename ?? ($file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file));
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return [
                'success' => false,
                'content' => '',
                'extension' => $extension,
                'error' => 'File is not readable or missing.',
            ];
        }

        // 1. PDF Files (Local extraction via stream / regex or pdftotext)
        if ($extension === 'pdf') {
            $pdfText = $this->extractPdfText($filePath);

            return [
                'success' => ! empty(trim($pdfText)),
                'content' => $pdfText,
                'extension' => $extension,
                'error' => empty(trim($pdfText)) ? 'Could not extract readable text from PDF.' : null,
            ];
        }

        // 2. Word DOCX Files (Extract text from word/document.xml)
        if ($extension === 'docx') {
            $docxText = $this->extractDocxText($filePath);

            return [
                'success' => ! empty(trim($docxText)),
                'content' => $docxText,
                'extension' => $extension,
                'error' => empty(trim($docxText)) ? 'Could not extract readable text from Word document.' : null,
            ];
        }

        // 3. PowerPoint PPTX Files (Extract text from ppt/slides/slide*.xml)
        if ($extension === 'pptx') {
            $pptxText = $this->extractPptxText($filePath);

            return [
                'success' => ! empty(trim($pptxText)),
                'content' => $pptxText,
                'extension' => $extension,
                'error' => empty(trim($pptxText)) ? 'Could not extract readable text from PowerPoint presentation.' : null,
            ];
        }

        // 4. Plain text & code files
        $codeExtensions = config('autochecker.supported_extensions', [
            'py', 'java', 'c', 'cpp', 'cs', 'js', 'jsx', 'ts', 'tsx',
            'php', 'html', 'css', 'sql', 'rb', 'go', 'rs', 'swift', 'kt',
            'txt', 'md', 'json', 'xml', 'csv',
        ]);

        if (in_array($extension, $codeExtensions, true)) {
            $raw = file_get_contents($filePath);
            if ($raw === false) {
                return [
                    'success' => false,
                    'content' => '',
                    'extension' => $extension,
                    'error' => 'Failed to read file contents.',
                ];
            }

            $cleaned = $this->cleanUtf8($raw);

            return [
                'success' => true,
                'content' => $cleaned,
                'extension' => $extension,
            ];
        }

        // Fallback: Attempt UTF-8 read
        $raw = @file_get_contents($filePath);
        if ($raw !== false && ! preg_match('~[^\x20-\x7E\t\r\n]~', substr($raw, 0, 100))) {
            return [
                'success' => true,
                'content' => $this->cleanUtf8($raw),
                'extension' => $extension,
            ];
        }

        return [
            'success' => false,
            'content' => '',
            'extension' => $extension,
            'error' => "Unsupported file type: .{$extension}",
        ];
    }

    /**
     * Unpack a zip file and extract individual submission files.
     *
     * @param  UploadedFile|string  $zipFile
     * @return array<int, string> List of extracted file paths
     */
    public function unpackZip($zipFile, string $destinationDir): array
    {
        $zipPath = $zipFile instanceof UploadedFile ? $zipFile->getRealPath() : $zipFile;
        $extractedPaths = [];

        if (! class_exists('ZipArchive')) {
            return [];
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) === true) {
            if (! is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                // Skip directories and MacOS/system meta files
                if (str_ends_with($entryName, '/') || str_contains($entryName, '__MACOSX') || str_starts_with(basename($entryName), '.')) {
                    continue;
                }

                $targetPath = $destinationDir.DIRECTORY_SEPARATOR.basename($entryName);
                if (copy("zip://{$zipPath}#{$entryName}", $targetPath)) {
                    $extractedPaths[] = $targetPath;
                }
            }
            $zip->close();
        }

        return $extractedPaths;
    }

    /**
     * Basic local PDF text extraction without external dependencies.
     */
    private function extractPdfText(string $filePath): string
    {
        $content = @file_get_contents($filePath);
        if (! $content) {
            return '';
        }

        // Check if pdftotext CLI is available locally
        if (function_exists('exec')) {
            $tempOutput = tempnam(sys_get_temp_dir(), 'pdf_out_');
            @exec('pdftotext '.escapeshellarg($filePath).' '.escapeshellarg($tempOutput), $out, $code);
            if ($code === 0 && file_exists($tempOutput)) {
                $text = file_get_contents($tempOutput);
                @unlink($tempOutput);
                if (! empty(trim($text))) {
                    return $this->cleanUtf8($text);
                }
            }
            if (file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
        }

        // Basic PDF stream object parser
        $text = '';
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $uncompressed = @gzuncompress($stream);
                if ($uncompressed === false) {
                    $uncompressed = $stream;
                }

                if (preg_match_all('/\[([^\]]+)\]\s*TJ/s', $uncompressed, $tjMatches)) {
                    foreach ($tjMatches[1] as $tj) {
                        if (preg_match_all('/\((.*?)\)/s', $tj, $tMatches)) {
                            $text .= implode('', $tMatches[1]).' ';
                        }
                    }
                } elseif (preg_match_all('/\((.*?)\)\s*Tj/s', $uncompressed, $tMatches)) {
                    $text .= implode(' ', $tMatches[1])."\n";
                }
            }
        }

        return $this->cleanUtf8($text);
    }

    /**
     * Extract text from Microsoft Word DOCX files by reading word/document.xml.
     */
    private function extractDocxText(string $filePath): string
    {
        if (! class_exists('ZipArchive')) {
            return '';
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! $xml) {
            return '';
        }

        // Replace paragraph & break tags with newlines
        $clean = preg_replace('/<w:p[^>]*>/i', "\n", $xml) ?? $xml;
        $clean = preg_replace('/<w:br[^>]*>/i', "\n", $clean) ?? $clean;
        $clean = strip_tags($clean);

        return $this->cleanUtf8(trim($clean));
    }

    /**
     * Extract text from Microsoft PowerPoint PPTX files by reading ppt/slides/slide*.xml.
     */
    private function extractPptxText(string $filePath): string
    {
        if (! class_exists('ZipArchive')) {
            return '';
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $slideTexts = [];
        $slideIndex = 1;

        // Collect all sequential slide xml entries
        while ($slideIndex <= 200) {
            $xml = $zip->getFromName("ppt/slides/slide{$slideIndex}.xml");
            if (! $xml) {
                break;
            }

            preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/is', $xml, $matches);
            if (! empty($matches[1])) {
                $slideContent = trim(implode(' ', array_map('html_entity_decode', $matches[1])));
                if ($slideContent !== '') {
                    $slideTexts[] = "[Slide {$slideIndex}]\n".$slideContent;
                }
            }

            $slideIndex++;
        }

        // If sequential check found none or missed non-sequential slides, iterate all entries
        if (empty($slideTexts)) {
            $indexedSlides = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#ppt/slides/slide(\d+)\.xml#i', $name, $m)) {
                    $idx = (int) $m[1];
                    $xml = $zip->getFromIndex($i);
                    if ($xml) {
                        preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/is', $xml, $matches);
                        if (! empty($matches[1])) {
                            $slideContent = trim(implode(' ', array_map('html_entity_decode', $matches[1])));
                            if ($slideContent !== '') {
                                $indexedSlides[$idx] = "[Slide {$idx}]\n".$slideContent;
                            }
                        }
                    }
                }
            }
            if (! empty($indexedSlides)) {
                ksort($indexedSlides);
                $slideTexts = array_values($indexedSlides);
            }
        }

        $zip->close();

        if (empty($slideTexts)) {
            return '';
        }

        return $this->cleanUtf8(implode("\n\n", $slideTexts));
    }

    private function cleanUtf8(string $text): string
    {
        // Convert to UTF-8 and strip control chars (except newline/tabs)
        $utf8 = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $utf8) ?? $utf8;
    }
}
