<?php

namespace App\Services\Autochecker;

class ClassCheckHelpCatalog
{
    /**
     * Complete, versioned knowledge catalog for ClassCheck capabilities and UI navigation.
     *
     * @return array<string, array{title: string, route: string, buttons: array<string>, summary: string, steps: array<string>}>
     */
    public static function all(): array
    {
        return [
            'dashboard' => [
                'title' => 'Dashboard Overview',
                'route' => '/dashboard',
                'buttons' => ['+ Create Section', 'View Section', 'Take Attendance'],
                'summary' => 'Provides an at-a-glance overview of active classes, total enrolled students, overall attendance health, and upcoming schedule.',
                'steps' => [
                    'View your active course cards and quick KPI statistics.',
                    'Click "+ Create Section" to start a new course section.',
                    'Click on any section card to enter its interactive floor plan and grade ledger.',
                ],
            ],
            'seating_grid' => [
                'title' => 'Interactive Seating Grid & Floor Planner',
                'route' => '/sections/{id}',
                'buttons' => ['Live Seating Arrangement', 'Edit Section', 'Enrollment QR', 'Auto-Seat', 'Customize Blocks / Rearrange Desks', 'Unseat Student', 'Remove Student'],
                'summary' => 'Visual digital twin of the classroom layout matching the front teacher wall / whiteboard view.',
                'steps' => [
                    'Arrange custom desk blocks (left wing, center, right wing) with aisles.',
                    'Drag and drop students to chairs or click "Auto-Seat" to seat unassigned students automatically.',
                    'Click any chair to view student profile, attendance summary, or reassign seating.',
                ],
            ],
            'attendance' => [
                'title' => 'Visual Roll Call & Attendance Sessions',
                'route' => '/sections/{id}/attendance',
                'buttons' => ['Take Roll Call / Record Attendance', 'Mark All Present', 'Mark All Absent', 'Save Attendance', 'Show QR Code', 'Print Roster'],
                'summary' => '1-tap visual roll call system and QR attendance scanner.',
                'steps' => [
                    'Click "Take Roll Call" from the seating chart or attendance tab.',
                    'Tap any seat to toggle Present, Late, or Absent status.',
                    'Use "Mark All Present" for rapid bulk check-in, then tap absent chairs.',
                    'Click "Save Attendance" to sync attendance scores immediately into the gradebook.',
                ],
            ],
            'recitation' => [
                'title' => 'Oral Recitation & Random Caller',
                'route' => '/sections/{id}',
                'buttons' => ['Random Caller', 'Spin / Roll Student', 'Save Oral Grade', '+10 Oral Points'],
                'summary' => 'Fair random student picker with daily oral participation scoring (0-10) and weighted gradebook bonus points.',
                'steps' => [
                    'Click "Random Caller" on the section toolbar.',
                    'Spin or roll to pick a random seated student fairly.',
                    'Award oral performance score (0-10 points) and click "Save Oral Grade".',
                ],
            ],
            'autochecker' => [
                'title' => 'Bulk Activity Autochecker with Ollama',
                'route' => '/sections/{id}/assessments/{assessment_id}',
                'buttons' => ['Bulk Autochecker', 'Upload Submissions', 'Auto-Balance (100%)', 'Run Ollama Autochecker', 'Approve', 'Sync Grades to Gradebook', 'Save Scores'],
                'summary' => 'Local AI automated grading engine supporting source code, text, PDFs, and ZIP archives against structured rubrics.',
                'steps' => [
                    'Open an assessment and click "Bulk Autochecker".',
                    'Drag and drop student code files or a batch ZIP archive.',
                    'Configure rubric criteria (e.g. Functionality, Clean Code) and click "Auto-Balance (100%)".',
                    'Click "Run Ollama Autochecker" to generate drafts with criterion feedback and code line quotes.',
                    'Review and approve each student proposal in the evidence ledger, then click "Sync Grades to Gradebook".',
                ],
            ],
            'projects' => [
                'title' => 'Group Projects & Team Randomizer',
                'route' => '/sections/{id}/projects',
                'buttons' => ['+ Create Project', 'Randomize Groups', 'Assign Leader', 'Export Teams', 'Save Scores'],
                'summary' => 'Group work management with automated team creation, leadership assignment, and shared group grading.',
                'steps' => [
                    'Click "+ Create Project" and enter project requirements and max points.',
                    'Click "Randomize Groups" to balance teams evenly across enrolled students.',
                    'Record group presentation scores which propagate automatically to all members with optional individual overrides.',
                ],
            ],
            'modules' => [
                'title' => 'Course Modules, Syllabus & Attachments',
                'route' => '/sections/{id}/modules',
                'buttons' => ['+ Add Module', 'Upload Syllabus', 'Attach File', 'Add Presentation Link', 'Rearrange Modules'],
                'summary' => 'Organize weekly lessons, presentation slide links, module descriptions, and student handout attachments.',
                'steps' => [
                    'Navigate to "Course Modules" in the section navigation bar.',
                    'Click "+ Add Module" and enter week number, module title, and lesson objectives.',
                    'Attach PDF/Word handouts or paste slide presentation URLs for classroom projection.',
                ],
            ],
            'schedule' => [
                'title' => 'Weekly Schedule & Calendar',
                'route' => '/schedule',
                'buttons' => ['Weekly Schedule Matrix', 'Room Filter', 'Add Class Meeting'],
                'summary' => 'Consolidated weekly timetable displaying all section meeting times, days, and assigned classrooms.',
                'steps' => [
                    'Click "Schedule" in the main navigation sidebar to view your weekly timetable.',
                    'Inspect class time slots, room assignments, and conflict-free meeting blocks.',
                ],
            ],
            'grading_weights' => [
                'title' => 'Grading Categories & Weight Auto-Balancing',
                'route' => '/sections/{id}',
                'buttons' => ['Grading Weights', 'Auto-Balance (100%)', 'Save Weights'],
                'summary' => 'Configure custom category weights for Activities, Labs, Quizzes, Exams, Projects, Attendance, and Recitations.',
                'steps' => [
                    'Open section settings or gradebook and click "Grading Weights".',
                    'Set percentages for each component (e.g., Activity 20%, Quiz 20%, Exam 25%, Project 20%, Attendance 15%).',
                    'Click "Auto-Balance (100%)" to automatically normalize total weight to 100%, then click "Save Weights".',
                ],
            ],
            'gradebook' => [
                'title' => 'Gradebook Matrix & Deficiency Reports',
                'route' => '/sections/{id}/reports/gradebook',
                'buttons' => ['Gradebook Matrix', 'Student Deficiencies', 'Export to Excel', 'Export to CSV', 'Print Grade Sheet'],
                'summary' => 'Comprehensive gradebook with weighted calculations, letter grades, passing rates, and at-risk student tracking.',
                'steps' => [
                    'Open "Gradebook" to see the full matrix of student scores and weighted final averages.',
                    'Click "Student Deficiencies" to view or print deficiency notices for at-risk students.',
                    'Click "Export to Excel" or "Export to CSV" to generate spreadsheet reports.',
                ],
            ],
            'backup_export' => [
                'title' => 'Backup, Export & Database Archiving',
                'route' => '/settings/backup-export',
                'buttons' => ['Download JSON Backup', 'Export CSV Data', 'Restore Backup'],
                'summary' => 'Export complete offline database snapshots of all sections, students, grades, and attendance records.',
                'steps' => [
                    'Navigate to "Settings" > "Backup & Export".',
                    'Click "Download JSON Backup" to generate a full encrypted snapshot of your local teaching database.',
                    'Use CSV exports to extract raw student rosters and assessment matrices.',
                ],
            ],
            'ai_assistant' => [
                'title' => 'Octo AI Copilot & Hermes 3 Assistant',
                'route' => 'Global (Ctrl + J)',
                'buttons' => ['Ask Octo', 'Yes, Add to Class', 'Edit Details', 'Retry', 'Attach File'],
                'summary' => 'Local AI teaching assistant powered by Hermes 3 (8B) for smart class insights, autochecking, curriculum design, and grade analytics.',
                'steps' => [
                    'Press "Ctrl + J" or click the Octo icon in the top header anytime.',
                    'Ask natural questions about student grades, attendance, at-risk alerts, or request syllabus outlines.',
                    'Click action proposal buttons ("Yes, Add to Class") to insert generated assessments directly into the section gradebook.',
                ],
            ],
            'settings' => [
                'title' => 'System Settings & Academic Terms',
                'route' => '/settings/profile',
                'buttons' => ['Academic Term', 'Appearance', 'Backup & Export', 'Profile', 'Password'],
                'summary' => 'Manage active semesters, color themes, dark mode, and account security.',
                'steps' => [
                    'Go to "Settings" > "Academic Term" to set the active school year and semester.',
                    'Go to "Settings" > "Appearance" to customize light/dark/system themes.',
                ],
            ],
        ];
    }

    /**
     * Search help catalog by query keyword.
     */
    public static function search(string $query): array
    {
        $q = strtolower(trim($query));
        $all = self::all();

        if (empty($q)) {
            return $all;
        }

        $results = [];
        foreach ($all as $key => $item) {
            $haystack = strtolower($item['title'] . ' ' . $item['summary'] . ' ' . implode(' ', $item['buttons']) . ' ' . implode(' ', $item['steps']));
            if (str_contains($haystack, $q) || str_contains($key, $q)) {
                $results[$key] = $item;
            }
        }

        return ! empty($results) ? $results : $all;
    }
}
