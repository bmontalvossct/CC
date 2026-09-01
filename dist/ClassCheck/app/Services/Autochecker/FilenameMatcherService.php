<?php

namespace App\Services\Autochecker;

use App\Models\Student;
use Illuminate\Support\Collection;

class FilenameMatcherService
{
    /**
     * Match a collection of filenames against section students.
     *
     * @param Collection<int, Student> $students
     * @param array<int, string> $filenames
     * @return array<string, array{student_id: int|null, student_number: string|null, student_name: string|null, confidence: float, match_type: string, reason: string}>
     */
    public function matchMultiple(Collection $students, array $filenames): array
    {
        $results = [];
        foreach ($filenames as $filename) {
            $results[$filename] = $this->matchSingle($students, $filename);
        }

        return $results;
    }

    /**
     * Match a single filename against students.
     *
     * @param Collection<int, Student> $students
     * @param string $filename
     * @return array{student_id: int|null, student_number: string|null, student_name: string|null, confidence: float, match_type: string, reason: string}
     */
    public function matchSingle(Collection $students, string $filename): array
    {
        $cleanBase = pathinfo($filename, PATHINFO_FILENAME);
        $normalizedFilename = $this->normalizeText($cleanBase);

        // 1. Check Student Number match (exact or stripped hyphens)
        foreach ($students as $student) {
            if (! empty($student->student_number)) {
                $rawNum = trim($student->student_number);
                $strippedNum = str_replace(['-', ' ', '_'], '', $rawNum);

                if (
                    stripos($cleanBase, $rawNum) !== false ||
                    (! empty($strippedNum) && stripos(str_replace(['-', ' ', '_'], '', $cleanBase), $strippedNum) !== false)
                ) {
                    return [
                        'student_id' => $student->id,
                        'student_number' => $student->student_number,
                        'student_name' => $student->full_name,
                        'confidence' => 1.0,
                        'match_type' => 'student_number',
                        'reason' => "Matched Student ID ({$student->student_number})",
                    ];
                }
            }
        }

        // 2. Check Full Name match (both Last Name and First Name present)
        $candidates = [];
        foreach ($students as $student) {
            $last = $this->normalizeText($student->last_name ?? '');
            $first = $this->normalizeText($student->first_name ?? '');

            if ($last !== '' && $first !== '') {
                $hasLast = $this->containsWord($normalizedFilename, $last);
                $hasFirst = $this->containsWord($normalizedFilename, $first);

                if ($hasLast && $hasFirst) {
                    return [
                        'student_id' => $student->id,
                        'student_number' => $student->student_number,
                        'student_name' => $student->full_name,
                        'confidence' => 0.95,
                        'match_type' => 'full_name',
                        'reason' => "Matched Full Name ({$student->full_name})",
                    ];
                }

                if ($hasLast) {
                    $candidates[] = $student;
                }
            }
        }

        // 3. Unique Last Name match
        if (count($candidates) === 1) {
            $matched = $candidates[0];
            return [
                'student_id' => $matched->id,
                'student_number' => $matched->student_number,
                'student_name' => $matched->full_name,
                'confidence' => 0.80,
                'match_type' => 'last_name',
                'reason' => "Matched Unique Last Name ({$matched->last_name})",
            ];
        }

        // 4. Fuzzy / Levenshtein similarity matching
        $bestMatch = null;
        $highestSim = 0;

        foreach ($students as $student) {
            $fullName = $this->normalizeText($student->full_name);
            similar_text($normalizedFilename, $fullName, $percent);

            if ($percent > $highestSim && $percent >= 60.0) {
                $highestSim = $percent;
                $bestMatch = $student;
            }
        }

        if ($bestMatch !== null) {
            $conf = round($highestSim / 100, 2);
            return [
                'student_id' => $bestMatch->id,
                'student_number' => $bestMatch->student_number,
                'student_name' => $bestMatch->full_name,
                'confidence' => $conf,
                'match_type' => 'fuzzy',
                'reason' => "Fuzzy match (~{$highestSim}% similarity with {$bestMatch->full_name})",
            ];
        }

        return [
            'student_id' => null,
            'student_number' => null,
            'student_name' => null,
            'confidence' => 0.0,
            'match_type' => 'none',
            'reason' => 'No student matched from filename',
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = strtolower($text);
        return preg_replace('/[^a-z0-9]/', ' ', $text) ?? '';
    }

    private function containsWord(string $haystack, string $needle): bool
    {
        if (empty($needle)) {
            return false;
        }

        // Match whole word or direct substring
        return (bool) preg_match('/\b' . preg_quote($needle, '/') . '\b/i', $haystack)
            || str_contains($haystack, $needle);
    }
}
