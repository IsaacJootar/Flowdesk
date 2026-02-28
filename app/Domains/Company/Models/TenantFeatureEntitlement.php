<?php

namespace App\Domains\Company\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeatureEntitlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'requests_enabled',
        'expenses_enabled',
        'vendors_enabled',
        'budgets_enabled',
        'assets_enabled',
        'reports_enabled',
        'communications_enabled',
        'ai_enabled',
        'fintech_enabled',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'requests_enabled' => 'boolean',
            'expenses_enabled' => 'boolean',
            'vendors_enabled' => 'boolean',
            'budgets_enabled' => 'boolean',
            'assets_enabled' => 'boolean',
            'reports_enabled' => 'boolean',
            'communications_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'fintech_enabled' => 'boolean',
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

