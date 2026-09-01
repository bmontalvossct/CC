<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Section;
use App\Models\Student;
use App\Services\BackupExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupExportController extends Controller
{
    public function __construct(
        protected BackupExportService $backupService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $driver = DB::connection()->getDriverName();
        $isSqlite = $driver === 'sqlite';

        $sqlitePath = config('database.connections.sqlite.database');
        $sqliteSize = $isSqlite && file_exists($sqlitePath) ? filesize($sqlitePath) : null;

        $stats = [
            'terms_count' => AcademicTerm::where('user_id', $user->id)->count(),
            'sections_count' => Section::where('user_id', $user->id)->count(),
            'students_count' => Student::whereHas('section', fn ($q) => $q->where('user_id', $user->id))->count(),
        ];

        // Check for local snapshot files
        $localSnapshots = [];
        $backupDir = database_path('backups');
        if (is_dir($backupDir)) {
            $files = glob($backupDir.'/database_backup_*.sqlite');
            rsort($files);
            foreach (array_slice($files, 0, 5) as $f) {
                $localSnapshots[] = [
                    'name' => basename($f),
                    'size' => filesize($f),
                    'created_at' => date('Y-m-d H:i:s', filemtime($f)),
                ];
            }
        }

        return Inertia::render('settings/BackupExport', [
            'driver' => $driver,
            'isSqlite' => $isSqlite,
            'sqliteSize' => $sqliteSize,
            'stats' => $stats,
            'localSnapshots' => $localSnapshots,
        ]);
    }

    public function exportJson(Request $request): StreamedResponse
    {
        $user = $request->user();
        $data = $this->backupService->exportUserData($user);

        $filename = 'classcheck_backup_'.now()->format('Y-m-d_His').'.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, [
            'Content-Type' => 'application/json',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    public function downloadSqlite(Request $request): BinaryFileResponse
    {
        $sqlitePath = config('database.connections.sqlite.database');

        if (!file_exists($sqlitePath)) {
            abort(404, 'SQLite database file not found.');
        }

        // Copy to temporary file to prevent locked read
        $tempPath = tempnam(sys_get_temp_dir(), 'cc_sqlite_');
        copy($sqlitePath, $tempPath);

        $filename = 'classcheck_db_'.now()->format('Y-m-d_His').'.sqlite';

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/x-sqlite3',
        ])->deleteFileAfterSend(true);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:students,attendance,grades,recitations'],
        ]);

        return $this->backupService->streamCsv($request->user(), $request->input('type'));
    }

    public function createLocalSnapshot(Request $request): RedirectResponse
    {
        $sqlitePath = config('database.connections.sqlite.database');

        if (!file_exists($sqlitePath)) {
            return back()->with('error', 'SQLite database file not found on local disk.');
        }

        $backupDir = database_path('backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $backupPath = $backupDir."/database_backup_{$timestamp}.sqlite";
        copy($sqlitePath, $backupPath);

        return back()->with('success', "Local backup snapshot created: database_backup_{$timestamp}.sqlite");
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'max:51200'], // 50MB
            'clean_replace' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('backup_file');
        $cleanReplace = (bool) $request->input('clean_replace', false);
        $ext = strtolower($file->getClientOriginalExtension());

        try {
            $data = null;

            if (in_array($ext, ['sqlite', 'db', 'sqlite3'])) {
                $data = $this->backupService->extractSqliteData($file->getRealPath());
            } else {
                $content = file_get_contents($file->getRealPath());
                // Remove UTF-8 BOM if present
                $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
                $data = json_decode($content, true);

                if (! $data || ! is_array($data)) {
                    // Check if the uploaded file is a raw SQLite binary despite wrong extension
                    if (str_starts_with($content, 'SQLite format 3')) {
                        $data = $this->backupService->extractSqliteData($file->getRealPath());
                    } else {
                        $jsonErr = json_last_error_msg() ?: 'Invalid syntax';
                        return back()->withErrors(['backup_file' => "The uploaded file is not a valid JSON or SQLite backup. ({$jsonErr})"]);
                    }
                }
            }

            $stats = $this->backupService->restoreUserData($request->user(), $data, $cleanReplace);

            $msg = sprintf(
                'Backup restored successfully! Imported %d section(s), %d student(s), %d attendance session(s), and %d assessment(s).',
                $stats['sections_imported'],
                $stats['students_imported'],
                $stats['attendance_sessions_imported'],
                $stats['assessments_imported']
            );

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->withErrors(['backup_file' => 'Failed to restore backup: '.$e->getMessage()]);
        }
    }
}
