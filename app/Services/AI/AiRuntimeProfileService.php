<?php

namespace App\Services\AI;

/**
 * AiRuntimeProfileService
 *
 * Service responsible for retrieving and providing AI runtime configuration profile.
 * Aggregates all AI-related settings including runtime provider information, model configurations,
 * embedding settings, and safety guards.
 */
class AiRuntimeProfileService
{
    /**
     * Retrieves the complete AI runtime profile configuration.
     *
     * Returns a comprehensive array containing:
     * - runtime: LLM provider details (provider, base URL, timeout)
     * - models: Model selection (primary, fallback, fast, allowed list)
     * - embeddings: Vector embedding settings (provider, model, vector store, Qdrant URL)
     * - guards: Safety and approval settings for AI actions
     *
     * @return array<string, mixed> The AI runtime profile with all configuration settings
     */
    public function profile(): array
    {
        return [
            // Runtime configuration: LLM provider, endpoint, and request settings
            'runtime' => [
                'provider' => (string) config('ai.runtime.provider', 'ollama'),
                'base_url' => (string) config('ai.runtime.base_url', ''),
                'request_timeout_seconds' => (int) config('ai.runtime.request_timeout_seconds', 25),
            ],
            // Model selection: primary for main tasks, fallback for redundancy, fast for quick responses
            'models' => [
                'primary' => (string) config('ai.models.primary', ''),
                'fallback' => (string) config('ai.models.fallback', ''),
                'fast' => (string) config('ai.models.fast', ''),
                'allowed' => array_values((array) config('ai.models.allowed', [])),
            ],
            // Embedding settings: provider for text vectorization and vector database connection
            'embeddings' => [
                'provider' => (string) config('ai.embeddings.provider', 'ollama'),
                'model' => (string) config('ai.embeddings.model', ''),
                'vector_store' => (string) config('ai.embeddings.vector_store', 'qdrant'),
                'qdrant_url' => (string) config('ai.embeddings.qdrant_url', ''),
            ],
            // Safety guards: control advisory mode, auto-approval, and auto-payout behavior
            'guards' => [
                'advisory_only' => (bool) config('ai.guards.advisory_only', true),
                'allow_auto_approval' => (bool) config('ai.guards.allow_auto_approval', false),
                'allow_auto_payout' => (bool) config('ai.guards.allow_auto_payout', false),
            ],
        ];
    }
}
