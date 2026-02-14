<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait CompanyScoped
{
    protected static function bootCompanyScoped(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->getAttribute('company_id') && auth()->check()) {
                $model->setAttribute('company_id', (int) auth()->user()->company_id);
            }
        });

        static::addGlobalScope('company', function (Builder $builder): void {
            if (auth()->check()) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    auth()->user()->company_id
                );
            }
        });
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable().'.company_id', $companyId);
    }
}
