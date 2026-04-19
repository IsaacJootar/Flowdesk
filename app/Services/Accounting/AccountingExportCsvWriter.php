<?php

namespace App\Services\Accounting;

use App\Domains\Accounting\Models\AccountingSyncEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AccountingExportCsvWriter
{
    /**
     * @param  Collection<int, AccountingSyncEvent>  $events
     */
    public function write(int $companyId, int $batchId, Collection $events): string
    {
        $handle = fopen('php://temp', 'wb+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, [
            'Date',
            'Reference',
            'Source Type',
            'Description',
            'Debit Account',
            'Credit Account',
            'Amount',
            'Currency',
            'Department',
            'Vendor',
            'Flowdesk Trace ID',
        ]);

        foreach ($events as $event) {
            $metadata = (array) ($event->metadata ?? []);
            fputcsv($handle, [
                $event->event_date?->toDateString() ?: '',
                $this->referenceFor($event, $metadata),
                $this->sourceLabel((string) $event->source_type),
                (string) $event->description,
                (string) $event->debit_account_code,
                (string) ($event->credit_account_code ?? ''),
                (string) ((int) $event->amount),
                (string) $event->currency_code,
                (string) ($metadata['department_name'] ?? ''),
                (string) ($metadata['vendor_name'] ?? ''),
                $this->traceIdFor($event, $metadata),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $path = sprintf(
            'private/accounting-exports/%d/accounting-export-%d-%s.csv',
            $companyId,
            $batchId,
            now()->format('Ymd-His')
        );

        Storage::disk('local')->put($path, (string) $csv);

        return $path;
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private function referenceFor(AccountingSyncEvent $event, array $metadata): string
    {
        return (string) (
            $metadata['expense_code']
            ?? $metadata['request_code']
            ?? $metadata['provider_reference']
            ?? strtoupper((string) $event->source_type).'-'.$event->source_id
        );
    }

    private function sourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'expense' => 'Expense',
            'payout' => 'Payout',
            'vendor_invoice' => 'Vendor invoice',
            'purchase_order' => 'Purchase order',
            default => ucfirst(str_replace('_', ' ', $sourceType)),
        };
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private function traceIdFor(AccountingSyncEvent $event, array $metadata): string
    {
        $requestCode = trim((string) ($metadata['request_code'] ?? ''));
        if ($requestCode !== '') {
            return $requestCode;
        }

        return (string) $event->source_type.':'.(string) $event->source_id;
    }
}
