<?php

namespace App\Domains\Vendors\Models;

use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorCommunicationLog extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $table = 'vendor_communication_logs';

    protected $fillable = [
        'company_id',
        'vendor_id',
        'vendor_invoice_id',
        'recipient_user_id',
        'event',
        'channel',
        'status',
        'recipient_email',
        'recipient_phone',
        'reminder_date',
        'dedupe_key',
        'message',
        'metadata',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'reminder_date' => 'date',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
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

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
