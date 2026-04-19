<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Company\Models\Company;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingExportBatch extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'from_date',
        'to_date',
        'status',
        'row_count',
        'warning_count',
        'file_path',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'row_count' => 'integer',
            'warning_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function syncEvents(): HasMany
    {
        return $this->hasMany(AccountingSyncEvent::class, 'export_batch_id');
    }
}
