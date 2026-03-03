<?php

namespace App\Domains\Company\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPilotWaveOutcome extends Model
{
    use HasFactory;

    public const OUTCOME_GO = 'go';

    public const OUTCOME_HOLD = 'hold';

    public const OUTCOME_NO_GO = 'no_go';

    protected $fillable = [
        'company_id',
        'wave_label',
        'outcome',
        'decision_at',
        'notes',
        'metadata',
        'decided_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'decision_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
