<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait CompanyScoped
{
    protected static function bootCompanyScoped(): void
    {
        static::creating(function (Model $model): void {
            $companyId = self::resolveCompanyScopeId();

            if (! $model->getAttribute('company_id') && $companyId !== null) {
                $model->setAttribute('company_id', $companyId);
            }
        });

        static::addGlobalScope('company', function (Builder $builder): void {
            $companyId = self::resolveCompanyScopeId();

            if ($companyId !== null) {
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
            }
        });
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable().'.company_id', $companyId);
    }

    private static function resolveCompanyScopeId(): ?int
    {
        if (Auth::check() && Auth::user()?->company_id) {
            return (int) Auth::user()->company_id;
        }

        return app(\App\Support\TenantContext::class)->companyId();
    }
}
