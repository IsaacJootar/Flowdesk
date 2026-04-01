<?php

/**
 * Communications Configuration
 *
 * This configuration file manages settings for the FlowDesk communication system,
 * including delivery modes and recovery mechanisms for failed or queued communications.
 *
 * The system supports both inline processing (immediate) and queued processing
 * for better performance and reliability.
 */

return [
    'delivery' => [
        // Default mode for communication delivery: "inline" or "queue".
        // Inline keeps user actions responsive and avoids unnecessary queues.
        'mode' => env('FLOWDESK_COMM_DELIVERY_MODE', 'inline'),
    ],
    'recovery' => [
        // Hard ceiling to prevent runaway retry workloads from UI/CLI overrides.
        'max_batch_size' => (int) env('FLOWDESK_COMM_RECOVERY_MAX_BATCH_SIZE', 500),

        // Default batch windows for bulk retry and stuck-queue processing.
        'default_retry_failed_batch' => (int) env('FLOWDESK_COMM_RECOVERY_DEFAULT_RETRY_FAILED_BATCH', 200),
        'default_process_queued_batch' => (int) env('FLOWDESK_COMM_RECOVERY_DEFAULT_PROCESS_QUEUED_BATCH', 500),

        // Internal chunk size keeps memory stable while processing larger batches.
        'chunk_size' => (int) env('FLOWDESK_COMM_RECOVERY_CHUNK_SIZE', 100),

        // Upper bound for older-than filters from UI/CLI to avoid pathological windows.
        'max_older_than_minutes' => (int) env('FLOWDESK_COMM_RECOVERY_MAX_OLDER_THAN_MINUTES', 10080),

        // UI-triggered operation batch sizes.
        'ui_retry_failed_batch' => (int) env('FLOWDESK_COMM_RECOVERY_UI_RETRY_FAILED_BATCH', 300),
        'ui_process_queued_batch' => (int) env('FLOWDESK_COMM_RECOVERY_UI_PROCESS_QUEUED_BATCH', 500),
    ],
];
