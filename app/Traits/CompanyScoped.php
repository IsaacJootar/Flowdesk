<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait CompanyScoped
{
    protected static function bootCompanyScoped(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->getAttribute('company_id') && \Illuminate\Support\Facades\Auth::check()) {
                $model->setAttribute('company_id', (int) \Illuminate\Support\Facades\Auth::user()->company_id);
            }
        });

        static::addGlobalScope('company', function (Builder $builder): void {
            if (\Illuminate\Support\Facades\Auth::check()) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    \Illuminate\Support\Facades\Auth::user()->company_id
                );
            }
        });
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable().'.company_id', $companyId);
    }
}
