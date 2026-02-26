<?php

namespace App\Domains\Assets\Models;

use App\Domains\Company\Models\Department;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetEvent extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const TYPE_CREATED = 'created';
    public const TYPE_UPDATED = 'updated';
    public const TYPE_ASSIGNED = 'assigned';
    public const TYPE_TRANSFERRED = 'transferred';
    public const TYPE_RETURNED = 'returned';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_DISPOSED = 'disposed';

    protected $table = 'asset_events';

    protected $fillable = [
        'company_id',
        'asset_id',
        'event_type',
        'event_date',
        'actor_user_id',
        'target_user_id',
        'target_department_id',
        'amount',
        'currency',
        'summary',
        'details',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }
}
