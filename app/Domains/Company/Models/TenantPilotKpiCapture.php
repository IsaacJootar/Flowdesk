<?php

namespace App\Domains\Company\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPilotKpiCapture extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'window_label',
        'window_start',
        'window_end',
        'match_pass_rate_percent',
        'open_procurement_exceptions',
        'procurement_exception_avg_open_hours',
        'auto_reconciliation_rate_percent',
        'open_treasury_exceptions',
        'treasury_exception_avg_open_hours',
        'blocked_payout_count',
        'manual_override_count',
        'incident_count',
        'incident_rate_per_week',
        'metadata',
        'notes',
        'captured_at',
        'captured_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'match_pass_rate_percent' => 'decimal:2',
            'procurement_exception_avg_open_hours' => 'decimal:2',
            'auto_reconciliation_rate_percent' => 'decimal:2',
            'treasury_exception_avg_open_hours' => 'decimal:2',
            'incident_rate_per_week' => 'decimal:2',
            'metadata' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }
}
