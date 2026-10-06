<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SectionFolderService
{
    public const CATEGORIES = ['activities', 'quiz', 'report', 'project'];

    /**
     * Get the standardized, filesystem-safe folder name for a section.
     * Format: "{subject_code} - {name}"
     */
    public function getFolderName(Section $section): string
    {
        $code = $this->sanitizeFileName($section->subject_code ?: 'SECTION');
        $name = $this->sanitizeFileName($section->name ?: 'GENERAL');

        return "{$code} - {$name}";
    }

    /**
     * Sanitize a string for safe directory/file naming across Windows and Linux.
     */
    public function sanitizeFileName(string $name): string
    {
        // Replace invalid filename characters \ / : * ? " < > | with hyphen
        $sanitized = preg_replace('/[\\\\\/:\*\?"<>\|]/', '-', trim($name));
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);
        $sanitized = trim($sanitized, " .\t\n\r\0\x0B");

        return $sanitized ?: 'unnamed';
    }

    /**
     * Get the relative disk path for a section root directory.
     */
    public function getSectionRelativePath(Section $section): string
    {
        return 'sections/'.$this->getFolderName($section);
    }

    /**
     * Get the relative disk path for a specific category inside a section.
     */
    public function getCategoryRelativePath(Section $section, string $category): string
    {
        $category = $this->normalizeCategory($category);

        return $this->getSectionRelativePath($section).'/'.$category;
    }

    /**
     * Normalize category string into one of the 4 supported folders:
     * - activities
     * - quiz
     * - report
     * - project
     */
    public function normalizeCategory(string $category): string
    {
        $cat = strtolower(trim($category));

        return match ($cat) {
            'activity', 'activities', 'lab', 'laboratory', 'laboratories', 'group_activity' => 'activities',
            'quiz', 'quizzes', 'exam', 'exams', 'test', 'tests' => 'quiz',
            'report', 'reports', 'reporting', 'export', 'exports', 'summary' => 'report',
            'project', 'projects' => 'project',
            default => 'activities',
        };
    }

    /**
     * Map assessment type to folder category.
     * - 'activity', 'laboratory' -> 'activities'
     * - 'quiz', 'exam' -> 'quiz'
     */
    public function getCategoryForAssessmentType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'quiz', 'exam' => 'quiz',
            default => 'activities',
        };
    }

    /**
     * Map project type to folder category.
     * - 'project' -> 'project'
     * - 'reporting' -> 'report'
     * - 'group_activity' -> 'activities'
     */
    public function getCategoryForProjectType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'reporting' => 'report',
            'group_activity' => 'activities',
            default => 'project',
        };
    }

    /**
     * Ensure the section folder and all 4 subfolders (activities, quiz, report, project) exist.
     */
    public function ensureSectionFolders(Section $section): array
    {
        $folderName = $this->getFolderName($section);
        $sectionRelPath = 'sections/'.$folderName;
        $createdPaths = [];

        foreach (self::CATEGORIES as $category) {
            $catRelPath = "{$sectionRelPath}/{$category}";

            // 1. Ensure on local storage disk
            Storage::disk('local')->makeDirectory($catRelPath);

            // 2. Ensure physical directories exist on disk for direct Explorer access
            $physicalPaths = [
                storage_path("app/private/{$catRelPath}"),
                storage_path("app/{$catRelPath}"),
            ];

            foreach ($physicalPaths as $physPath) {
                try {
                    File::ensureDirectoryExists($physPath, 0755, true);
                } catch (\Throwable) {
                    // Ignore permission glitches if already handled by Storage
                }
            }

            $createdPaths[$category] = $catRelPath;
        }

        return $createdPaths;
    }

    /**
     * Ensure folders exist for all currently included sections in the database.
     */
    public function ensureAllSectionFolders(): int
    {
        $count = 0;
        $sections = Section::all();

        foreach ($sections as $section) {
            $this->ensureSectionFolders($section);
            $count++;
        }

        return $count;
    }

    /**
     * Handle renaming of a section folder if subject_code or name was updated.
     */
    public function handleSectionRenamed(Section $section, ?string $oldCode, ?string $oldName): void
    {
        if (! $oldCode && ! $oldName) {
            $this->ensureSectionFolders($section);

            return;
        }

        $oldFolderName = $this->sanitizeFileName($oldCode ?: 'SECTION').' - '.$this->sanitizeFileName($oldName ?: 'GENERAL');
        $newFolderName = $this->getFolderName($section);

        if ($oldFolderName === $newFolderName) {
            $this->ensureSectionFolders($section);

            return;
        }

        $oldRel = 'sections/'.$oldFolderName;
        $newRel = 'sections/'.$newFolderName;

        if (Storage::disk('local')->exists($oldRel)) {
            try {
                Storage::disk('local')->move($oldRel, $newRel);
            } catch (\Throwable) {
                // If move fails, ensure new folder is created
                $this->ensureSectionFolders($section);
            }
        }

        $this->ensureSectionFolders($section);
    }

    /**
     * Generate the standardized details filename for an assessment or project.
     * Format: "{Type}_details {number}.{extension}" or "{Type}_details.{extension}"
     */
    public function generateDetailsFileName(string $type, ?string $numberString = null, ?string $title = null, string $extension = 'bin'): string
    {
        $prefix = match (strtolower(trim($type))) {
            'quiz' => 'Quiz_details',
            'exam' => 'Exam_details',
            'laboratory', 'lab' => 'Lab_details',
            'reporting', 'report' => 'Report_details',
            'project' => 'Project_details',
            default => 'Activity_details',
        };

        $num = $this->extractNumber($numberString, $title);
        $ext = ltrim($extension, '.');

        return $num !== null ? "{$prefix} {$num}.{$ext}" : "{$prefix}.{$ext}";
    }

    /**
     * Generate the standardized student output filename for individual tasks.
     * Format: "{Lastname}_{Activity/Quiz/Exam/Project} {number}.{extension}"
     */
    public function generateStudentOutputFileName(?string $lastName, string $type, ?string $numberString = null, ?string $title = null, string $extension = 'bin'): string
    {
        $cleanLast = $this->sanitizeFileName($lastName ?: 'Student');
        $taskLabel = $this->getTaskLabel($type, $numberString, $title);
        $ext = ltrim($extension, '.');

        return "{$cleanLast}_{$taskLabel}.{$ext}";
    }

    /**
     * Generate the standardized group output filename for group tasks.
     * Format: "Group {groupNumber}_{Activity/Report/Project} {number}.{extension}"
     */
    public function generateGroupOutputFileName(int|string $groupNumber, string $type, ?string $numberString = null, ?string $title = null, string $extension = 'bin'): string
    {
        $groupLabel = "Group {$groupNumber}";
        $taskLabel = $this->getTaskLabel($type, $numberString, $title);
        $ext = ltrim($extension, '.');

        return "{$groupLabel}_{$taskLabel}.{$ext}";
    }

    /**
     * Extract a task designation label (e.g. "Activity 1", "Quiz 2", "Project 1", "Report 1").
     */
    public function getTaskLabel(string $type, ?string $numberString = null, ?string $title = null): string
    {
        $typeLabel = match (strtolower(trim($type))) {
            'quiz' => 'Quiz',
            'exam' => 'Exam',
            'laboratory', 'lab' => 'Lab',
            'reporting', 'report' => 'Report',
            'project' => 'Project',
            default => 'Activity',
        };

        $num = $this->extractNumber($numberString, $title);

        return $num !== null ? "{$typeLabel} {$num}" : $typeLabel;
    }

    /**
     * Helper to extract numeric identifier from numberString or title.
     */
    public function extractNumber(?string $numberString, ?string $title): ?string
    {
        if ($numberString && preg_match('/(\d+(?:\.\d+)?)/', $numberString, $matches)) {
            return $matches[1];
        }

        if ($title && preg_match('/(?:#|no\.?|activity|quiz|exam|lab|report|project)?\s*(\d+(?:\.\d+)?)/i', $title, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Store an uploaded assessment details attachment with standardized naming into the section's category folder.
     */
    public function storeAssessmentAttachment(Section $section, UploadedFile $file, string $type, ?string $assessmentNumber = null, ?string $title = null): array
    {
        $category = $this->getCategoryForAssessmentType($type);
        $this->ensureSectionFolders($section);

        $targetDir = $this->getCategoryRelativePath($section, $category);
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $customName = $this->generateDetailsFileName($type, $assessmentNumber, $title, $extension);

        $path = $file->storeAs($targetDir, $customName, 'local');

        return [
            'path' => $path,
            'name' => $customName,
            'mime' => $file->getMimeType(),
        ];
    }

    /**
     * Store an uploaded project details attachment with standardized naming into the section's category folder.
     */
    public function storeProjectAttachment(Section $section, UploadedFile $file, string $type, ?string $projectNumber = null, ?string $title = null): array
    {
        $category = $this->getCategoryForProjectType($type);
        $this->ensureSectionFolders($section);

        $targetDir = $this->getCategoryRelativePath($section, $category);
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $customName = $this->generateDetailsFileName($type, $projectNumber, $title, $extension);

        $path = $file->storeAs($targetDir, $customName, 'local');

        return [
            'path' => $path,
            'name' => $customName,
            'mime' => $file->getMimeType(),
        ];
    }

    /**
     * Store an uploaded student output file for an individual assessment (Activity, Quiz, Exam, Lab).
     * Renamed as: "{Lastname}_{Activity/Quiz/Exam/Lab} {number}.{ext}"
     */
    public function storeStudentAssessmentOutput(Section $section, Assessment $assessment, Student $student, UploadedFile $file): array
    {
        $category = $this->getCategoryForAssessmentType($assessment->type);
        $this->ensureSectionFolders($section);

        $task = $this->getTaskLabel($assessment->type, $assessment->assessment_number, $assessment->title);
        $folder = $this->sanitizeFileName($section->name.' - '.($section->subject_code ?: $section->subject_title).' - '.$task.' - student outputs');
        $targetDir = 'sections/section-'.$section->id.'/assessment-'.$assessment->id.'/'.$folder;
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $customName = $this->generateStudentOutputFileName(
            $student->last_name,
            $assessment->type,
            $assessment->assessment_number,
            $assessment->title,
            $extension
        );

        $customName = $student->id.'-'.Str::uuid().'-'.$customName;
        $mime = $file->getMimeType();
        $path = $file->storeAs($targetDir, $customName, 'local');
        if (! $path) {
            throw new \RuntimeException('The output could not be stored. Please retry.');
        }

        return [
            'path' => $path,
            'name' => $customName,
            'mime' => $mime,
        ];
    }

    /**
     * Store an uploaded group output file for a group project, activity, or report.
     * Renamed as: "Group {groupNumber}_{Activity/Report/Project} {number}.{ext}"
     */
    public function storeGroupProjectOutput(Section $section, Project $project, ProjectGroup $group, UploadedFile $file): array
    {
        $category = $this->getCategoryForProjectType($project->type);
        $this->ensureSectionFolders($section);

        $task = $this->getTaskLabel($project->type, $project->project_number, $project->title);
        $folder = $this->sanitizeFileName($section->name.' - '.($section->subject_code ?: $section->subject_title).' - '.$task.' - student outputs');
        $targetDir = 'sections/section-'.$section->id.'/project-'.$project->id.'/'.$folder;
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $customName = $this->generateGroupOutputFileName(
            $group->group_number,
            $project->type,
            $project->project_number,
            $project->title,
            $extension
        );

        $customName = $group->id.'-'.Str::uuid().'-'.$customName;
        $mime = $file->getMimeType();
        $path = $file->storeAs($targetDir, $customName, 'local');
        if (! $path) {
            throw new \RuntimeException('The output could not be stored. Please retry.');
        }

        return [
            'path' => $path,
            'name' => $customName,
            'mime' => $mime,
        ];
    }

    /**
     * Store an uploaded individual student output file for a project/report.
     * Renamed as: "{Lastname}_{Project/Report} {number}.{ext}"
     */
    public function storeStudentProjectOutput(Section $section, Project $project, Student $student, UploadedFile $file): array
    {
        $category = $this->getCategoryForProjectType($project->type);
        $this->ensureSectionFolders($section);

        $task = $this->getTaskLabel($project->type, $project->project_number, $project->title);
        $folder = $this->sanitizeFileName($section->name.' - '.($section->subject_code ?: $section->subject_title).' - '.$task.' - student outputs');
        $targetDir = 'sections/section-'.$section->id.'/project-'.$project->id.'/'.$folder;
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $customName = $this->generateStudentOutputFileName(
            $student->last_name,
            $project->type,
            $project->project_number,
            $project->title,
            $extension
        );

        $customName = $student->id.'-'.Str::uuid().'-'.$customName;
        $mime = $file->getMimeType();
        $path = $file->storeAs($targetDir, $customName, 'local');
        if (! $path) {
            throw new \RuntimeException('The output could not be stored. Please retry.');
        }

        return [
            'path' => $path,
            'name' => $customName,
            'mime' => $mime,
        ];
    }

    /**
     * Save a generated report file directly into the section's report folder.
     */
    public function saveReportFile(Section $section, string $filename, string $content): string
    {
        $this->ensureSectionFolders($section);
        $targetPath = $this->getCategoryRelativePath($section, 'report').'/'.$filename;

        Storage::disk('local')->put($targetPath, $content);

        return $targetPath;
    }

    /**
     * Get the absolute physical path to a section's folder or specific category.
     */
    public function getPhysicalPath(Section $section, ?string $category = null): string
    {
        $this->ensureSectionFolders($section);

        $relPath = $category
            ? $this->getCategoryRelativePath($section, $category)
            : $this->getSectionRelativePath($section);

        // Check local storage root
        if (Storage::disk('local')->exists($relPath)) {
            return Storage::disk('local')->path($relPath);
        }

        $candidates = [
            storage_path("app/private/{$relPath}"),
            storage_path("app/{$relPath}"),
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }

        return Storage::disk('local')->path($relPath);
    }
}
