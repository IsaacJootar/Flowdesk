<?php

namespace App\Livewire\Shell;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Requests\Models\RequestCommunicationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public function getUnreadCountProperty(): int
    {
        $userId = Auth::id();
        if (! $userId) {
            return 0;
        }

        return ActivityLog::query()
            ->where('user_id', $userId)
            ->where('action', 'request.notification.in_app')
            ->whereExists(function ($query): void {
                $query->from('request_communication_logs')
                    ->whereColumn('request_communication_logs.id', 'activity_logs.entity_id')
                    ->whereNull('request_communication_logs.read_at');
            })
            ->count();
    }

    public function getNotificationsProperty(): Collection
    {
        $userId = Auth::id();
        if (! $userId) {
            return collect();
        }

        $activityLogs = ActivityLog::query()
            ->where('user_id', $userId)
            ->where('action', 'request.notification.in_app')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($activityLogs->isEmpty()) {
            return collect();
        }

        $commLogIds = $activityLogs->pluck('entity_id')->filter()->map(fn ($id) => (int) $id)->all();
        $commLogs = RequestCommunicationLog::query()
            ->whereIn('id', $commLogIds)
            ->get()
            ->keyBy('id');

        return $activityLogs->map(function (ActivityLog $log) use ($commLogs): array {
            $commLog = $commLogs->get((int) $log->entity_id);

            return [
                'id' => $log->id,
                'event' => (string) ($log->metadata['event'] ?? ''),
                'request_code' => (string) ($log->metadata['request_code'] ?? ''),
                'request_id' => (int) ($log->metadata['request_id'] ?? 0),
                'created_at' => $log->created_at,
                'read' => $commLog ? $commLog->read_at !== null : true,
            ];
        });
    }

    public function markAllRead(): void
    {
        $userId = Auth::id();
        if (! $userId) {
            return;
        }

        $commLogIds = ActivityLog::query()
            ->where('user_id', $userId)
            ->where('action', 'request.notification.in_app')
            ->pluck('entity_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($commLogIds !== []) {
            RequestCommunicationLog::query()
                ->whereIn('id', $commLogIds)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $this->open = false;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.shell.notification-bell', [
            'unreadCount' => $this->unreadCount,
            'notifications' => $this->notifications,
        ]);
    }
}
