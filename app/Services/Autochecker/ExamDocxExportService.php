<?php

namespace App\Services\Autochecker;

use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

class ExamDocxExportService
{
    /**
     * Generate a Microsoft Word (.docx) file from exam markdown content.
     *
     * @param  array  $options  [
     *                          'title' => string,
     *                          'subject_code' => string|null,
     *                          'subject_title' => string|null,
     *                          'section_name' => string|null,
     *                          'max_points' => float|int|null,
     *                          'exam_content' => string,
     *                          'answer_key' => string|null,
     *                          'mode' => 'student'|'both'|'answers',
     *                          ]
     * @return string Absolute file path to the generated .docx file in temporary storage.
     */
    public function generateDocx(array $options): string
    {
        $title = $options['title'] ?? 'Examination';
        $subjectCode = $options['subject_code'] ?? '';
        $subjectTitle = $options['subject_title'] ?? '';
        $sectionName = $options['section_name'] ?? '';
        $maxPoints = $options['max_points'] ?? null;
        $examContent = $options['exam_content'] ?? '';
        $answerKey = $options['answer_key'] ?? '';
        $mode = $options['mode'] ?? 'student';

        $phpWord = new PhpWord;

        // Document Properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('ClassCheck Examination System');
        $properties->setCompany('ClassCheck');
        $properties->setTitle($title);
        $properties->setDescription('Auto-generated examination paper');

        // Page setup: Standard Letter, 1-inch margins (1440 twips = 1 inch)
        $sectionStyle = [
            'orientation' => 'portrait',
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
            'headerHeight' => 720,
            'footerHeight' => 720,
        ];
        $section = $phpWord->addSection($sectionStyle);

        // Header & Footer
        $footer = $section->addFooter();
        $footer->addPreserveText('{PAGE} of {NUMPAGES}', ['size' => 9, 'color' => '6B7280', 'name' => 'Calibri'], ['alignment' => Jc::CENTER]);

        // If mode is 'answers', only render answer key
        if ($mode === 'answers') {
            $this->renderHeaderBlock($section, $title, $subjectCode, $subjectTitle, $sectionName, $maxPoints, true);
            $this->renderMarkdownToDocx($section, $answerKey ?: 'No answer key provided.', true);
        } else {
            // Render Student Exam
            $this->renderHeaderBlock($section, $title, $subjectCode, $subjectTitle, $sectionName, $maxPoints, false);
            $this->renderMarkdownToDocx($section, $examContent, false);

            // If mode is 'both', add page break and Answer Key
            if ($mode === 'both' && ! empty($answerKey)) {
                $section->addPageBreak();
                $this->renderAnswerKeyHeader($section, $title, $maxPoints);
                $this->renderMarkdownToDocx($section, $answerKey, true);
            }
        }

        // Save to temporary file
        $tempDir = storage_path('app/temp/docx');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $filename = 'exam_'.Str::random(16).'.docx';
        $fullPath = $tempDir.DIRECTORY_SEPARATOR.$filename;

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($fullPath);

        return $fullPath;
    }

    /**
     * Render the formal University / Department Examination Header Block.
     */
    protected function renderHeaderBlock(
        $section,
        string $title,
        ?string $subjectCode,
        ?string $subjectTitle,
        ?string $sectionName,
        float|int|null $maxPoints,
        bool $isAnswerKeyOnly
    ): void {
        // Course info line
        $subjectLine = trim(($subjectCode ? "{$subjectCode} - " : '').($subjectTitle ?: 'Academic Course'));
        if ($subjectLine) {
            $section->addText(
                mb_strtoupper($subjectLine),
                ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => '1E3A8A'],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
            );
        }

        // Exam Title
        $section->addText(
            mb_strtoupper($title),
            ['name' => 'Calibri', 'size' => 16, 'bold' => true, 'color' => '0F172A'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => $isAnswerKeyOnly ? 140 : 120]
        );

