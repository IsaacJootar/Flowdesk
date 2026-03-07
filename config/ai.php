<?php

$defaultModels = array_values(array_filter(array_map(
    static fn (string $model): string => trim($model),
    explode(',', (string) env('FLOWDESK_AI_LOCAL_MODELS', 'qwen2.5:7b-instruct,llama3.1:8b-instruct,phi3:mini'))
)));

return [
    'runtime' => [
        'provider' => env('FLOWDESK_AI_RUNTIME_PROVIDER', 'ollama'),
        'base_url' => env('FLOWDESK_AI_RUNTIME_BASE_URL', 'http://127.0.0.1:11434'),
        'request_timeout_seconds' => (int) env('FLOWDESK_AI_RUNTIME_TIMEOUT_SECONDS', 25),
    ],

    'models' => [
        'primary' => env('FLOWDESK_AI_PRIMARY_MODEL', 'qwen2.5:7b-instruct'),
        'fallback' => env('FLOWDESK_AI_FALLBACK_MODEL', 'llama3.1:8b-instruct'),
        'fast' => env('FLOWDESK_AI_FAST_MODEL', 'phi3:mini'),
        'allowed' => $defaultModels,
    ],

    'embeddings' => [
        'provider' => env('FLOWDESK_AI_EMBEDDING_PROVIDER', 'ollama'),
        'model' => env('FLOWDESK_AI_EMBEDDING_MODEL', 'nomic-embed-text'),
        'vector_store' => env('FLOWDESK_AI_VECTOR_STORE', 'qdrant'),
        'qdrant_url' => env('FLOWDESK_AI_QDRANT_URL', 'http://127.0.0.1:6333'),
    ],

    // Safety defaults keep AI advisory-only until explicit module rollout.
    'guards' => [
        'advisory_only' => filter_var(env('FLOWDESK_AI_ADVISORY_ONLY', true), FILTER_VALIDATE_BOOL),
        'allow_auto_approval' => filter_var(env('FLOWDESK_AI_ALLOW_AUTO_APPROVAL', false), FILTER_VALIDATE_BOOL),
        'allow_auto_payout' => filter_var(env('FLOWDESK_AI_ALLOW_AUTO_PAYOUT', false), FILTER_VALIDATE_BOOL),
    ],
];
