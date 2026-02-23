<?php

namespace App\Livewire\Requests;

use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class RequestCommunicationsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $activeTab = 'inbox';

    public string $search = '';

    public string $channelFilter = 'all';

    public string $statusFilter = 'all';

    public int $perPage = 10;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedSearch(): void
    {
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedChannelFilter(): void
    {
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage(pageName: $this->pageName());
    }

    public function switchTab(string $tab): void
    {
        if (! in_array($tab, ['inbox', 'delivery'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetPage(pageName: $this->pageName());
    }

    public function markRead(int $logId): void
    {
        $log = RequestCommunicationLog::query()
            ->where('id', $logId)
            ->where('channel', 'in_app')
            ->where('recipient_user_id', (int) auth()->id())
            ->first();

        if (! $log) {
            $this->setFeedbackError('Notification not found or not accessible.');

            return;
        }

        if (! $log->read_at) {
            $log->forceFill(['read_at' => now()])->save();
        }

        $this->setFeedback('Notification marked as read.');
    }

    public function markAllRead(): void
    {
        RequestCommunicationLog::query()
            ->where('channel', 'in_app')
            ->where('recipient_user_id', (int) auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->setFeedback('All inbox notifications marked as read.');
    }

    public function render(): View
    {
        $inboxUnreadCount = RequestCommunicationLog::query()
            ->where('channel', 'in_app')
            ->where('recipient_user_id', (int) auth()->id())
            ->whereNull('read_at')
            ->count();

        $communications = $this->readyToLoad
            ? $this->communicationsQuery()->paginate($this->perPage, pageName: $this->pageName())
            : RequestCommunicationLog::query()->whereRaw('1 = 0')->paginate($this->perPage, pageName: $this->pageName());

        return view('livewire.requests.request-communications-page', [
            'communications' => $communications,
            'inboxUnreadCount' => $inboxUnreadCount,
            'channels' => ['in_app', 'email', 'sms'],
            'statuses' => ['queued', 'sent', 'failed', 'skipped'],
        ])->layout('layouts.app', [
            'title' => 'Request Communications',
            'subtitle' => 'Track inbox notifications and delivery status across approval flows',
        ]);
    }

    private function communicationsQuery(): Builder
    {
        $query = RequestCommunicationLog::query()
            ->with([
                'request:id,request_code,title,requested_by',
                'recipient:id,name',
            ]);

        if ($this->activeTab === 'inbox') {
            $query->where('channel', 'in_app')
                ->where('recipient_user_id', (int) auth()->id());
        } else {
            $this->applyDeliveryAccessScope($query);
        }

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('event', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%')
                    ->orWhereHas('request', fn (Builder $requestQuery) => $requestQuery
                        ->where('request_code', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%'))
                    ->orWhereHas('recipient', fn (Builder $recipientQuery) => $recipientQuery
                        ->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($this->channelFilter !== 'all') {
            $query->where('channel', $this->channelFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->latest('id');
    }

    private function applyDeliveryAccessScope(Builder $query): void
    {
        $user = auth()->user();
        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        $role = (string) $user->role;
        $canViewCompanyLogs = in_array($role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);

        if ($canViewCompanyLogs) {
            return;
        }

        $query->where(function (Builder $builder) use ($user): void {
            $builder
                ->where('recipient_user_id', (int) $user->id)
                ->orWhereHas('request', fn (Builder $requestQuery) => $requestQuery->where('requested_by', (int) $user->id));
        });
    }

    private function pageName(): string
    {
        return $this->activeTab === 'inbox' ? 'inboxPage' : 'deliveryPage';
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function setFeedbackError(string $message): void
    {
        $this->feedbackMessage = null;
        $this->feedbackError = $message;
        $this->feedbackKey++;
    }
}
