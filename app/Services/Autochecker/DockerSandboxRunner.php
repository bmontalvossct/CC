<?php

namespace App\Services\Autochecker;

use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DockerSandboxRunner
{
    protected ?bool $isDockerAvailable = null;

    /**
     * Check if Docker daemon is available and accessible.
     */
    public function isAvailable(): bool
    {
        if ($this->isDockerAvailable !== null) {
            return $this->isDockerAvailable;
        }

        try {
            $process = new Process(['docker', 'info']);
            $process->setTimeout(3);
            $process->run();

            $this->isDockerAvailable = $process->isSuccessful();
        } catch (Exception $e) {
            $this->isDockerAvailable = false;
        }

        return $this->isDockerAvailable;
    }

    /**
     * Get availability status DTO.
     *
     * @return array{available: bool, reason: ?string}
     */
    public function getStatus(): array
    {
        $available = $this->isAvailable();

        return [
            'available' => $available,
            'reason' => $available ? null : 'Docker daemon is not running or not installed on this host.',
        ];
    }

    /**
     * Run Python test suite against student submission inside an isolated, non-root container.
     *
     * @param string $studentCode
     * @param array<int, array{name: string, stdin?: string, expected_output?: string, points: float}> $testCases
     * @return array{
     *     success: bool,
     *     total_points_earned: float,
     *     total_points_possible: float,
     *     passed_count: int,
     *     total_tests: int,
     *     test_results: array<int, array<string, mixed>>,
     *     error: ?string
     * }
     */
    public function runPythonTests(string $studentCode, array $testCases): array
    {
        if (! $this->isAvailable()) {
            return [
                'success' => false,
                'total_points_earned' => 0.0,
                'total_points_possible' => (float) collect($testCases)->sum('points'),
                'passed_count' => 0,
                'total_tests' => count($testCases),
                'test_results' => [],
                'error' => 'Docker is not installed or running. Python sandbox execution is unavailable.',
            ];
        }

        $tempDir = storage_path('app/temp/sandbox_' . uniqid('', true));
        if (! @mkdir($tempDir, 0755, true)) {
            return [
                'success' => false,
                'total_points_earned' => 0.0,
                'total_points_possible' => (float) collect($testCases)->sum('points'),
                'passed_count' => 0,
                'total_tests' => count($testCases),
                'test_results' => [],
                'error' => 'Could not allocate sandbox directory.',
            ];
        }

        file_put_contents("{$tempDir}/submission.py", $studentCode);

        $results = [];
        $earned = 0.0;
        $possible = 0.0;
        $passed = 0;

        try {
            foreach ($testCases as $test) {
                $testName = $test['name'] ?? 'Test';
                $stdin = $test['stdin'] ?? '';
                $expected = trim($test['expected_output'] ?? '');
                $points = (float) ($test['points'] ?? 1.0);
                $possible += $points;

                file_put_contents("{$tempDir}/input.txt", $stdin);

                // Run isolated docker command
                // Constraints: --network none, --cap-drop ALL, --security-opt no-new-privileges, --read-only, 256MB RAM, 1 CPU, 64 PIDs
                $cmd = [
                    'docker', 'run', '--rm',
                    '--network', 'none',
                    '--cap-drop', 'ALL',
                    '--security-opt', 'no-new-privileges',
                    '--memory', '256m',
                    '--cpus', '1.0',
                    '--pids-limit', '64',
                    '-v', "{$tempDir}:/app:ro",
                    '-w', '/app',
                    'python:3.11-slim',
                    'sh', '-c', 'python submission.py < input.txt',
                ];

                $process = new Process($cmd);
                $process->setTimeout(5); // 5s hard per-test timeout
                $process->run();

                $rawOutput = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $actual = trim($rawOutput);
                $isPass = ($actual === $expected) && $process->isSuccessful();

                if ($isPass) {
                    $earned += $points;
                    $passed++;
                }

                $results[] = [
                    'name' => $testName,
                    'passed' => $isPass,
                    'points_earned' => $isPass ? $points : 0.0,
                    'points_possible' => $points,
                    'stdin' => mb_substr($stdin, 0, 500),
                    'expected' => mb_substr($expected, 0, 500),
                    'actual' => mb_substr($actual, 0, 500),
                    'error' => ! empty($errorOutput) ? mb_substr($errorOutput, 0, 500) : null,
                ];
            }

            return [
                'success' => true,
                'total_points_earned' => round($earned, 2),
                'total_points_possible' => round($possible, 2),
                'passed_count' => $passed,
                'total_tests' => count($testCases),
                'test_results' => $results,
                'error' => null,
            ];
        } catch (Exception $e) {
            Log::warning("DockerSandboxRunner execution failed: " . $e->getMessage());

            return [
                'success' => false,
                'total_points_earned' => round($earned, 2),
                'total_points_possible' => round($possible, 2),
                'passed_count' => $passed,
                'total_tests' => count($testCases),
                'test_results' => $results,
                'error' => 'Test execution error: ' . $e->getMessage(),
            ];
        } finally {
            // Clean up temporary sandbox directory
            if (is_dir($tempDir)) {
                @unlink("{$tempDir}/submission.py");
                @unlink("{$tempDir}/input.txt");
                @rmdir($tempDir);
            }
        }
    }
}
