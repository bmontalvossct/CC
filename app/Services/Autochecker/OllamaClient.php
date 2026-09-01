<?php

namespace App\Services\Autochecker;

use Exception;
use Generator;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaClient
{
    protected string $baseUrl;
    protected int $connectTimeout;
    protected int $timeout;
    protected string $keepAlive;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('autochecker.ollama.base_url', 'http://127.0.0.1:11434'), '/');
        $this->connectTimeout = (int) config('autochecker.ollama.connect_timeout', 5);
        $this->timeout = (int) config('autochecker.ollama.timeout', 300);
        $this->keepAlive = (string) config('autochecker.ollama.keep_alive', '15m');
    }

    /**
     * Check if the configured endpoint is a local loopback address.
     */
    public function isLocalEndpoint(): bool
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?? '127.0.0.1';

        return in_array($host, ['127.0.0.1', 'localhost', '::1', '0.0.0.0', '127.0.0.1:11434'], true)
            || str_starts_with($host, '127.');
    }

    /**
     * Test connection and retrieve latency.
     *
     * @return array{online: bool, is_local: bool, latency_ms: ?float, error: ?string}
     */
    public function ping(): array
    {
        $startTime = microtime(true);

        try {
            $response = Http::connectTimeout($this->connectTimeout)
                ->timeout($this->connectTimeout + 2)
                ->get("{$this->baseUrl}/api/tags");

            $latency = round((microtime(true) - $startTime) * 1000, 1);

            if ($response->successful()) {
                return [
                    'online' => true,
                    'is_local' => $this->isLocalEndpoint(),
                    'latency_ms' => $latency,
                    'error' => null,
                ];
            }

            return [
                'online' => false,
                'is_local' => $this->isLocalEndpoint(),
                'latency_ms' => null,
                'error' => "HTTP {$response->status()}",
            ];
        } catch (Exception $e) {
            return [
                'online' => false,
                'is_local' => $this->isLocalEndpoint(),
                'latency_ms' => null,
                'error' => 'Ollama is unreachable on ' . $this->baseUrl,
            ];
        }
    }

    /**
     * Get list of installed models on the Ollama instance (excluding disallowed ones).
     *
     * @return array<int, array{name: string, model: string, size_gb: float}>
     */
    public function getModels(): array
    {
        try {
            $response = Http::connectTimeout($this->connectTimeout)
                ->timeout(5)
                ->get("{$this->baseUrl}/api/tags");

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json('models') ?? [];
            $models = [];

            foreach ($data as $m) {
                $name = $m['name'] ?? '';
                // Never auto-select or suggest Qwen 3.6 on this hardware setup
                if (stripos($name, 'qwen3.6') !== false || stripos($name, 'qwen:3.6') !== false) {
                    continue;
                }

                $sizeBytes = $m['size'] ?? 0;
                $models[] = [
                    'name' => $name,
                    'model' => $m['model'] ?? $name,
                    'size_gb' => round($sizeBytes / (1024 * 1024 * 1024), 2),
                ];
            }

            return $models;
        } catch (Exception $e) {
            Log::warning("Ollama getModels failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Resolve the exact model to use for a given profile based on installed models.
     */
    public function resolveProfileModel(string $profile): string
    {
        $config = config("autochecker.profiles.{$profile}", config('autochecker.profiles.chat'));
        $primary = $config['primary_model'] ?? 'qwen2.5:14b-instruct-q4_K_M';
        $allowed = $config['allowed_models'] ?? [$primary];

        $installed = collect($this->getModels())->pluck('name')->all();

        if (empty($installed)) {
            return $primary;
        }

        // 1. Check if primary exact model is installed
        if (in_array($primary, $installed, true)) {
            return $primary;
        }

        // 2. Check if primary matches an installed model tag variation (e.g., 'hermes3' -> 'hermes3:latest')
        foreach ($installed as $inst) {
            if ($inst === $primary || str_starts_with($inst, $primary . ':') || str_starts_with($primary, $inst . ':')) {
                return $inst;
            }
        }

        // 3. Check if any allowed alias is installed
        foreach ($allowed as $candidate) {
            foreach ($installed as $inst) {
                if ($inst === $candidate || str_starts_with($inst, $candidate . ':') || str_starts_with($candidate, $inst)) {
                    return $inst;
                }
            }
        }

        return $installed[0] ?? $primary;
    }

    /**
     * Warm up the model by issuing a zero-token ping with keep-alive.
     */
    public function warm(string $profile = 'chat'): bool
    {
        $model = $this->resolveProfileModel($profile);

        try {
            $response = Http::connectTimeout($this->connectTimeout)
                ->timeout(30)
                ->post("{$this->baseUrl}/api/chat", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'ping'],
                    ],
                    'stream' => false,
                    'keep_alive' => $this->keepAlive,
                    'options' => [
                        'num_predict' => 1,
                    ],
                ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::info("Ollama warm request failed or timed out: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute a synchronous chat request.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<int, array<string, mixed>> $tools
     * @param array<string, mixed>|null $schema
     * @param array<string, mixed> $extraOptions
     * @return array<string, mixed>
     */
    public function chat(
        string $profile,
        array $messages,
        array $tools = [],
        ?array $schema = null,
        array $extraOptions = []
    ): array {
        $model = $this->resolveProfileModel($profile);
        $profileConfig = config("autochecker.profiles.{$profile}", config('autochecker.profiles.chat'));

        $options = array_merge([
            'num_ctx' => $profileConfig['num_ctx'] ?? 16384,
            'num_predict' => $profileConfig['num_predict'] ?? -1,
            'temperature' => $profileConfig['temperature'] ?? 0.2,
            'top_k' => $profileConfig['top_k'] ?? 20,
            'top_p' => $profileConfig['top_p'] ?? 0.9,
        ], $extraOptions);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'keep_alive' => $this->keepAlive,
            'options' => $options,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        if ($schema !== null) {
            $payload['format'] = $schema;
        }

        $response = Http::connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/api/chat", $payload);

        if (! $response->successful()) {
            $status = $response->status();
            $msg = $status === 404 ? "Model '{$model}' is not installed in Ollama." : "Ollama error (HTTP {$status})";
            throw new Exception($msg, $status >= 400 && $status < 600 ? $status : 503);
        }

        return $response->json();
    }

    /**
     * Execute a streaming chat request using a Generator.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<int, array<string, mixed>> $tools
     * @param array<string, mixed>|null $schema
     * @param array<string, mixed> $extraOptions
     * @return Generator<int, array<string, mixed>>
     */
    public function chatStream(
        string $profile,
        array $messages,
        array $tools = [],
        ?array $schema = null,
        array $extraOptions = []
    ): Generator {
        $model = $this->resolveProfileModel($profile);
        $profileConfig = config("autochecker.profiles.{$profile}", config('autochecker.profiles.chat'));

        $options = array_merge([
            'num_ctx' => $profileConfig['num_ctx'] ?? 16384,
            'num_predict' => $profileConfig['num_predict'] ?? -1,
            'temperature' => $profileConfig['temperature'] ?? 0.2,
            'top_k' => $profileConfig['top_k'] ?? 20,
            'top_p' => $profileConfig['top_p'] ?? 0.9,
        ], $extraOptions);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'keep_alive' => $this->keepAlive,
            'options' => $options,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        if ($schema !== null) {
            $payload['format'] = $schema;
        }

        $client = new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'connect_timeout' => $this->connectTimeout,
            'timeout' => $this->timeout,
        ]);

        $response = $client->post('/api/chat', [
            'json' => $payload,
            'stream' => true,
        ]);

        $body = $response->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '' && ! $body->eof()) {
                usleep(5000);
                continue;
            }
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if (! empty($line)) {
                    $json = json_decode($line, true);
                    if (is_array($json)) {
                        yield $json;
                    }
                }
            }
        }

        if (! empty(trim($buffer))) {
            $remainingLines = explode("\n", trim($buffer));
            foreach ($remainingLines as $remLine) {
                $remLine = trim($remLine);
                if (! empty($remLine)) {
                    $json = json_decode($remLine, true);
                    if (is_array($json)) {
                        yield $json;
                    }
                }
            }
        }
    }

    /**
     * Stream download/pull progress for an Ollama model.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function streamPull(string $modelName): Generator
    {
        $client = new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'connect_timeout' => $this->connectTimeout,
            'timeout' => 3600,
        ]);

        try {
            $response = $client->post('/api/pull', [
                'json' => [
                    'name' => $modelName,
                    'stream' => true,
                ],
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (! $body->eof()) {
                $chunk = $body->read(8192);
                if ($chunk === '' && ! $body->eof()) {
                    usleep(5000);
                    continue;
                }
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);

                    if (! empty($line)) {
                        $json = json_decode($line, true);
                        if (is_array($json)) {
                            yield $json;
                        }
                    }
                }
            }

            if (! empty(trim($buffer))) {
                $remainingLines = explode("\n", trim($buffer));
                foreach ($remainingLines as $remLine) {
                    $remLine = trim($remLine);
                    if (! empty($remLine)) {
                        $json = json_decode($remLine, true);
                        if (is_array($json)) {
                            yield $json;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::error("Ollama streamPull failed for {$modelName}: " . $e->getMessage());
            yield [
                'error' => $e->getMessage(),
                'status' => 'error',
            ];
        }
    }
}

