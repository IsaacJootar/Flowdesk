<?php

namespace App\Domains\Vendors\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorInvoice extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PART_PAID = 'part_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_VOID = 'void';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_UNPAID,
        self::STATUS_PART_PAID,
        self::STATUS_PAID,
        self::STATUS_VOID,
    ];

    /**
     * Display-only status options (includes computed overdue state).
     *
     * @var array<int, string>
     */
    public const DISPLAY_STATUSES = [
        self::STATUS_UNPAID,
        self::STATUS_PART_PAID,
        self::STATUS_OVERDUE,
        self::STATUS_PAID,
        self::STATUS_VOID,
    ];

    protected $fillable = [
        'company_id',
        'vendor_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'currency',
        'total_amount',
        'paid_amount',
        'outstanding_amount',
        'status',
        'description',
        'notes',
        'created_by',
        'updated_by',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorInvoicePayment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(VendorInvoiceAttachment::class, 'vendor_invoice_id');
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(VendorCommunicationLog::class, 'vendor_invoice_id');
    }
}
