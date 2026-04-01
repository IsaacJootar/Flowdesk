<?php

namespace App\Services;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Jobs\ProcessVendorCommunicationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VendorCommunicationLogger
{
    public function __construct(
        private readonly VendorCommunicationDeliveryManager $deliveryManager
    ) {
    }
    /**
     * Queue vendor-facing payment-related communication events (email/SMS).
     *
     * @param  array<int, string>|null  $channels
     * @param  array<string, mixed>  $metadata
     */
    public function queueVendorPaymentEvent(
        VendorInvoice $invoice,
        string $event,
        ?array $channels = null,
        ?string $message = null,
        ?string $dedupeKey = null,
        array $metadata = [],
        bool $forceQueue = false
    ): int {
        if (! $this->isVendorPaymentEvent($event)) {
            // Guardrail: external vendor notifications are reserved for payment lifecycle events only.
            return 0;
        }

        $invoice->loadMissing('vendor:id,name,email,phone,company_id');

        $activeChannels = $this->resolveVendorChannels((int) $invoice->company_id, $channels);
        if ($activeChannels === []) {
            return 0;
        }

        $queued = 0;
        foreach ($activeChannels as $channel) {
            $log = VendorCommunicationLog::query()->create([
                'company_id' => (int) $invoice->company_id,
                'vendor_id' => (int) $invoice->vendor_id,
                'vendor_invoice_id' => (int) $invoice->id,
                'recipient_user_id' => null,
                'event' => $event,
                'channel' => $channel,
                'status' => 'queued',
                'recipient_email' => (string) ($invoice->vendor?->email ?? '') ?: null,
                'recipient_phone' => (string) ($invoice->vendor?->phone ?? '') ?: null,
                'reminder_date' => now()->toDateString(),
                'dedupe_key' => $dedupeKey,
                'message' => $message ?: 'Vendor communication queued.',
                'metadata' => $metadata,
            ]);

            $queued++;
            $this->dispatchLog($log, $forceQueue);
        }

        return $queued;
    }

    /**
     * Queue internal finance notifications (in-app/email) for invoice events.
     *
     * @param  array<int, string>|null  $channels
     * @param  array<string, mixed>  $metadata
     */
    public function queueFinanceTeamEvent(
        VendorInvoice $invoice,
        string $event,
        ?array $channels = null,
        ?string $message = null,
        ?string $dedupeKey = null,
        array $metadata = [],
        bool $forceQueue = false
    ): int {
        if (! str_starts_with($event, 'vendor.internal.')) {
            // Guardrail: finance inbox/email notifications should use explicit internal event names.
            return 0;
        }

        $invoice->loadMissing('vendor:id,name,email,phone,company_id');

        $financeUsers = User::query()
            ->where('company_id', (int) $invoice->company_id)
            ->where('role', UserRole::Finance->value)
            ->where('is_active', true)
            ->get(['id', 'email', 'phone']);

        if ($financeUsers->isEmpty()) {
            return 0;
        }

        $activeChannels = $this->resolveFinanceChannels((int) $invoice->company_id, $channels);
        if ($activeChannels === []) {
            return 0;
        }

        $queued = 0;
        foreach ($financeUsers as $recipient) {
            foreach ($activeChannels as $channel) {
                $safeDedupe = $dedupeKey
                    ? $dedupeKey.':recipient:'.$recipient->id.':channel:'.$channel
                    : null;

                $log = VendorCommunicationLog::query()->create([
                    'company_id' => (int) $invoice->company_id,
                    'vendor_id' => (int) $invoice->vendor_id,
                    'vendor_invoice_id' => (int) $invoice->id,
                    'recipient_user_id' => (int) $recipient->id,
                    'event' => $event,
                    'channel' => $channel,
                    'status' => 'queued',
                    'recipient_email' => (string) ($recipient->email ?? '') ?: null,
                    'recipient_phone' => (string) ($recipient->phone ?? '') ?: null,
                    'reminder_date' => now()->toDateString(),
                    'dedupe_key' => $safeDedupe,
                    'message' => $message ?: 'Internal finance notification queued.',
                    'metadata' => $metadata,
                ]);

                $queued++;
                $this->dispatchLog($log, $forceQueue);
            }
        }

        return $queued;
    }

    private function dispatchLog(VendorCommunicationLog $log, bool $forceQueue): void
    {
        $dispatch = function () use ($log, $forceQueue): void {
            if ($this->shouldQueue($forceQueue)) {
                ProcessVendorCommunicationLog::dispatch((int) $log->id);
                return;
            }

            $this->deliveryManager->deliver($log);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($dispatch);
        } else {
            $dispatch();
        }
    }

    private function shouldQueue(bool $forceQueue): bool
    {
        if ($forceQueue) {
            return true;
        }

        return strtolower((string) config('communications.delivery.mode', 'inline')) === 'queue';
    }

    /**
     * @param  array<int, string>|null  $channels
     * @return array<int, string>
     */
    private function resolveVendorChannels(int $companyId, ?array $channels): array
    {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                CompanyCommunicationSetting::defaultAttributes()
            );

        $allowed = array_values(array_intersect(
            $settings->selectableChannels(),
            [
                CompanyCommunicationSetting::CHANNEL_EMAIL,
                CompanyCommunicationSetting::CHANNEL_SMS,
            ]
        ));

        if ($channels === null) {
            return $allowed;
        }

        $wanted = array_values(array_unique(array_map('strval', $channels)));

        return array_values(array_intersect($allowed, $wanted));
    }

    /**
     * @param  array<int, string>|null  $channels
     * @return array<int, string>
     */
    private function resolveFinanceChannels(int $companyId, ?array $channels): array
    {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                CompanyCommunicationSetting::defaultAttributes()
            );

        $allowed = array_values(array_intersect(
            $settings->selectableChannels(),
            [
                CompanyCommunicationSetting::CHANNEL_IN_APP,
                CompanyCommunicationSetting::CHANNEL_EMAIL,
            ]
        ));

        if ($channels === null) {
            return $allowed;
        }

        $wanted = array_values(array_unique(array_map('strval', $channels)));

        return array_values(array_intersect($allowed, $wanted));
    }

    private function isVendorPaymentEvent(string $event): bool
    {
        return in_array($event, [
            'vendor.invoice.payment_recorded',
        ], true);
    }
}
