<?php

namespace App\Services\Execution\DTO;

/**
 * Normalized operation statuses shared across billing and payout adapters.
 */
enum AdapterOperationStatus: string
{
    case Skipped = 'skipped';
    case Queued = 'queued';
    case Processing = 'processing';
    case Settled = 'settled';
    case Failed = 'failed';
    case Reversed = 'reversed';
}
