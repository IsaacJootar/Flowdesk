<?php

namespace App\Domains\Company\Models;

use App\Domains\Requests\Models\CompanyRequestType;
use App\Domains\Requests\Models\CompanyRequestPolicySetting;
use App\Domains\Requests\Models\CompanySpendCategory;
use App\Domains\Approvals\Models\CompanyApprovalTimingSetting;
use App\Domains\Approvals\Models\DepartmentApprovalTimingOverride;
use App\Domains\Expenses\Models\CompanyExpensePolicySetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'industry',
        'currency_code',
        'timezone',
        'address',
        'is_active',
        'lifecycle_status',
        'status_reason',
        'status_updated_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status_updated_at' => 'datetime',
        ];
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function requestTypes(): HasMany
    {
        return $this->hasMany(CompanyRequestType::class);
    }

    public function spendCategories(): HasMany
    {
        return $this->hasMany(CompanySpendCategory::class);
    }

    public function communicationSetting(): HasOne
    {
        return $this->hasOne(CompanyCommunicationSetting::class);
    }

    public function requestPolicySetting(): HasOne
    {
        return $this->hasOne(CompanyRequestPolicySetting::class);
    }

    public function expensePolicySetting(): HasOne
    {
        return $this->hasOne(CompanyExpensePolicySetting::class);
    }

    public function approvalTimingSetting(): HasOne
    {
        return $this->hasOne(CompanyApprovalTimingSetting::class);
    }

    public function approvalTimingOverrides(): HasMany
    {
        return $this->hasMany(DepartmentApprovalTimingOverride::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class);
    }

    public function featureEntitlements(): HasOne
    {
        return $this->hasOne(TenantFeatureEntitlement::class);
    }

    public function manualPayments(): HasMany
    {
        return $this->hasMany(TenantManualPayment::class);
    }

    public function planChangeHistory(): HasMany
    {
        return $this->hasMany(TenantPlanChangeHistory::class);
    }

    public function billingLedgerEntries(): HasMany
    {
        return $this->hasMany(TenantBillingLedgerEntry::class);
    }

    public function billingAllocations(): HasMany
    {
        return $this->hasMany(TenantBillingAllocation::class);
    }

    public function usageCounters(): HasMany
    {
        return $this->hasMany(TenantUsageCounter::class);
    }

    public function tenantAuditEvents(): HasMany
    {
        return $this->hasMany(TenantAuditEvent::class);
    }
}
