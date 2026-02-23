<?php

namespace App\Domains\Requests\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyRequestType extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $table = 'company_request_types';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'is_active',
        'requires_amount',
        'requires_line_items',
        'requires_date_range',
        'requires_vendor',
        'requires_attachments',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_amount' => 'boolean',
            'requires_line_items' => 'boolean',
            'requires_date_range' => 'boolean',
            'requires_vendor' => 'boolean',
            'requires_attachments' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Company\Models\Company::class);
    }
}
