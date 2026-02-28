<?php

namespace App\Domains\Company\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsageCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'snapshot_at',
        'active_users',
        'seat_limit',
        'seat_utilization_percent',
        'requests_count',
        'expenses_count',
        'vendors_count',
        'assets_count',
        'warning_level',
        'captured_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_at' => 'datetime',
            'seat_utilization_percent' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function capturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}

