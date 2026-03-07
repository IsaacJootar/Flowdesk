<?php

namespace App\Domains\Fintech\Models;

use App\Domains\Company\Models\Company;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPaymentRailSetting extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const STATUS_NOT_CONNECTED = 'not_connected';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'company_id',
        'provider_key',
        'connection_status',
        'connected_at',
        'paused_at',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
        'last_synced_at',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
            'paused_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'provider_key' => null,
            'connection_status' => self::STATUS_NOT_CONNECTED,
            'connected_at' => null,
            'paused_at' => null,
            'last_tested_at' => null,
            'last_test_status' => null,
            'last_test_message' => null,
            'last_synced_at' => null,
            'metadata' => null,
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
