<?php

namespace App\Domains\Vendors\Models;

use App\Domains\Expenses\Models\Expense;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'vendors';

    protected $fillable = [
        'company_id',
        'name',
        'vendor_type',
        'contact_person',
        'phone',
        'email',
        'address',
        'bank_name',
        'bank_code',
        'account_name',
        'account_number',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(VendorInvoicePayment::class);
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(VendorCommunicationLog::class);
    }
}
