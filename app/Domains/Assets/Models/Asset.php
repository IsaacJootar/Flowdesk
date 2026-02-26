<?php

namespace App\Domains\Assets\Models;

use App\Domains\Company\Models\Department;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'assets';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_MAINTENANCE = 'in_maintenance';
    public const STATUS_DISPOSED = 'disposed';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_MAINTENANCE,
        self::STATUS_DISPOSED,
    ];

    protected $fillable = [
        'company_id',
        'asset_category_id',
        'asset_code',
        'name',
        'serial_number',
        'acquisition_date',
        'purchase_amount',
        'currency',
        'status',
        'condition',
        'notes',
        'assigned_to_user_id',
        'assigned_department_id',
        'assigned_at',
        'disposed_at',
        'disposal_reason',
        'salvage_amount',
        'last_maintenance_at',
        'maintenance_due_date',
        'warranty_expires_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'assigned_at' => 'datetime',
            'disposed_at' => 'datetime',
            'last_maintenance_at' => 'date',
            'maintenance_due_date' => 'date',
            'warranty_expires_at' => 'date',
            'purchase_amount' => 'integer',
            'salvage_amount' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class);
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(AssetCommunicationLog::class);
    }
}
