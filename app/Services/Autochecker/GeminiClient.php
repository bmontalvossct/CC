<?php

namespace App\Services\Autochecker;

use Exception;
use Generator;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient
{
    protected ?string $apiKey;
    protected string $model;
    protected int $timeout;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('autochecker.gemini.api_key') ?: env('GEMINI_API_KEY');
        $this->model = config('autochecker.gemini.model') ?: env('GEMINI_MODEL', 'gemini-2.5-flash');
        $this->timeout = (int) (config('autochecker.gemini.timeout') ?: env('GEMINI_TIMEOUT', 90));
    }

    /**
     * Check if Gemini API key is configured.
     */
    public function isAvailable(): bool
    {
        return ! empty($this->apiKey) && trim($this->apiKey) !== '';
    }

    /**
     * Get the active Gemini model identifier.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Test connection and verify API key validity.
     *
     * @return array{online: bool, latency_ms: ?float, model: string, error: ?string}
     */
    public function ping(): array
    {
        if (! $this->isAvailable()) {
            return [
                'online' => false,
                'latency_ms' => null,
                'model' => $this->model,
                'error' => 'Gemini API key is not configured in .env',
            ];
        }

        $startTime = microtime(true);

        try {
            $url = "{$this->baseUrl}/models/{$this->model}?key={$this->apiKey}";
            $response = Http::timeout(8)->get($url);
            $latency = round((microtime(true) - $startTime) * 1000, 1);

            if ($response->successful()) {
                return [
                    'online' => true,
                    'latency_ms' => $latency,
                    'model' => $this->model,
                    'error' => null,
                ];
            }

            $body = $response->json();
            $errorMessage = $body['error']['message'] ?? "HTTP {$response->status()}";

            return [
                'online' => false,
                'latency_ms' => null,
                'model' => $this->model,
                'error' => $errorMessage,
            ];
        } catch (Exception $e) {
            return [
                'online' => false,
                'latency_ms' => null,
                'model' => $this->model,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate content synchronously.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param array<string, mixed> $extraOptions
     * @return array{text: string, finish_reason: ?string, usage: array}
     */
    public function generateContent(string $systemPrompt, string $userPrompt, array $extraOptions = []): array
    {
        if (! $this->isAvailable()) {
            throw new Exception('Gemini API key is missing. Please set GEMINI_API_KEY in your .env file.');
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $extraOptions['temperature'] ?? 0.3,
                'maxOutputTokens' => $extraOptions['max_tokens'] ?? 8192,
            ],
        ];

        if (! empty($systemPrompt)) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        if (! empty($extraOptions['response_mime_type'])) {
            $payload['generationConfig']['responseMimeType'] = $extraOptions['response_mime_type'];
        }

        $response = Http::timeout($this->timeout)->post($url, $payload);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new Exception("Gemini API error ({$response->status()}): {$error}");
        }

        $data = $response->json();
        $candidate = $data['candidates'][0] ?? null;
        $parts = $candidate['content']['parts'] ?? [];
        $text = collect($parts)->pluck('text')->implode('');

        return [
            'text' => $text,
            'finish_reason' => $candidate['finishReason'] ?? 'STOP',
            'usage' => $data['usageMetadata'] ?? [],
        ];
    }

    /**
     * Stream exam or text generation via Gemini SSE stream endpoint.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param array<string, mixed> $extraOptions
     * @return Generator<int, array{text?: string, done?: bool, finish_reason?: string, usage?: array}>
     */
    public function streamGenerateContent(string $systemPrompt, string $userPrompt, array $extraOptions = []): Generator
    {
        if (! $this->isAvailable()) {
            throw new Exception('Gemini API key is missing. Please set GEMINI_API_KEY in your .env file.');
        }

        $url = "{$this->baseUrl}/models/{$this->model}:streamGenerateContent?alt=sse&key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $extraOptions['temperature'] ?? 0.3,
                'maxOutputTokens' => $extraOptions['max_tokens'] ?? 8192,
            ],
        ];

        if (! empty($systemPrompt)) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        $client = new GuzzleClient([
            'timeout' => $this->timeout,
            'connect_timeout' => 10,
        ]);

        $response = $client->post($url, [
            'json' => $payload,
            'stream' => true,
            'headers' => [
                'Accept' => 'text/event-stream',
            ],
        ]);

        $body = $response->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // Keep incomplete line in buffer

            foreach ($lines as $line) {
                $line = trim($line);
                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $jsonText = trim(substr($line, 5));
                if (empty($jsonText) || $jsonText === '[DONE]') {
                    continue;
                }

                $chunk = json_decode($jsonText, true);
                if (! is_array($chunk)) {
                    continue;
                }

                $candidate = $chunk['candidates'][0] ?? null;
                $parts = $candidate['content']['parts'] ?? [];
                $textDelta = collect($parts)->pluck('text')->implode('');

                if ($textDelta !== '') {
                    yield [
                        'text' => $textDelta,
                    ];
                }

                if (! empty($candidate['finishReason'])) {
                    yield [
                        'done' => true,
                        'finish_reason' => $candidate['finishReason'],
                        'usage' => $chunk['usageMetadata'] ?? [],
                    ];
                }
            }
        }
    }

    /**
     * Stream multi-turn chat messages through Gemini.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param string|null $systemPrompt
     * @return Generator<int, array{text?: string, done?: bool, finish_reason?: string}>
     */
    public function streamChat(array $messages, ?string $systemPrompt = null): Generator
    {
        if (! $this->isAvailable()) {
            throw new Exception('Gemini API key is missing. Please set GEMINI_API_KEY in your .env file.');
        }

        $url = "{$this->baseUrl}/models/{$this->model}:streamGenerateContent?alt=sse&key={$this->apiKey}";

        // Map messages to Gemini format (roles: 'user' or 'model')
        $contents = [];
        foreach ($messages as $msg) {
            $role = ($msg['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $text = trim($msg['content'] ?? '');
            if ($text === '') {
                continue;
            }

            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $text],
                ],
            ];
        }

        if (empty($contents)) {
            return;
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 8192,
            ],
        ];

        if (! empty($systemPrompt)) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        $client = new GuzzleClient([
            'timeout' => $this->timeout,
            'connect_timeout' => 10,
        ]);

        $response = $client->post($url, [
            'json' => $payload,
            'stream' => true,
            'headers' => [
                'Accept' => 'text/event-stream',
            ],
        ]);

        $body = $response->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $jsonText = trim(substr($line, 5));
                if (empty($jsonText) || $jsonText === '[DONE]') {
                    continue;
                }

                $chunk = json_decode($jsonText, true);
                if (! is_array($chunk)) {
                    continue;
                }

                $candidate = $chunk['candidates'][0] ?? null;
                $parts = $candidate['content']['parts'] ?? [];
                $textDelta = collect($parts)->pluck('text')->implode('');

                if ($textDelta !== '') {
                    yield [
                        'text' => $textDelta,
                    ];
                }

                if (! empty($candidate['finishReason'])) {
                    yield [
                        'done' => true,
                        'finish_reason' => $candidate['finishReason'],
                    ];
                }
            }
        }
    }
}
