<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Section;
use App\Services\Autochecker\ChatbotService;
use App\Services\Autochecker\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiAssistantController extends Controller
{
    public function __construct(
        protected ChatbotService $chatbotService,
        protected OllamaClient $ollamaClient,
    ) {
    }

    /**
     * Check Ollama status, latency, local verification, and active model profiles.
     */
    public function status(): JsonResponse
    {
        $ping = $this->ollamaClient->ping();
        $models = $ping['online'] ? $this->ollamaClient->getModels() : [];
        $chatModel = $this->ollamaClient->resolveProfileModel('chat');
        $codeModel = $this->ollamaClient->resolveProfileModel('code_grading');

        return response()->json([
            'ollama' => $ping,
            'models' => $models,
            'active_profiles' => [
                'chat' => $chatModel,
                'code_grading' => $codeModel,
            ],
            'is_local' => $this->ollamaClient->isLocalEndpoint(),
        ]);
    }

    /**
     * Warm up the chat model weights on Ollama when UI opens.
     */
    public function warm(): JsonResponse
    {
        $success = $this->ollamaClient->warm('chat');

        return response()->json([
            'warmed' => $success,
            'model' => $this->ollamaClient->resolveProfileModel('chat'),
        ]);
    }

    /**
     * Stream model download / installation progress via Ollama API.
     */
    public function pull(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'model' => ['nullable', 'string'],
        ]);

        $model = $validated['model'] ?? config('autochecker.profiles.chat.primary_model', 'hermes3:8b');

        // Security check: restrict pull to allowed model families
        $allowed = config('autochecker.profiles.chat.allowed_models', []);
        $allowed = array_merge($allowed, config('autochecker.profiles.code_grading.allowed_models', []));
        $allowed = array_merge($allowed, ['hermes3:8b', 'hermes3', 'qwen2.5-coder:7b', 'qwen2.5:7b']);

        $isAllowed = false;
        foreach ($allowed as $allow) {
            if (stripos($model, explode(':', $allow)[0]) !== false) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            return response()->json([
                'success' => false,
                'message' => "Model '{$model}' is not in the list of supported ClassCheck AI models.",
            ], 422);
        }

        return new StreamedResponse(function () use ($model) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @ob_implicit_flush(true);

            foreach ($this->ollamaClient->streamPull($model) as $event) {
                echo json_encode($event) . "\n";
                @ob_flush();
                @flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Stream an intelligent chat conversation with NDJSON chunks.
     */
    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant,system'],
            'messages.*.content' => ['required', 'string'],
            'messages.*.attachments' => ['nullable', 'array'],
            'messages.*.attachments.*.name' => ['nullable', 'string'],
            'messages.*.attachments.*.content' => ['nullable', 'string'],
            'scope' => ['nullable', 'string', 'in:current_section,all_classes,app_help'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $scope = $validated['scope'] ?? ChatbotService::SCOPE_CURRENT_SECTION;
        $sectionId = $validated['section_id'] ?? null;
        $user = $request->user();

        // Enforce section validation if current_section scope
        if ($scope === ChatbotService::SCOPE_CURRENT_SECTION) {
            if (! $sectionId) {
                // If on global page, automatically fall back to all_classes scope
                $scope = ChatbotService::SCOPE_ALL_CLASSES;
            } else {
                $userOwns = Section::where('id', $sectionId)->where('user_id', $user->id)->exists();
                if (! $userOwns) {
                    abort(403, "You do not have permission to access section #{$sectionId}.");
                }
            }
        }

        return new StreamedResponse(function () use ($user, $validated, $scope, $sectionId) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }

            // Safely clear existing output buffers to allow immediate chunk streaming
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            if (function_exists('ob_implicit_flush')) {
                @ob_implicit_flush(true);
            }

            $generator = $this->chatbotService->streamChat(
                user: $user,
                messages: $validated['messages'],
                scope: $scope,
                sectionId: $sectionId
            );

            foreach ($generator as $event) {
                echo json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Safely execute teacher-confirmed action proposals (e.g. creating/updating/deleting an assessment).
     */
    public function executeAction(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:create_assessment,update_assessment,delete_assessment'],
            'section_id' => ['required', 'integer'],
            'assessment_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'in:activity,laboratory,quiz,exam'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'max_points' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'conducted_on' => ['nullable', 'date'],
        ]);

        $section = Section::where('id', $validated['section_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($validated['action'] === 'create_assessment') {
            $type = $validated['type'] ?? 'activity';
            $title = trim($validated['title'] ?? '') ?: 'New Activity';
            $maxPoints = (float) ($validated['max_points'] ?? 100);
            $conductedOn = $validated['conducted_on'] ?? now()->toDateString();
            $description = $validated['description'] ?? null;

            $count = Assessment::where('section_id', $section->id)->where('type', $type)->count();
            $prefix = $type === 'laboratory' ? 'Lab' : ucfirst($type);
            $assessmentNumber = "{$prefix} " . ($count + 1);

            $assessment = Assessment::create([
                'section_id' => $section->id,
                'type' => $type,
                'title' => $title,
                'assessment_number' => $assessmentNumber,
                'max_points' => $maxPoints,
                'conducted_on' => $conductedOn,
                'description' => $description,
            ]);

            return response()->json([
                'success' => true,
                'action' => 'create_assessment',
                'message' => "Successfully added \"{$assessment->title}\" ({$assessment->assessment_number}) to {$section->name}!",
                'assessment' => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'type' => $assessment->type,
                    'max_points' => (float) $assessment->max_points,
                    'assessment_number' => $assessment->assessment_number,
                ],
                'section_id' => $section->id,
                'redirect_url' => route('sections.assessments.show', [$section, $assessment]),
            ]);
        }

        if ($validated['action'] === 'update_assessment') {
            $assessment = Assessment::where('section_id', $section->id)
                ->where('id', $validated['assessment_id'])
                ->firstOrFail();

            $updateData = array_filter([
                'title' => $validated['title'] ?? null,
                'type' => $validated['type'] ?? null,
                'max_points' => isset($validated['max_points']) ? (float) $validated['max_points'] : null,
                'conducted_on' => $validated['conducted_on'] ?? null,
                'description' => $validated['description'] ?? null,
            ], fn ($v) => $v !== null);

            $assessment->update($updateData);

            return response()->json([
                'success' => true,
                'action' => 'update_assessment',
                'message' => "Successfully updated \"{$assessment->title}\"!",
                'assessment' => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'type' => $assessment->type,
                    'max_points' => (float) $assessment->max_points,
                    'assessment_number' => $assessment->assessment_number,
                ],
                'section_id' => $section->id,
                'redirect_url' => route('sections.assessments.show', [$section, $assessment]),
            ]);
        }

        if ($validated['action'] === 'delete_assessment') {
            $assessment = Assessment::where('section_id', $section->id)
                ->where('id', $validated['assessment_id'])
                ->firstOrFail();

            $deletedTitle = $assessment->title;
            $assessment->scores()->delete();
            $assessment->delete();

            return response()->json([
                'success' => true,
                'action' => 'delete_assessment',
                'message' => "Successfully removed \"{$deletedTitle}\" from {$section->name}.",
                'section_id' => $section->id,
            ]);
        }

        return response()->json(['error' => 'Invalid action.'], 400);
    }

    /**
     * Get quick action suggestions based on active scope & section.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $sectionId = $request->query('section_id');
        $scope = $request->query('scope', 'current_section');
        $user = $request->user();

        if ($scope === 'app_help') {
            return response()->json([
                'suggestions' => [
                    'How do I arrange classroom seats and aisles in Floor Plan?',
                    'How does the Bulk Activity Autochecker work?',
                    'How do I randomize student groups for projects?',
                    'How do I export or backup my grades and rosters?',
                    'How do I configure grading weights and oral bonus cap?',
                ],
            ]);
        }

        if ($sectionId && $scope === 'current_section') {
            $hasSection = Section::where('id', $sectionId)->where('user_id', $user->id)->exists();
            if ($hasSection) {
                return response()->json([
                    'suggestions' => [
                        'Summarize this section\'s overall gradebook performance',
                        'Which students need academic follow-up or have high absences?',
                        'Draft a 5-item quiz with answer key for this subject',
                        'Create a 4-part grading rubric for our next laboratory task',
                        'Suggest active learning recitation questions for today\'s session',
                    ],
                ]);
            }
        }

        return response()->json([
            'suggestions' => [
                'Give me an overview of all my handled classes and student load',
                'Show me students across all classes with missing tasks or low attendance',
                'Suggest an interactive teaching activity for college students',
                'How do I use the Seating Grid & 1-Tap Roll Call in ClassCheck?',
                'Generate a 5-question multiple choice quiz with answer key',
            ],
        ]);
    }
}
