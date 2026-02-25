<?php

namespace App\Domains\Vendors\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorInvoicePayment extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'vendor_id',
        'vendor_invoice_id',
        'payment_reference',
        'amount',
        'payment_date',
        'payment_method',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(VendorInvoicePaymentAttachment::class, 'vendor_invoice_payment_id');
    }
}
