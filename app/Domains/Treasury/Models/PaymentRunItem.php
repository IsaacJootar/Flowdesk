<?php

namespace App\Domains\Treasury\Models;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Vendors\Models\VendorInvoicePayment;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRunItem extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'company_id',
        'payment_run_id',
        'request_payout_execution_attempt_id',
        'vendor_invoice_payment_id',
        'expense_id',
        'item_reference',
        'item_status',
        'amount',
        'currency_code',
        'provider_reference',
        'processed_at',
        'settled_at',
        'failed_at',
        'failure_reason',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'datetime',
            'settled_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PaymentRun::class, 'payment_run_id');
    }

    public function payoutAttempt(): BelongsTo
    {
        return $this->belongsTo(RequestPayoutExecutionAttempt::class, 'request_payout_execution_attempt_id');
    }

    public function vendorPayment(): BelongsTo
    {
        return $this->belongsTo(VendorInvoicePayment::class, 'vendor_invoice_payment_id')->withTrashed();
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
