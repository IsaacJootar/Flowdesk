<?php

namespace App\Domains\Approvals\Models;

use App\Domains\Requests\Models\SpendRequest;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalWorkflow extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    public const APPLIES_TO_REQUEST = 'request';

    public const APPLIES_TO_PAYMENT_AUTHORIZATION = 'payment_authorization';

    protected $table = 'approval_workflows';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'applies_to',
        'description',
        'is_active',
        'is_default',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function supportedAppliesTo(): array
    {
        return [
            self::APPLIES_TO_REQUEST,
            self::APPLIES_TO_PAYMENT_AUTHORIZATION,
        ];
    }

    public static function labelForAppliesTo(string $appliesTo): string
    {
        return match ($appliesTo) {
            self::APPLIES_TO_PAYMENT_AUTHORIZATION => 'Payment Authorization',
            default => 'Request Approval',
        };
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_id')
            ->orderBy('step_order');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(SpendRequest::class, 'workflow_id');
    }
}
