<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Company\Models\Company;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingProviderAccount extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'provider',
        'provider_account_id',
        'account_code',
        'account_name',
        'account_type',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
