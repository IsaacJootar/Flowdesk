<?php

namespace App\Domains\Requests\Models;

use App\Domains\Vendors\Models\Vendor;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestItem extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'request_items';

    protected $fillable = [
        'company_id',
        'request_id',
        'item_name',
        'description',
        'quantity',
        'unit_cost',
        'line_total',
        'vendor_id',
        'category',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'line_total' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpendRequest::class, 'request_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}

