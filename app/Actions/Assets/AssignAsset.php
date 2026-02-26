<?php

namespace App\Actions\Assets;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Domains\Assets\Models\AssetEvent;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Jobs\ProcessAssetCommunicationLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssignAsset
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, Asset $asset, array $input): Asset
    {
        Gate::forUser($user)->authorize('assign', $asset);

        if ($asset->status === Asset::STATUS_DISPOSED) {
            throw ValidationException::withMessages([
                'status' => 'Disposed assets cannot be assigned or transferred.',
            ]);
        }

        $validated = Validator::make($input, [
            'target_user_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query->where('company_id', (int) $asset->company_id)->where('is_active', true)->whereNull('deleted_at')),
            ],
            'target_department_id' => [
                'nullable',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('company_id', (int) $asset->company_id)->whereNull('deleted_at')),
            ],
            'event_date' => ['required', 'date'],
            'summary' => ['nullable', 'string', 'max:160'],
            'details' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $eventDateTime = Carbon::parse((string) $validated['event_date'])->toDateTimeString();

        $eventType = $asset->assigned_to_user_id
            ? AssetEvent::TYPE_TRANSFERRED
            : AssetEvent::TYPE_ASSIGNED;

        $previousAssignment = [
            'assigned_to_user_id' => $asset->assigned_to_user_id,
            'assigned_department_id' => $asset->assigned_department_id,
            'assigned_at' => optional($asset->assigned_at)->toDateTimeString(),
        ];

        $asset->forceFill([
            'assigned_to_user_id' => (int) $validated['target_user_id'],
            'assigned_department_id' => $validated['target_department_id'] ? (int) $validated['target_department_id'] : null,
            'assigned_at' => $eventDateTime,
            'status' => Asset::STATUS_ASSIGNED,
            'updated_by' => (int) $user->id,
        ])->save();

        AssetEvent::query()->create([
            'company_id' => (int) $asset->company_id,
            'asset_id' => (int) $asset->id,
            'event_type' => $eventType,
            'event_date' => $eventDateTime,
            'actor_user_id' => (int) $user->id,
            'target_user_id' => (int) $validated['target_user_id'],
            'target_department_id' => $validated['target_department_id'] ? (int) $validated['target_department_id'] : null,
            'summary' => $this->nullableString($validated['summary'] ?? null) ?: ($eventType === AssetEvent::TYPE_TRANSFERRED ? 'Asset transferred' : 'Asset assigned'),
            'details' => $this->nullableString($validated['details'] ?? null),
            'metadata' => [
                'previous_assignment' => $previousAssignment,
            ],
        ]);

        $this->activityLogger->log(
            action: $eventType === AssetEvent::TYPE_TRANSFERRED ? 'asset.transferred' : 'asset.assigned',
            entityType: Asset::class,
            entityId: (int) $asset->id,
            metadata: [
                'asset_code' => (string) $asset->asset_code,
                'target_user_id' => (int) $validated['target_user_id'],
                'target_department_id' => $validated['target_department_id'] ? (int) $validated['target_department_id'] : null,
                'previous_assignment' => $previousAssignment,
            ],
            companyId: (int) $asset->company_id,
            userId: (int) $user->id,
        );

        $this->queueAssigneeCommunication(
            actor: $user,
            asset: $asset,
            targetUserId: (int) $validated['target_user_id'],
            eventType: $eventType,
            eventDateTime: $eventDateTime
        );

        return $asset->refresh();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function queueAssigneeCommunication(
        User $actor,
        Asset $asset,
        int $targetUserId,
        string $eventType,
        string $eventDateTime
    ): void {
        $recipient = User::query()
            ->where('id', $targetUserId)
            ->where('company_id', (int) $asset->company_id)
            ->where('is_active', true)
            ->first(['id', 'email', 'phone']);

        if (! $recipient) {
            return;
        }

        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => (int) $asset->company_id],
                CompanyCommunicationSetting::defaultAttributes()
            );

        $channels = $settings->selectableChannels();
        if ($channels === []) {
            return;
        }

        $event = $eventType === AssetEvent::TYPE_TRANSFERRED
            ? 'asset.internal.assignment.transferred'
            : 'asset.internal.assignment.assigned';

        foreach ($channels as $channel) {
            $dedupeKey = implode(':', [
                'asset-assignment',
                (int) $asset->id,
                $event,
                'channel',
                $channel,
                'recipient',
                (int) $recipient->id,
                Carbon::parse($eventDateTime)->format('YmdHis'),
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
                    'channel' => (string) $channel,
                    'status' => 'queued',
                    'recipient_email' => trim((string) ($recipient->email ?? '')) !== '' ? (string) $recipient->email : null,
                    'recipient_phone' => trim((string) ($recipient->phone ?? '')) !== '' ? (string) $recipient->phone : null,
                    'reminder_date' => now()->toDateString(),
                    'message' => 'Asset assignment notification queued.',
                    'metadata' => [
                        'asset_code' => (string) $asset->asset_code,
                        'asset_name' => (string) $asset->name,
                        'actor_user_id' => (int) $actor->id,
                        'event_date' => $eventDateTime,
                    ],
                ]
            );

            if (! $log->wasRecentlyCreated) {
                continue;
            }

            ProcessAssetCommunicationLog::dispatch((int) $log->id);
        }
    }
}
