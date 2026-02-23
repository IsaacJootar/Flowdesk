<?php

namespace App\Domains\Requests\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySpendCategory extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $table = 'company_spend_categories';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Company\Models\Company::class);
    }
}