        if ($isAnswerKeyOnly) {
            $section->addText(
                'TEACHER ANSWER KEY & GRADING RUBRIC (CONFIDENTIAL)',
                ['name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => '047857'],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
            );

            // Divider rule
            $this->addHorizontalRule($section, '047857');

            return;
        }

        // Student Info Block Table (Name, Section, Date, Score)
        $table = $section->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'alignment' => Jc::CENTER,
            'cellMarginTop' => 60,
            'cellMarginBottom' => 60,
        ]);

        $scoreLabel = $maxPoints ? "Score: _____ / {$maxPoints}" : 'Score: _________';

        // Row 1: Student Name & Section
        $row1 = $table->addRow(300);
        $cell1 = $row1->addCell(6000);
        $r1 = $cell1->addTextRun(['spaceAfter' => 60]);
        $r1->addText('Student Name: ', ['name' => 'Calibri', 'size' => 10.5, 'bold' => true]);
        $r1->addText('____________________________________', ['name' => 'Calibri', 'size' => 10.5]);

        $cell2 = $row1->addCell(3500);
        $r2 = $cell2->addTextRun(['spaceAfter' => 60]);
        $r2->addText('Section: ', ['name' => 'Calibri', 'size' => 10.5, 'bold' => true]);
        $r2->addText($sectionName ? "{$sectionName}" : '_______________', ['name' => 'Calibri', 'size' => 10.5, 'underline' => $sectionName ? 'none' : 'single']);

        // Row 2: Date & Score
        $row2 = $table->addRow(300);
        $cell3 = $row2->addCell(6000);
        $r3 = $cell3->addTextRun(['spaceAfter' => 120]);
        $r3->addText('Date: ', ['name' => 'Calibri', 'size' => 10.5, 'bold' => true]);
        $r3->addText('____________________________________', ['name' => 'Calibri', 'size' => 10.5]);

        $cell4 = $row2->addCell(3500);
        $r4 = $cell4->addTextRun(['spaceAfter' => 120]);
        $r4->addText($scoreLabel, ['name' => 'Calibri', 'size' => 10.5, 'bold' => true, 'color' => '1E293B']);

        // Divider
        $this->addHorizontalRule($section, '94A3B8');
    }

    /**
     * Render distinct header for the Answer Key section when appended to student copy.
     */
    protected function renderAnswerKeyHeader($section, string $title, float|int|null $maxPoints): void
    {
        $section->addText(
            mb_strtoupper($title).' — ANSWER KEY & RUBRIC',
            ['name' => 'Calibri', 'size' => 14, 'bold' => true, 'color' => '047857'],
            ['alignment' => Jc::CENTER, 'spaceBefore' => 120, 'spaceAfter' => 40]
        );

        $section->addText(
            'CONFIDENTIAL · FOR INSTRUCTOR / GRADING USE ONLY',
            ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => '059669'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 180]
        );

        $this->addHorizontalRule($section, '059669');
    }

    /**
     * Add clean horizontal line separator.
     */
    protected function addHorizontalRule($section, string $hexColor = 'CBD5E1'): void
    {
        $section->addLine([
            'weight' => 1,
            'width' => 468, // pt (~6.5 inches)
            'height' => 0,
            'color' => $hexColor,
            'spaceAfter' => 160,
        ]);
    }

    /**
     * Parse structured exam markdown and render it into Word elements.
     */
    protected function renderMarkdownToDocx($section, string $markdown, bool $isAnswerKey = false): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown);
        $totalLines = count($lines);
        $inCodeBlock = false;
        $codeLines = [];
        $skipTopMetadata = ! $isAnswerKey;

        for ($i = 0; $i < $totalLines; $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            // Handle fenced code blocks (```python ... ```)
            if (preg_match('/^```/', $trimmed)) {
                if ($inCodeBlock) {
                    // End of code block: Render accumulated code lines
                    $this->renderCodeBlock($section, $codeLines);
                    $codeLines = [];
                    $inCodeBlock = false;
                } else {
                    $inCodeBlock = true;
                    $codeLines = [];
                }

                continue;
            }

            if ($inCodeBlock) {
                $codeLines[] = $line; // preserve indentation

                continue;
            }

            // Skip empty lines
            if ($trimmed === '') {
                continue;
            }

            // Skip horizontal rules
            if (preg_match('/^---+$|^\*\*\*+$|^___+$/', $trimmed)) {
                $this->addHorizontalRule($section, $isAnswerKey ? 'A7F3D0' : 'E2E8F0');

                continue;
            }

            // Skip redundant metadata headers in body since we rendered the formal header block
            if ($skipTopMetadata) {
                if (
                    preg_match('/^\*{0,2}Course Subject Code & Title\*{0,2}:/i', $trimmed) ||
                    preg_match('/^\*{0,2}Exam Title\*{0,2}:/i', $trimmed) ||
                    preg_match('/^\*{0,2}Student Name\*{0,2}:/i', $trimmed) ||
                    preg_match('/^\*{0,2}Section\*{0,2}:/i', $trimmed) ||
                    preg_match('/^\*{0,2}Date\*{0,2}:/i', $trimmed) ||
                    preg_match('/^\*{0,2}Score\*{0,2}:/i', $trimmed)
                ) {
                    continue;
                }
                // Once we reach General Directions or Part I, stop checking top metadata
                if (
                    preg_match('/General Directions/i', $trimmed) ||
                    preg_match('/Part\s+[I|V|X\d]+/i', $trimmed) ||
                    preg_match('/^#+\s+/i', $trimmed)
                ) {
                    $skipTopMetadata = false;
                }
            }

            // Markdown Headings: # Title, ## Part I: Identification, etc.
            if (preg_match('/^(#{1,4})\s*(.+)$/', $trimmed, $matches)) {
                $level = strlen($matches[1]);
                $headingText = trim($matches[2]);
                $this->renderHeading($section, $headingText, $level, $isAnswerKey);

                continue;
            }

            // Bold section titles without markdown hashes e.g. **Part I: Identification (10 pts)**
            if (preg_match('/^\*\*(Part\s+[I|V|X\d]+[^\*]+)\*\*$/i', $trimmed, $matches)) {
                $this->renderHeading($section, $matches[1], 2, $isAnswerKey);

                continue;
            }

            // Bold General Directions: **General Directions**:
            if (preg_match('/^\*\*(General Directions[^\*]*)\*\*:?$/i', $trimmed, $matches)) {
                $section->addText(
                    mb_strtoupper($matches[1]),
                    ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => '1E293B'],
                    ['spaceBefore' => 140, 'spaceAfter' => 60]
                );

                continue;
            }

            // Multiple choice choices: A) ..., B) ..., C) ..., D) ...
            if (preg_match('/^([A-D]\))\s+(.+)$/i', $trimmed, $matches)) {
                $choiceLetter = strtoupper($matches[1]);
                $choiceContent = $matches[2];
                $textRun = $section->addTextRun([
                    'leftIndent' => 480, // 0.33 inch
                    'spaceBefore' => 20,
                    'spaceAfter' => 40,
                ]);
                $textRun->addText($choiceLetter.' ', ['name' => 'Calibri', 'size' => 10.5, 'bold' => true, 'color' => '1E3A8A']);
                $this->appendFormattedInlineText($textRun, $choiceContent, ['name' => 'Calibri', 'size' => 10.5, 'color' => '1F2937']);

                continue;
            }

            // True / False checkbox statement: [ ] True  [ ] False or [ ] 1.
            if (preg_match('/^\[\s*\]\s*(.+)$/', $trimmed, $matches)) {
                $tfContent = $matches[1];
                $textRun = $section->addTextRun([
                    'leftIndent' => 360,
                    'spaceBefore' => 30,
                    'spaceAfter' => 50,
                ]);
                $textRun->addText('[   ] ', ['name' => 'Consolas', 'size' => 10.5, 'bold' => true, 'color' => '475569']);
                $this->appendFormattedInlineText($textRun, $tfContent, ['name' => 'Calibri', 'size' => 10.5, 'color' => '1F2937']);

                continue;
            }

            // Numbered items: 1. Question stem...
            if (preg_match('/^(\d+[\.\)])\s*(.+)$/', $trimmed, $matches)) {
                $itemNumber = $matches[1];
                $itemBody = $matches[2];
                $textRun = $section->addTextRun([
                    'leftIndent' => 240,
                    'hangingIndent' => 240,
                    'spaceBefore' => 80,
                    'spaceAfter' => 50,
                ]);
                $textRun->addText($itemNumber.' ', ['name' => 'Calibri', 'size' => 10.5, 'bold' => true, 'color' => '0F172A']);
                $this->appendFormattedInlineText($textRun, $itemBody, ['name' => 'Calibri', 'size' => 10.5, 'color' => '1F2937']);

                continue;
            }

            // Regular paragraph line
            $textRun = $section->addTextRun([
                'spaceBefore' => 30,
                'spaceAfter' => 50,
                'lineHeight' => 1.15,
            ]);
            $this->appendFormattedInlineText($textRun, $trimmed, ['name' => 'Calibri', 'size' => 10.5, 'color' => '1F2937']);
        }

        // Flush any unclosed code block
        if ($inCodeBlock && ! empty($codeLines)) {
            $this->renderCodeBlock($section, $codeLines);
        }
    }

    /**
     * Render Heading 1, 2, 3 with appropriate point badges and colors.
     */
    protected function renderHeading($section, string $text, int $level, bool $isAnswerKey): void
    {
        // Clean markdown bold tokens if present
        $cleanText = trim(str_replace(['**', '##'], '', $text));

        if ($level === 1) {
            $section->addText(
                mb_strtoupper($cleanText),
                ['name' => 'Calibri', 'size' => 14, 'bold' => true, 'color' => $isAnswerKey ? '047857' : '0F172A'],
                ['alignment' => Jc::CENTER, 'spaceBefore' => 160, 'spaceAfter' => 80]
            );
        } elseif ($level === 2) {
            // Part Heading (e.g. Part I: Identification (10 pts))
            $section->addText(
                $cleanText,
                [
                    'name' => 'Calibri',
                    'size' => 12,
                    'bold' => true,
                    'color' => $isAnswerKey ? '065F46' : '1E3A8A',
                ],
                [
                    'spaceBefore' => 180,
                    'spaceAfter' => 80,
                    'borderBottomSize' => 6,
                    'borderBottomColor' => $isAnswerKey ? 'A7F3D0' : 'BFDBFE',
                ]
            );
        } else {
            // Subheading
            $section->addText(
                $cleanText,
                ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => '334155'],
                ['spaceBefore' => 100, 'spaceAfter' => 40]
            );
        }
    }

    /**
     * Render code snippets inside a shaded single-cell Word table with Consolas font.
     */
    protected function renderCodeBlock($section, array $lines): void
    {
        $table = $section->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'alignment' => Jc::CENTER,
            'cellMarginTop' => 100,
            'cellMarginBottom' => 100,
            'cellMarginLeft' => 140,
            'cellMarginRight' => 140,
        ]);

        $row = $table->addRow();
        $cell = $row->addCell(9500, [
            'bgColor' => 'F8FAFC',
            'borderTopSize' => 6,
            'borderBottomSize' => 6,
            'borderLeftSize' => 18, // thicker left accent bar
            'borderRightSize' => 6,
            'borderTopColor' => 'E2E8F0',
            'borderBottomColor' => 'E2E8F0',
            'borderLeftColor' => '3B82F6', // Blue left border accent
            'borderRightColor' => 'E2E8F0',
        ]);

        foreach ($lines as $line) {
            $cell->addText(
                $line === '' ? ' ' : $line,
                ['name' => 'Consolas', 'size' => 9.5, 'color' => '0F172A'],
                ['spaceBefore' => 10, 'spaceAfter' => 10]
            );
        }

        // Small spacer after table
        $section->addText('', [], ['spaceAfter' => 80]);
    }

    /**
     * Parse inline markdown (**bold**, *italic*, `code`) into formatted Word TextRun runs.
     */
    protected function appendFormattedInlineText($textRun, string $text, array $baseFontStyle): void
    {
        // Split by tokens: **bold**, *italic*, `code`
        $pattern = '/(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)/';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, '**') && str_ends_with($part, '**') && strlen($part) >= 4) {
                $boldContent = substr($part, 2, -2);
                $style = array_merge($baseFontStyle, ['bold' => true]);
                $textRun->addText($boldContent, $style);
            } elseif (str_starts_with($part, '*') && str_ends_with($part, '*') && strlen($part) >= 2) {
                $italicContent = substr($part, 1, -1);
                $style = array_merge($baseFontStyle, ['italic' => true]);
                $textRun->addText($italicContent, $style);
            } elseif (str_starts_with($part, '`') && str_ends_with($part, '`') && strlen($part) >= 2) {
                $codeContent = substr($part, 1, -1);
                $style = array_merge($baseFontStyle, [
                    'name' => 'Consolas',
                    'size' => 9.5,
                    'color' => '09090B',
                ]);
                $textRun->addText($codeContent, $style);
            } else {
                $textRun->addText($part, $baseFontStyle);
            }
        }
    }
}
