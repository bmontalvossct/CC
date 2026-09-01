<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CourseModule;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemActionController extends Controller
{
    public function openFileLocation(Request $request): JsonResponse
    {
        $user = $request->user();
        $fileUrl = (string) $request->input('file_url', '');
        $fileName = (string) $request->input('file_name', '');
        $fullPath = null;

        // 1. Try matching assessment attachment route: /sections/{section}/assessments/{assessment}/...
        if (preg_match('#/sections/\d+/assessments/(\d+)#', $fileUrl, $matches)) {
            $assessmentId = (int) $matches[1];
            $assessment = Assessment::whereHas('section', fn ($q) => $q->where('user_id', $user->id))->find($assessmentId);
            if ($assessment && $assessment->attachment_path) {
                foreach (['local', 'public'] as $disk) {
                    if (Storage::disk($disk)->exists($assessment->attachment_path)) {
                        $fullPath = Storage::disk($disk)->path($assessment->attachment_path);
                        break;
                    }
                }
                if (! $fullPath && file_exists(storage_path('app/'.$assessment->attachment_path))) {
                    $fullPath = storage_path('app/'.$assessment->attachment_path);
                }
            }
        }

        // 2. Try matching project attachment route: /sections/{section}/projects/{project}/...
        if (! $fullPath && preg_match('#/sections/\d+/projects/(\d+)#', $fileUrl, $matches)) {
            $projectId = (int) $matches[1];
            $project = Project::whereHas('section', fn ($q) => $q->where('user_id', $user->id))->find($projectId);
            if ($project && $project->attachment_path) {
                foreach (['local', 'public'] as $disk) {
                    if (Storage::disk($disk)->exists($project->attachment_path)) {
                        $fullPath = Storage::disk($disk)->path($project->attachment_path);
                        break;
                    }
                }
                if (! $fullPath && file_exists(storage_path('app/'.$project->attachment_path))) {
                    $fullPath = storage_path('app/'.$project->attachment_path);
                }
            }
        }

        // 3. Try matching course module route: /sections/{section}/modules/(\d+)/...
        if (! $fullPath && preg_match('#/sections/\d+/modules/(\d+)#', $fileUrl, $matches)) {
            $moduleId = (int) $matches[1];
            $module = CourseModule::whereHas('section', fn ($q) => $q->where('user_id', $user->id))->find($moduleId);
            if ($module && $module->file_path) {
                foreach (['local', 'public'] as $disk) {
                    if (Storage::disk($disk)->exists($module->file_path)) {
                        $fullPath = Storage::disk($disk)->path($module->file_path);
                        break;
                    }
                }
                if (! $fullPath && file_exists(storage_path('app/'.$module->file_path))) {
                    $fullPath = storage_path('app/'.$module->file_path);
                }
            }
        }

        // 4. Fallback search by filename in storage/app
        if (! $fullPath && $fileName) {
            $safeName = basename($fileName);
            $patterns = [
                storage_path("app/assessments/*{$safeName}*"),
                storage_path("app/projects/*{$safeName}*"),
                storage_path("app/modules/*/*{$safeName}*"),
                storage_path("app/public/*/*{$safeName}*"),
            ];
            foreach ($patterns as $pattern) {
                $found = glob($pattern);
                if (! empty($found) && file_exists($found[0])) {
                    $fullPath = $found[0];
                    break;
                }
            }
        }

        // 5. Default fallback to app storage folder
        if (! $fullPath || ! file_exists($fullPath)) {
            $fullPath = storage_path('app');
        }

        if (PHP_OS_FAMILY === 'Windows' && file_exists($fullPath)) {
            $winPath = str_replace('/', '\\', $fullPath);
            if (is_file($winPath)) {
                pclose(popen('start "" explorer.exe /select,"'.$winPath.'"', 'r'));
            } else {
                pclose(popen('start "" explorer.exe "'.$winPath.'"', 'r'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Opened in Windows Explorer',
                'path' => $winPath,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Folder location: '.$fullPath,
            'path' => $fullPath,
        ]);
    }
}
