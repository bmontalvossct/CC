<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ollama Local Configuration & Profiles
    |--------------------------------------------------------------------------
    |
    | Ollama is a free and open-source local LLM runner.
    | Default port is 11434 on localhost.
    |
    */
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'connect_timeout' => env('OLLAMA_CONNECT_TIMEOUT', 5),
        'timeout' => env('OLLAMA_TIMEOUT', 300),
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '15m'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Profiles (Hardware-Calibrated)
    |--------------------------------------------------------------------------
    |
    | - Chat: 14B instruct for balanced pedagogical conversations and tool calling.
    | - Code Grading: 7B coder for strict syntax and programmatic analysis.
    | - General Grading: 14B instruct for essay and rubric grading.
    |
    */
    'profiles' => [
        'chat' => [
            'primary_model' => env('OLLAMA_CHAT_MODEL', 'hermes3:8b'),
            'allowed_models' => [
                'hermes3:8b', 'hermes3', 'hermes3:latest', 'hermes3:8b-llama3.1-q4_K_M',
                'qwen2.5:7b', 'qwen2.5:7b-instruct', 'qwen2.5:7b-instruct-q4_K_M',
                'qwen2.5-coder:7b', 'qwen2.5-coder:7b-instruct', 'qwen2.5-coder:7b-instruct-q4_K_M',
                'qwen2.5:14b-instruct-q4_K_M', 'qwen2.5:14b', 'qwen2.5:14b-instruct',
                'nous-hermes2', 'hermes',
            ],
            'num_ctx' => 16384,
            'num_predict' => -1,
            'temperature' => 0.2,
            'top_k' => 20,
            'top_p' => 0.9,
        ],
        'code_grading' => [
            'primary_model' => env('OLLAMA_CODE_MODEL', 'qwen2.5-coder:7b'),
            'allowed_models' => [
                'qwen2.5-coder:7b', 'qwen2.5-coder:7b-instruct', 'qwen2.5-coder:7b-instruct-q4_K_M',
                'hermes3:8b', 'hermes3', 'hermes3:latest',
            ],
            'num_ctx' => 16384,
            'num_predict' => 4096,
            'temperature' => 0.0,
            'top_k' => 20,
            'top_p' => 0.9,
        ],
        'general_grading' => [
            'primary_model' => env('OLLAMA_GENERAL_MODEL', 'hermes3:8b'),
            'allowed_models' => [
                'hermes3:8b', 'hermes3', 'hermes3:latest', 'hermes3:8b-llama3.1-q4_K_M',
                'qwen2.5:7b', 'qwen2.5:7b-instruct', 'qwen2.5:7b-instruct-q4_K_M',
                'qwen2.5:14b-instruct-q4_K_M', 'qwen2.5:14b', 'qwen2.5:14b-instruct',
            ],
            'num_ctx' => 16384,
            'num_predict' => 4096,
            'temperature' => 0.0,
            'top_k' => 20,
            'top_p' => 0.9,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Autochecker Upload & Execution Bounds
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_direct_files' => 20,
        'max_zip_entries' => 100,
        'max_file_size_kb' => 10240,       // 10 MB per entry
        'max_total_expanded_kb' => 102400, // 100 MB total expanded
        'pdf_timeout_seconds' => 10,
        'temp_run_ttl_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported File Extensions for Extraction
    |--------------------------------------------------------------------------
    */
    'supported_extensions' => [
        // Programming & Scripting
        'py', 'java', 'c', 'cpp', 'cs', 'js', 'jsx', 'ts', 'tsx',
        'php', 'html', 'css', 'sql', 'rb', 'go', 'rs', 'swift', 'kt',
        // Text & Documents
        'txt', 'md', 'json', 'xml', 'csv', 'pdf',
    ],
];
