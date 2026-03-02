<?php

namespace App\Domains\Procurement\Models;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PART_RECEIVED = 'part_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELED = 'canceled';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ISSUED,
        self::STATUS_PART_RECEIVED,
        self::STATUS_RECEIVED,
        self::STATUS_INVOICED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELED,
    ];

    protected $fillable = [
        'company_id',
        'spend_request_id',
        'department_budget_id',
        'vendor_id',
        'po_number',
        'po_status',
        'currency_code',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'issued_at',
        'expected_delivery_at',
        'closed_at',
        'canceled_at',
        'cancel_reason',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'issued_at' => 'datetime',
            'expected_delivery_at' => 'date',
            'closed_at' => 'datetime',
            'canceled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpendRequest::class, 'spend_request_id')->withTrashed();
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(DepartmentBudget::class, 'department_budget_id')->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'purchase_order_id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(ProcurementCommitment::class, 'purchase_order_id');
    }

    public function matchResults(): HasMany
    {
        return $this->hasMany(InvoiceMatchResult::class, 'purchase_order_id');
    }

    public function matchExceptions(): HasMany
    {
        return $this->hasMany(InvoiceMatchException::class, 'purchase_order_id');
    }

    public function vendorInvoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class, 'purchase_order_id');
    }
}