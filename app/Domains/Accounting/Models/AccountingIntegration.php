<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Company\Models\Company;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingIntegration extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'provider',
        'status',
        'external_tenant_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'last_synced_at',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
