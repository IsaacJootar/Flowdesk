<?php

namespace App\Domains\Procurement\Models;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementCommitment extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_REVERSED = 'reversed';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_RELEASED,
        self::STATUS_CONSUMED,
        self::STATUS_REVERSED,
    ];

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'department_budget_id',
        'commitment_status',
        'amount',
        'currency_code',
        'effective_at',
        'released_at',
        'release_reason',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'effective_at' => 'datetime',
            'released_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(DepartmentBudget::class, 'department_budget_id')->withTrashed();
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
