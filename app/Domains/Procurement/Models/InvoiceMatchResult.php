<?php

namespace App\Domains\Procurement\Models;

use App\Domains\Vendors\Models\VendorInvoice;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceMatchResult extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_MISMATCH = 'mismatch';
    public const STATUS_OVERRIDDEN = 'overridden';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_MATCHED,
        self::STATUS_MISMATCH,
        self::STATUS_OVERRIDDEN,
    ];

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'vendor_invoice_id',
        'match_status',
        'match_score',
        'mismatch_reason',
        'matched_at',
        'resolved_at',
        'resolved_by_user_id',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'decimal:2',
            'matched_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id')->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(InvoiceMatchException::class, 'invoice_match_result_id');
    }
}
