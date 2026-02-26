<?php

namespace App\Services;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Enums\UserRole;
use App\Jobs\ProcessAssetCommunicationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AssetReminderService
{
    /**
     * @return array{scanned:int,queued:int,duplicates:int,missing_recipient:int,no_channels:int}
     */
    public function dispatchDueReminders(?int $companyId = null, int $daysAhead = 7): array
    {
        $stats = [
            'scanned' => 0,
            'queued' => 0,
            'duplicates' => 0,
            'missing_recipient' => 0,
            'no_channels' => 0,
        ];

        $today = now()->startOfDay();
        $threshold = $today->copy()->addDays(max(0, $daysAhead));

        Asset::query()
            ->where('status', '!=', Asset::STATUS_DISPOSED)
            ->where(function (Builder $query) use ($threshold): void {
                $query
                    ->where(function (Builder $maintenance): void {
                        $maintenance->whereNotNull('maintenance_due_date')
                            ->whereDate('maintenance_due_date', '<=', now()->toDateString());
                    })
                    ->orWhere(function (Builder $warranty): void {
                        $warranty->whereNotNull('warranty_expires_at')
                            ->whereDate('warranty_expires_at', '<=', now()->toDateString());
                    })
                    ->orWhere(function (Builder $maintenanceSoon) use ($threshold): void {
                        $maintenanceSoon->whereNotNull('maintenance_due_date')
                            ->whereDate('maintenance_due_date', '>', now()->toDateString())
                            ->whereDate('maintenance_due_date', '<=', $threshold->toDateString());
                    })
                    ->orWhere(function (Builder $warrantySoon) use ($threshold): void {
                        $warrantySoon->whereNotNull('warranty_expires_at')
                            ->whereDate('warranty_expires_at', '>', now()->toDateString())
                            ->whereDate('warranty_expires_at', '<=', $threshold->toDateString());
                    });
            })
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', (int) $companyId))
            ->orderBy('id')
            ->chunkById(200, function ($assets) use (&$stats, $today): void {
                foreach ($assets as $asset) {
                    $stats['scanned']++;

                    $channels = $this->channelsForCompany((int) $asset->company_id);
                    if ($channels === []) {
                        $stats['no_channels']++;

                        continue;
                    }

                    $recipients = $this->recipientsForAsset($asset);
                    if ($recipients->isEmpty()) {
                        $stats['missing_recipient']++;

                        continue;
                    }

                    $reminders = $this->reminderEventsForAsset($asset, $today);
                    if ($reminders === []) {
                        continue;
                    }

                    foreach ($reminders as $reminder) {
                        $event = (string) $reminder['event'];
                        $dueDate = (string) $reminder['due_date'];
                        $daysUntilDue = (int) $reminder['days_until_due'];

                        foreach ($recipients as $recipient) {
                            foreach ($channels as $channel) {
                                $recipientEmail = trim((string) ($recipient->email ?? ''));
                                $recipientPhone = trim((string) ($recipient->phone ?? ''));

                                if ($channel === CompanyCommunicationSetting::CHANNEL_EMAIL && $recipientEmail === '') {
                                    $stats['missing_recipient']++;
                                    continue;
                                }

                                if ($channel === CompanyCommunicationSetting::CHANNEL_SMS && $recipientPhone === '') {
                                    $stats['missing_recipient']++;
                                    continue;
                                }

                                $dedupeKey = implode(':', [
                                    'asset-reminder',
                                    (int) $asset->id,
                                    $event,
                                    $channel,
                                    'recipient',
                                    (int) $recipient->id,
                                    now()->toDateString(),
                                ]);

                                $log = AssetCommunicationLog::query()->firstOrCreate(
                                    [
                                        'company_id' => (int) $asset->company_id,
                                        'dedupe_key' => $dedupeKey,
                                    ],
                                    [
                                        'asset_id' => (int) $asset->id,
                                        'recipient_user_id' => (int) $recipient->id,
                                        'event' => $event,
                                        'channel' => $channel,
                                        'status' => 'queued',
                                        'recipient_email' => $recipientEmail !== '' ? $recipientEmail : null,
                                        'recipient_phone' => $recipientPhone !== '' ? $recipientPhone : null,
                                        'reminder_date' => now()->toDateString(),
                                        'message' => 'Asset reminder queued.',
                                        'metadata' => [
                                            'asset_name' => (string) $asset->name,
                                            'asset_code' => (string) $asset->asset_code,
                                            'asset_status' => (string) $asset->status,
                                            'due_date' => $dueDate,
                                            'days_until_due' => $daysUntilDue,
                                        ],
                                    ]
                                );

                                if (! $log->wasRecentlyCreated) {
                                    $stats['duplicates']++;
                                    continue;
                                }

                                $stats['queued']++;
                                ProcessAssetCommunicationLog::dispatch((int) $log->id);
                            }
                        }
                    }
                }
            });

        return $stats;
    }

    /**
     * @return array<int, string>
     */
    private function channelsForCompany(int $companyId): array
    {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                CompanyCommunicationSetting::defaultAttributes()
            );

        return $settings->selectableChannels();
    }

    private function recipientsForAsset(Asset $asset): \Illuminate\Support\Collection
    {
        $query = User::query()
            ->where('company_id', (int) $asset->company_id)
            ->where('is_active', true)
            ->where(function (Builder $builder) use ($asset): void {
                $builder->where('role', UserRole::Finance->value);
                if ($asset->assigned_to_user_id) {
                    $builder->orWhere('id', (int) $asset->assigned_to_user_id);
                }
            });

        return $query->get(['id', 'name', 'email', 'phone']);
    }

    /**
     * @return array<int, array{event:string,due_date:string,days_until_due:int}>
     */
    private function reminderEventsForAsset(Asset $asset, Carbon $today): array
    {
        $events = [];

        if ($asset->maintenance_due_date) {
            $dueDate = Carbon::parse($asset->maintenance_due_date)->startOfDay();
            $events[] = [
                'event' => $this->maintenanceEventForDate($dueDate, $today),
                'due_date' => $dueDate->toDateString(),
                'days_until_due' => (int) $today->diffInDays($dueDate, false),
            ];
        }

        if ($asset->warranty_expires_at) {
            $dueDate = Carbon::parse($asset->warranty_expires_at)->startOfDay();
            $events[] = [
                'event' => $this->warrantyEventForDate($dueDate, $today),
                'due_date' => $dueDate->toDateString(),
                'days_until_due' => (int) $today->diffInDays($dueDate, false),
            ];
        }

        return $events;
    }

    private function maintenanceEventForDate(Carbon $dueDate, Carbon $today): string
    {
        if ($dueDate->lt($today)) {
            return 'asset.internal.maintenance.overdue';
        }

        if ($dueDate->equalTo($today)) {
            return 'asset.internal.maintenance.due_today';
        }

        return 'asset.internal.maintenance.due_soon';
    }

    private function warrantyEventForDate(Carbon $dueDate, Carbon $today): string
    {
        if ($dueDate->lt($today)) {
            return 'asset.internal.warranty.expired';
        }

        if ($dueDate->equalTo($today)) {
            return 'asset.internal.warranty.expires_today';
        }

        return 'asset.internal.warranty.expires_soon';
    }
}

