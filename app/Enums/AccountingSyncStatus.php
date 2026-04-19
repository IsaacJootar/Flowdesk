<?php

namespace App\Enums;

enum AccountingSyncStatus: string
{
    case Pending = 'pending';
    case NeedsMapping = 'needs_mapping';
    case Exported = 'exported';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ready',
            self::NeedsMapping => 'Needs account mapping',
            self::Exported => 'Exported',
            self::Syncing => 'Syncing',
            self::Synced => 'Synced',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
