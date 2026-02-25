<?php

namespace App\Services;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Jobs\ProcessVendorCommunicationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VendorReminderService
{
    /**
     * @return array{scanned:int,queued:int,duplicates:int,missing_recipient:int,no_channels:int}
     */
    public function dispatchDueInvoiceReminders(
        ?int $companyId = null,
        ?int $vendorId = null,
        int $daysAhead = 0
    ): array {
        $stats = [
            'scanned' => 0,
            'queued' => 0,
            'duplicates' => 0,
            'missing_recipient' => 0,
            'no_channels' => 0,
        ];

        $today = now()->startOfDay();
        $dueThreshold = $today->copy()->addDays(max(0, $daysAhead));

        $query = VendorInvoice::query()
            ->with(['vendor:id,name,email,phone,company_id'])
            ->whereNotNull('due_date')
            ->where('status', '!=', VendorInvoice::STATUS_VOID)
            ->where('outstanding_amount', '>', 0)
            ->whereDate('due_date', '<=', $dueThreshold->toDateString())
            ->when($companyId !== null, fn (Builder $builder) => $builder->where('company_id', (int) $companyId))
            ->when($vendorId !== null, fn (Builder $builder) => $builder->where('vendor_id', (int) $vendorId))
            ->orderBy('id');

        $query->chunkById(200, function ($invoices) use (&$stats, $today): void {
            foreach ($invoices as $invoice) {
                $stats['scanned']++;

                $channels = $this->channelsForCompany((int) $invoice->company_id);
                if ($channels === []) {
                    $stats['no_channels']++;
                    continue;
                }

                $financeRecipients = User::query()
                    ->where('company_id', (int) $invoice->company_id)
                    ->where('role', UserRole::Finance->value)
                    ->where('is_active', true)
                    ->get(['id', 'email', 'phone']);

                if ($financeRecipients->isEmpty()) {
                    $stats['missing_recipient']++;
                    continue;
                }

                $event = $this->eventForInvoice($invoice, $today);
                $dueDate = optional($invoice->due_date)->toDateString();
                $daysUntilDue = $invoice->due_date
                    ? (int) Carbon::parse($invoice->due_date)->startOfDay()->diffInDays($today, false) * -1
                    : null;

                foreach ($financeRecipients as $recipient) {
                    foreach ($channels as $channel) {
                        $recipientEmail = (string) ($recipient->email ?? '');
                        $recipientPhone = (string) ($recipient->phone ?? '');

                        if ($channel === CompanyCommunicationSetting::CHANNEL_EMAIL && trim($recipientEmail) === '') {
                            $stats['missing_recipient']++;
                            continue;
                        }

                        if ($channel === CompanyCommunicationSetting::CHANNEL_IN_APP && (int) $recipient->id <= 0) {
                            $stats['missing_recipient']++;
                            continue;
                        }

                        $dedupeKey = implode(':', [
                            'vendor-reminder',
                            (int) $invoice->id,
                            $event,
                            $channel,
                            'recipient',
                            (int) $recipient->id,
                            now()->toDateString(),
                        ]);

                        $log = VendorCommunicationLog::query()->firstOrCreate(
                            [
                                'company_id' => (int) $invoice->company_id,
                                'dedupe_key' => $dedupeKey,
                            ],
                            [
                                'vendor_id' => (int) $invoice->vendor_id,
                                'vendor_invoice_id' => (int) $invoice->id,
                                'recipient_user_id' => (int) $recipient->id,
                                'event' => $event,
                                'channel' => $channel,
                                'status' => 'queued',
                                'recipient_email' => $recipientEmail !== '' ? $recipientEmail : null,
                                'recipient_phone' => $recipientPhone !== '' ? $recipientPhone : null,
                                'reminder_date' => now()->toDateString(),
                                'message' => 'Internal reminder queued for finance.',
                                'metadata' => [
                                    'invoice_number' => (string) $invoice->invoice_number,
                                    'currency' => strtoupper((string) ($invoice->currency ?: 'NGN')),
                                    'outstanding_amount' => (int) $invoice->outstanding_amount,
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
                        ProcessVendorCommunicationLog::dispatch((int) $log->id);
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

        return array_values(array_intersect(
            $settings->selectableChannels(),
            [
                CompanyCommunicationSetting::CHANNEL_IN_APP,
                CompanyCommunicationSetting::CHANNEL_EMAIL,
            ]
        ));
    }

    private function eventForInvoice(VendorInvoice $invoice, Carbon $today): string
    {
        $dueDate = $invoice->due_date?->copy()->startOfDay();
        if (! $dueDate) {
            return 'vendor.internal.due_today.reminder';
        }

        if ($dueDate->lt($today)) {
            return 'vendor.internal.overdue.reminder';
        }

        if ($dueDate->equalTo($today)) {
            return 'vendor.internal.due_today.reminder';
        }

        return 'vendor.internal.due_soon.reminder';
    }
}
