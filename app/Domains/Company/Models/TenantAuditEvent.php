<?php

namespace App\Domains\Company\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAuditEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'metadata',
        'event_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'event_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

