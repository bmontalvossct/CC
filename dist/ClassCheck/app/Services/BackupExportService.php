<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CourseModule;
use App\Models\LayoutBlock;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\Recitation;
use App\Models\Seat;
use App\Models\Section;
use App\Models\SectionSchedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupExportService
{
    /**
     * Export all data for a specific user as a structured array.
     */
    public function exportUserData(User $user): array
    {
        $terms = AcademicTerm::where('user_id', $user->id)->get();
        $sections = Section::where('user_id', $user->id)
            ->with([
                'schedules',
                'layoutBlocks.seats',
                'students.seat',
                'attendanceSessions.records',
                'recitations',
                'assessments.scores',
                'projects.groups',
                'courseModules',
            ])
            ->get();

        $sectionsData = [];
        foreach ($sections as $section) {
            $studentsData = [];
            foreach ($section->students as $student) {
                $photoBase64 = null;
                if ($student->photo_path && Storage::disk('local')->exists($student->photo_path)) {
                    try {
                        $photoData = Storage::disk('local')->get($student->photo_path);
                        $mime = Storage::disk('local')->mimeType($student->photo_path) ?: 'image/jpeg';
                        $photoBase64 = 'data:'.$mime.';base64,'.base64_encode($photoData);
                    } catch (\Throwable $e) {
                        // ignore photo encode errors
                    }
                }

                $studentsData[] = [
                    'id' => $student->id,
                    'student_number' => $student->student_number,
                    'first_name' => $student->first_name,
                    'middle_name' => $student->middle_name,
                    'last_name' => $student->last_name,
                    'is_active' => (bool) $student->is_active,
                    'photo_base64' => $photoBase64,
                    'photo_path' => $student->photo_path,
                    'created_at' => (string) $student->created_at,
                    'updated_at' => (string) $student->updated_at,
                ];
            }

            $blocksData = [];
            foreach ($section->layoutBlocks as $block) {
                $seatsData = [];
                foreach ($block->seats as $seat) {
                    $seatsData[] = [
                        'id' => $seat->id,
                        'student_id' => $seat->student_id,
                        'row_number' => $seat->row_number,
                        'column_number' => $seat->column_number,
                        'label' => $seat->label,
                        'is_disabled' => (bool) $seat->is_disabled,
                    ];
                }
                $blocksData[] = [
                    'id' => $block->id,
                    'label' => $block->label,
                    'block_row' => $block->block_row,
                    'block_column' => $block->block_column,
                    'internal_rows' => $block->internal_rows,
                    'internal_columns' => $block->internal_columns,
                    'aisles' => $block->aisles,
                    'seats' => $seatsData,
                ];
            }

            $attendanceData = [];
            foreach ($section->attendanceSessions as $session) {
                $recordsData = [];
                foreach ($session->records as $record) {
                    $recordsData[] = [
                        'student_id' => $record->student_id,
                        'status' => $record->status,
                        'attended_minutes' => $record->attended_minutes,
                        'remarks' => $record->remarks,
                    ];
                }
                $attendanceData[] = [
                    'id' => $session->id,
                    'session_date' => (string) $session->session_date,
                    'starts_at' => (string) $session->starts_at,
                    'ends_at' => (string) $session->ends_at,
                    'duration_minutes' => $session->duration_minutes,
                    'records' => $recordsData,
                ];
            }

            $recitationsData = [];
            foreach ($section->recitations as $recitation) {
                $recitationsData[] = [
                    'student_id' => $recitation->student_id,
                    'score' => (float) $recitation->score,
                    'conducted_on' => (string) $recitation->conducted_on,
                    'accuracy' => $recitation->accuracy,
                    'delivery' => $recitation->delivery,
                    'comments' => $recitation->comments,
                ];
            }

            $assessmentsData = [];
            foreach ($section->assessments as $assessment) {
                $scoresData = [];
                foreach ($assessment->scores as $score) {
                    $scoresData[] = [
                        'student_id' => $score->student_id,
                        'score' => (float) $score->score,
                        'remarks' => $score->remarks,
                    ];
                }
                $assessmentsData[] = [
                    'id' => $assessment->id,
                    'type' => $assessment->type,
                    'assessment_number' => $assessment->assessment_number,
                    'title' => $assessment->title,
                    'description' => $assessment->description,
                    'conducted_on' => (string) $assessment->conducted_on,
                    'max_points' => (float) $assessment->max_points,
                    'scores' => $scoresData,
                ];
            }

            $projectsData = [];
            foreach ($section->projects as $project) {
                $groupsData = [];
                foreach ($project->groups as $group) {
                    $groupsData[] = [
                        'group_number' => $group->group_number,
                        'name' => $group->name,
                        'description' => $group->description,
                        'leader_student_id' => $group->leader_student_id,
                        'student_ids' => $group->student_ids,
                        'score' => (float) $group->score,
                    ];
                }
                $projectsData[] = [
                    'id' => $project->id,
                    'title' => $project->title,
                    'project_number' => $project->project_number,
                    'format' => $project->format,
                    'max_score' => (float) $project->max_score,
                    'due_at' => (string) $project->due_at,
                    'groups' => $groupsData,
                ];
            }

            $modulesData = [];
            foreach ($section->courseModules as $module) {
                $modulesData[] = [
                    'id' => $module->id,
                    'module_number' => $module->module_number ?? ('M'.$module->sort_order),
                    'title' => $module->title,
                    'description' => $module->description,
                    'link_url' => $module->link_url ?? $module->url ?? null,
                    'sort_order' => $module->sort_order,
                    'file_name' => $module->file_name,
                    'file_path' => $module->file_path,
                ];
            }

            $sectionsData[] = [
                'id' => $section->id,
                'academic_term_id' => $section->academic_term_id,
                'subject_code' => $section->subject_code,
                'subject_title' => $section->subject_title,
                'name' => $section->name,
                'enrollment_token' => $section->enrollment_token,
                'is_enrollment_open' => (bool) $section->is_enrollment_open,
                'grading_weights' => $section->grading_weights,
                'archived_at' => (string) $section->archived_at,
                'schedules' => $section->schedules->map(fn ($s) => [
                    'day_of_week' => $s->day_of_week,
                    'starts_at' => (string) $s->starts_at,
                    'ends_at' => (string) $s->ends_at,
                    'room' => $s->room,
                    'schedule_type' => $s->schedule_type,
                ])->toArray(),
                'students' => $studentsData,
                'layout_blocks' => $blocksData,
                'attendance_sessions' => $attendanceData,
                'recitations' => $recitationsData,
                'assessments' => $assessmentsData,
                'projects' => $projectsData,
                'course_modules' => $modulesData,
            ];
        }

        return [
            'meta' => [
                'app' => 'ClassCheck',
                'version' => '1.0.0',
                'exported_at' => now()->toIso8601String(),
                'database_driver' => DB::connection()->getDriverName(),
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'counts' => [
                    'terms' => $terms->count(),
                    'sections' => count($sectionsData),
                ],
            ],
            'academic_terms' => $terms->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'school_year' => $t->school_year,
                'starts_on' => (string) $t->starts_on,
                'ends_on' => (string) $t->ends_on,
                'is_current' => (bool) $t->is_current,
            ])->toArray(),
            'sections' => $sectionsData,
        ];
    }

    /**
     * Restore data from a backup array for a user.
     */
    public function restoreUserData(User $user, array $backupData, bool $cleanReplace = false): array
    {
        if (!isset($backupData['meta']['app']) || $backupData['meta']['app'] !== 'ClassCheck') {
            throw new \InvalidArgumentException('Invalid backup archive. File must be a valid ClassCheck export.');
        }

        $termMap = []; // old_id => new_id
        $studentMap = []; // old_id => new_id
        $stats = [
            'terms_imported' => 0,
            'sections_imported' => 0,
            'students_imported' => 0,
            'attendance_sessions_imported' => 0,
            'assessments_imported' => 0,
            'recitations_imported' => 0,
        ];

        DB::transaction(function () use ($user, $backupData, $cleanReplace, &$termMap, &$studentMap, &$stats) {
            if ($cleanReplace) {
                // Delete user's existing terms and sections
                Section::where('user_id', $user->id)->forceDelete();
                AcademicTerm::where('user_id', $user->id)->delete();
            }

            // 1. Restore Academic Terms
            foreach ($backupData['academic_terms'] ?? [] as $termData) {
                $term = AcademicTerm::firstOrCreate([
                    'user_id' => $user->id,
                    'name' => $termData['name'],
                    'school_year' => $termData['school_year'],
                ], [
                    'starts_on' => $termData['starts_on'],
                    'ends_on' => $termData['ends_on'],
                    'is_current' => $termData['is_current'] ?? false,
                ]);

                if (isset($termData['id'])) {
                    $termMap[$termData['id']] = $term->id;
                }
                $stats['terms_imported']++;
            }

            // 2. Restore Sections
            foreach ($backupData['sections'] ?? [] as $secData) {
                $newTermId = null;
                if (isset($secData['academic_term_id']) && isset($termMap[$secData['academic_term_id']])) {
                    $newTermId = $termMap[$secData['academic_term_id']];
                } else {
                    $newTermId = AcademicTerm::where('user_id', $user->id)->value('id');
                }

                $section = Section::create([
                    'user_id' => $user->id,
                    'academic_term_id' => $newTermId,
                    'subject_code' => $secData['subject_code'],
                    'subject_title' => $secData['subject_title'] ?? $secData['subject_code'],
                    'name' => $secData['name'],
                    'enrollment_token' => $secData['enrollment_token'] ?? null,
                    'is_enrollment_open' => $secData['is_enrollment_open'] ?? true,
                    'grading_weights' => $secData['grading_weights'] ?? null,
                    'archived_at' => !empty($secData['archived_at']) ? $secData['archived_at'] : null,
                ]);
                $stats['sections_imported']++;

                // Schedules
                foreach ($secData['schedules'] ?? [] as $sch) {
                    $section->schedules()->create($sch);
                }

                // Students
                foreach ($secData['students'] ?? [] as $stData) {
                    $photoPath = $stData['photo_path'] ?? null;
                    if (!empty($stData['photo_base64'])) {
                        try {
                            if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $stData['photo_base64'], $matches)) {
                                $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                                $decoded = base64_decode($matches[2]);
                                $photoPath = 'classcheck/students/'.\Illuminate\Support\Str::random(40).'.'.$ext;
                                Storage::disk('local')->put($photoPath, $decoded);
                            }
                        } catch (\Throwable $e) {
                            // keep existing or null
                        }
                    }

                    $student = $section->students()->create([
                        'student_number' => $stData['student_number'] ?? null,
                        'first_name' => $stData['first_name'],
                        'middle_name' => $stData['middle_name'] ?? null,
                        'last_name' => $stData['last_name'],
                        'is_active' => $stData['is_active'] ?? true,
                        'photo_path' => $photoPath,
                    ]);

                    if (isset($stData['id'])) {
                        $studentMap[$stData['id']] = $student->id;
                    }
                    $stats['students_imported']++;
                }

                // Layout Blocks & Seats
                foreach ($secData['layout_blocks'] ?? [] as $blockData) {
                    $block = $section->layoutBlocks()->create([
                        'label' => $blockData['label'],
                        'block_row' => $blockData['block_row'],
                        'block_column' => $blockData['block_column'],
                        'internal_rows' => $blockData['internal_rows'],
                        'internal_columns' => $blockData['internal_columns'],
                        'aisles' => $blockData['aisles'] ?? null,
                    ]);

                    foreach ($blockData['seats'] ?? [] as $seatData) {
                        $newStudentId = null;
                        if (!empty($seatData['student_id']) && isset($studentMap[$seatData['student_id']])) {
                            $newStudentId = $studentMap[$seatData['student_id']];
                        }

                        $block->seats()->create([
                            'row_number' => $seatData['row_number'],
                            'column_number' => $seatData['column_number'],
                            'label' => $seatData['label'],
                            'is_disabled' => $seatData['is_disabled'] ?? false,
                            'student_id' => $newStudentId,
                        ]);
                    }
                }

                // Attendance Sessions & Records
                foreach ($secData['attendance_sessions'] ?? [] as $sessData) {
                    $session = $section->attendanceSessions()->create([
                        'session_date' => $sessData['session_date'],
                        'starts_at' => $sessData['starts_at'],
                        'ends_at' => $sessData['ends_at'],
                        'duration_minutes' => $sessData['duration_minutes'] ?? 60,
                    ]);
                    $stats['attendance_sessions_imported']++;

                    foreach ($sessData['records'] ?? [] as $recData) {
                        if (!empty($recData['student_id']) && isset($studentMap[$recData['student_id']])) {
                            $session->records()->create([
                                'student_id' => $studentMap[$recData['student_id']],
                                'status' => $recData['status'],
                                'attended_minutes' => $recData['attended_minutes'] ?? 0,
                                'remarks' => $recData['remarks'] ?? null,
                            ]);
                        }
                    }
                }

                // Recitations
                foreach ($secData['recitations'] ?? [] as $recData) {
                    if (!empty($recData['student_id']) && isset($studentMap[$recData['student_id']])) {
                        $section->recitations()->create([
                            'student_id' => $studentMap[$recData['student_id']],
                            'score' => $recData['score'],
                            'conducted_on' => $recData['conducted_on'],
                            'accuracy' => $recData['accuracy'] ?? null,
                            'delivery' => $recData['delivery'] ?? null,
                            'comments' => $recData['comments'] ?? ($recData['notes'] ?? null),
                        ]);
                        $stats['recitations_imported']++;
                    }
                }

                // Assessments & Scores
                foreach ($secData['assessments'] ?? [] as $assData) {
                    $assessment = $section->assessments()->create([
                        'type' => $assData['type'],
                        'assessment_number' => $assData['assessment_number'] ?? 1,
                        'title' => $assData['title'],
                        'description' => $assData['description'] ?? null,
                        'conducted_on' => $assData['conducted_on'],
                        'max_points' => $assData['max_points'] ?? 100,
                    ]);
                    $stats['assessments_imported']++;

                    foreach ($assData['scores'] ?? [] as $scData) {
                        if (!empty($scData['student_id']) && isset($studentMap[$scData['student_id']])) {
                            $assessment->scores()->create([
                                'student_id' => $studentMap[$scData['student_id']],
                                'score' => $scData['score'],
                                'remarks' => $scData['remarks'] ?? null,
                            ]);
                        }
                    }
                }

                // Projects & Groups
                foreach ($secData['projects'] ?? [] as $prjData) {
                    $project = $section->projects()->create([
                        'title' => $prjData['title'],
                        'project_number' => $prjData['project_number'] ?? 1,
                        'format' => $prjData['format'] ?? 'group',
                        'max_score' => $prjData['max_score'] ?? 100,
                        'due_at' => !empty($prjData['due_at']) ? $prjData['due_at'] : null,
                    ]);

                    foreach ($prjData['groups'] ?? [] as $grpData) {
                        $newLeaderId = !empty($grpData['leader_student_id']) && isset($studentMap[$grpData['leader_student_id']])
                            ? $studentMap[$grpData['leader_student_id']]
                            : null;

                        $newStudentIds = [];
                        if (is_array($grpData['student_ids'] ?? null)) {
                            foreach ($grpData['student_ids'] as $sid) {
                                if (isset($studentMap[$sid])) {
                                    $newStudentIds[] = $studentMap[$sid];
                                }
                            }
                        }

                        $project->groups()->create([
                            'group_number' => $grpData['group_number'],
                            'name' => $grpData['name'],
                            'description' => $grpData['description'] ?? null,
                            'leader_student_id' => $newLeaderId,
                            'student_ids' => $newStudentIds,
                            'score' => $grpData['score'] ?? null,
                        ]);
                    }
                }

                // Course Modules
                foreach ($secData['course_modules'] ?? [] as $modIdx => $modData) {
                    $section->courseModules()->create([
                        'module_number' => !empty($modData['module_number']) ? $modData['module_number'] : ('M'.($modIdx + 1)),
                        'title' => $modData['title'] ?? ('Module '.($modIdx + 1)),
                        'description' => $modData['description'] ?? null,
                        'link_url' => $modData['link_url'] ?? $modData['url'] ?? null,
                        'file_path' => $modData['file_path'] ?? null,
                        'file_name' => $modData['file_name'] ?? null,
                        'sort_order' => $modData['sort_order'] ?? ($modIdx + 1),
                    ]);
                }
            }
        });

        return $stats;
    }

    /**
     * Extract structured backup data from an uploaded SQLite database file.
     */
    public function extractSqliteData(string $filePath): array
    {
        $pdo = new \PDO('sqlite:'.$filePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // Check if this is a valid ClassCheck database
        $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $tablesStmt->fetchAll(\PDO::FETCH_COLUMN);

        $hasRequired = in_array('sections', $tables) || in_array('students', $tables) || in_array('academic_terms', $tables);
        if (!$hasRequired) {
            throw new \InvalidArgumentException('The uploaded SQLite file is not a valid ClassCheck database.');
        }

        $terms = in_array('academic_terms', $tables)
            ? $pdo->query('SELECT * FROM academic_terms')->fetchAll()
            : [];

        $sections = in_array('sections', $tables)
            ? $pdo->query('SELECT * FROM sections')->fetchAll()
            : [];

        $sectionsData = [];
        foreach ($sections as $sec) {
            $secId = (int) $sec['id'];

            // Students
            $students = in_array('students', $tables)
                ? $pdo->query("SELECT * FROM students WHERE section_id = {$secId}")->fetchAll()
                : [];

            // Layout Blocks & Seats
            $blocks = in_array('layout_blocks', $tables)
                ? $pdo->query("SELECT * FROM layout_blocks WHERE section_id = {$secId}")->fetchAll()
                : [];

            $blocksData = [];
            foreach ($blocks as $block) {
                $blockId = (int) $block['id'];
                $seats = in_array('seats', $tables)
                    ? $pdo->query("SELECT * FROM seats WHERE layout_block_id = {$blockId}")->fetchAll()
                    : [];

                $blocksData[] = [
                    'id' => $block['id'],
                    'label' => $block['label'],
                    'block_row' => (int) $block['block_row'],
                    'block_column' => (int) $block['block_column'],
                    'internal_rows' => (int) $block['internal_rows'],
                    'internal_columns' => (int) $block['internal_columns'],
                    'aisles' => ! empty($block['aisles']) ? (is_array(json_decode($block['aisles'], true)) ? json_decode($block['aisles'], true) : $block['aisles']) : null,
                    'seats' => array_map(fn ($s) => [
                        'id' => $s['id'],
                        'student_id' => $s['student_id'] ? (int) $s['student_id'] : null,
                        'row_number' => (int) $s['row_number'],
                        'column_number' => (int) $s['column_number'],
                        'label' => $s['label'],
                        'is_disabled' => (bool) ($s['is_disabled'] ?? false),
                    ], $seats),
                ];
            }

            // Attendance Sessions & Records
            $sessions = in_array('attendance_sessions', $tables)
                ? $pdo->query("SELECT * FROM attendance_sessions WHERE section_id = {$secId}")->fetchAll()
                : [];

            $attendanceData = [];
            foreach ($sessions as $sess) {
                $sessId = (int) $sess['id'];
                $records = in_array('attendance_records', $tables)
                    ? $pdo->query("SELECT * FROM attendance_records WHERE attendance_session_id = {$sessId}")->fetchAll()
                    : [];

                $attendanceData[] = [
                    'id' => $sess['id'],
                    'session_date' => (string) $sess['session_date'],
                    'starts_at' => (string) $sess['starts_at'],
                    'ends_at' => (string) $sess['ends_at'],
                    'duration_minutes' => (int) ($sess['duration_minutes'] ?? 60),
                    'records' => array_map(fn ($r) => [
                        'student_id' => (int) $r['student_id'],
                        'status' => $r['status'],
                        'attended_minutes' => (int) ($r['attended_minutes'] ?? 0),
                        'remarks' => $r['remarks'] ?? null,
                    ], $records),
                ];
            }

            // Recitations
            $recitations = in_array('recitations', $tables)
                ? $pdo->query("SELECT * FROM recitations WHERE section_id = {$secId}")->fetchAll()
                : [];

            // Assessments & Scores
            $assessments = in_array('assessments', $tables)
                ? $pdo->query("SELECT * FROM assessments WHERE section_id = {$secId}")->fetchAll()
                : [];

            $assessmentsData = [];
            foreach ($assessments as $ass) {
                $assId = (int) $ass['id'];
                $scores = in_array('assessment_scores', $tables)
                    ? $pdo->query("SELECT * FROM assessment_scores WHERE assessment_id = {$assId}")->fetchAll()
                    : [];

                $assessmentsData[] = [
                    'id' => $ass['id'],
                    'type' => $ass['type'],
                    'assessment_number' => $ass['assessment_number'] ?? 1,
                    'title' => $ass['title'],
                    'description' => $ass['description'] ?? null,
                    'conducted_on' => (string) $ass['conducted_on'],
                    'max_points' => (float) ($ass['max_points'] ?? 100),
                    'scores' => array_map(fn ($sc) => [
                        'student_id' => (int) $sc['student_id'],
                        'score' => (float) ($sc['score'] ?? 0),
                        'remarks' => $sc['remarks'] ?? null,
                    ], $scores),
                ];
            }

            // Projects & Groups
            $projects = in_array('projects', $tables)
                ? $pdo->query("SELECT * FROM projects WHERE section_id = {$secId}")->fetchAll()
                : [];

            $projectsData = [];
            foreach ($projects as $prj) {
                $prjId = (int) $prj['id'];
                $groups = in_array('project_groups', $tables)
                    ? $pdo->query("SELECT * FROM project_groups WHERE project_id = {$prjId}")->fetchAll()
                    : [];

                $projectsData[] = [
                    'id' => $prj['id'],
                    'title' => $prj['title'],
                    'project_number' => $prj['project_number'] ?? 1,
                    'format' => $prj['format'] ?? 'group',
                    'max_score' => (float) ($prj['max_score'] ?? ($prj['max_points'] ?? 100)),
                    'due_at' => (string) ($prj['due_at'] ?? ($prj['conducted_on'] ?? '')),
                    'groups' => array_map(fn ($g) => [
                        'group_number' => $g['group_number'] ?? 1,
                        'name' => $g['name'],
                        'description' => $g['description'] ?? null,
                        'leader_student_id' => $g['leader_student_id'] ? (int) $g['leader_student_id'] : null,
                        'student_ids' => ! empty($g['student_ids']) ? (is_array(json_decode($g['student_ids'], true)) ? json_decode($g['student_ids'], true) : []) : [],
                        'score' => $g['score'] !== null ? (float) $g['score'] : null,
                    ], $groups),
                ];
            }

            // Schedules
            $schedules = in_array('section_schedules', $tables)
                ? $pdo->query("SELECT * FROM section_schedules WHERE section_id = {$secId}")->fetchAll()
                : [];

            // Course Modules
            $modules = in_array('course_modules', $tables)
                ? $pdo->query("SELECT * FROM course_modules WHERE section_id = {$secId}")->fetchAll()
                : [];

            $sectionsData[] = [
                'id' => $sec['id'],
                'academic_term_id' => $sec['academic_term_id'] ? (int) $sec['academic_term_id'] : null,
                'subject_code' => $sec['subject_code'],
                'subject_title' => $sec['subject_title'] ?? $sec['subject_code'],
                'name' => $sec['name'],
                'enrollment_token' => $sec['enrollment_token'] ?? null,
                'is_enrollment_open' => (bool) ($sec['is_enrollment_open'] ?? true),
                'grading_weights' => ! empty($sec['grading_weights']) ? (is_array(json_decode($sec['grading_weights'], true)) ? json_decode($sec['grading_weights'], true) : $sec['grading_weights']) : null,
                'archived_at' => ! empty($sec['archived_at']) ? (string) $sec['archived_at'] : null,
                'schedules' => array_map(fn ($s) => [
                    'day_of_week' => (int) $s['day_of_week'],
                    'starts_at' => (string) $s['starts_at'],
                    'ends_at' => (string) $s['ends_at'],
                    'room' => $s['room'] ?? null,
                    'schedule_type' => $s['schedule_type'] ?? 'lecture',
                ], $schedules),
                'students' => array_map(fn ($st) => [
                    'id' => $st['id'],
                    'student_number' => $st['student_number'] ?? null,
                    'first_name' => $st['first_name'],
                    'middle_name' => $st['middle_name'] ?? null,
                    'last_name' => $st['last_name'],
                    'is_active' => (bool) ($st['is_active'] ?? true),
                    'photo_path' => $st['photo_path'] ?? null,
                ], $students),
                'layout_blocks' => $blocksData,
                'attendance_sessions' => $attendanceData,
                'recitations' => array_map(fn ($r) => [
                    'student_id' => (int) $r['student_id'],
                    'score' => (float) $r['score'],
                    'conducted_on' => (string) $r['conducted_on'],
                    'accuracy' => $r['accuracy'] ?? null,
                    'delivery' => $r['delivery'] ?? null,
                    'comments' => $r['comments'] ?? ($r['notes'] ?? null),
                ], $recitations),
                'assessments' => $assessmentsData,
                'projects' => $projectsData,
                'course_modules' => array_map(fn ($m, $idx) => [
                    'id' => $m['id'],
                    'module_number' => $m['module_number'] ?? ('M'.($idx + 1)),
                    'title' => $m['title'],
                    'description' => $m['description'] ?? null,
                    'link_url' => $m['link_url'] ?? ($m['url'] ?? null),
                    'file_path' => $m['file_path'] ?? null,
                    'file_name' => $m['file_name'] ?? null,
                    'sort_order' => (int) ($m['sort_order'] ?? ($idx + 1)),
                ], $modules, array_keys($modules)),
            ];
        }

        return [
            'meta' => [
                'app' => 'ClassCheck',
                'version' => '1.0.0',
                'exported_at' => now()->toIso8601String(),
                'source' => 'sqlite_upload',
                'counts' => [
                    'terms' => count($terms),
                    'sections' => count($sectionsData),
                ],
            ],
            'academic_terms' => array_map(fn ($t) => [
                'id' => $t['id'],
                'name' => $t['name'],
                'school_year' => $t['school_year'],
                'starts_on' => (string) $t['starts_on'],
                'ends_on' => (string) $t['ends_on'],
                'is_current' => (bool) ($t['is_current'] ?? false),
            ], $terms),
            'sections' => $sectionsData,
        ];
    }

    /**
     * Stream CSV export based on requested data type.
     */
    public function streamCsv(User $user, string $type): StreamedResponse
    {
        $filename = 'classcheck_'.$type.'_'.now()->format('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($user, $type) {
            $output = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            switch ($type) {
                case 'students':
                    fputcsv($output, ['Section Code', 'Section Name', 'Student ID', 'Last Name', 'First Name', 'Middle Name', 'Status', 'Seat']);
                    $sections = Section::where('user_id', $user->id)->with(['students.seat'])->get();
                    foreach ($sections as $sec) {
                        foreach ($sec->students as $st) {
                            fputcsv($output, [
                                $sec->subject_code,
                                $sec->name,
                                $st->student_number ?? 'N/A',
                                $st->last_name,
                                $st->first_name,
                                $st->middle_name ?? '',
                                $st->is_active ? 'Active' : 'Inactive',
                                $st->seat ? $st->seat->label : 'Unseated',
                            ]);
                        }
                    }
                    break;

                case 'attendance':
                    fputcsv($output, ['Section Code', 'Session Date', 'Starts At', 'Ends At', 'Student ID', 'Student Name', 'Status', 'Minutes Attended', 'Remarks']);
                    $sessions = AttendanceSession::whereHas('section', fn ($q) => $q->where('user_id', $user->id))
                        ->with(['section', 'records.student'])
                        ->orderByDesc('session_date')
                        ->get();
                    foreach ($sessions as $sess) {
                        foreach ($sess->records as $rec) {
                            fputcsv($output, [
                                $sess->section->subject_code,
                                (string) $sess->session_date,
                                (string) $sess->starts_at,
                                (string) $sess->ends_at,
                                $rec->student->student_number ?? 'N/A',
                                $rec->student ? $rec->student->full_name : 'Unknown',
                                ucfirst($rec->status),
                                $rec->attended_minutes,
                                $rec->remarks ?? '',
                            ]);
                        }
                    }
                    break;

                case 'grades':
                    fputcsv($output, ['Section Code', 'Assessment Title', 'Type', 'Max Score', 'Conducted On', 'Student ID', 'Student Name', 'Score', 'Percentage']);
                    $assessments = Assessment::whereHas('section', fn ($q) => $q->where('user_id', $user->id))
                        ->with(['section', 'scores.student'])
                        ->get();
                    foreach ($assessments as $ass) {
                        foreach ($ass->scores as $sc) {
                            $pct = $ass->max_points > 0 ? round(($sc->score / $ass->max_points) * 100, 1).'%' : 'N/A';
                            fputcsv($output, [
                                $ass->section->subject_code,
                                $ass->title,
                                ucfirst($ass->type),
                                $ass->max_points,
                                (string) $ass->conducted_on,
                                $sc->student->student_number ?? 'N/A',
                                $sc->student ? $sc->student->full_name : 'Unknown',
                                $sc->score,
                                $pct,
                            ]);
                        }
                    }
                    break;

                case 'recitations':
                    fputcsv($output, ['Section Code', 'Conducted On', 'Student ID', 'Student Name', 'Score', 'Accuracy', 'Delivery', 'Comments']);
                    $recitations = Recitation::whereHas('section', fn ($q) => $q->where('user_id', $user->id))
                        ->with(['section', 'student'])
                        ->orderByDesc('conducted_on')
                        ->get();
                    foreach ($recitations as $rec) {
                        fputcsv($output, [
                            $rec->section->subject_code,
                            (string) $rec->conducted_on,
                            $rec->student->student_number ?? 'N/A',
                            $rec->student ? $rec->student->full_name : 'Unknown',
                            $rec->score,
                            $rec->accuracy ?? 'N/A',
                            $rec->delivery ?? 'N/A',
                            $rec->comments ?? '',
                        ]);
                    }
                    break;
            }

            fclose($output);
        }, 200, $headers);
    }
}
