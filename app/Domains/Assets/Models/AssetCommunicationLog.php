<?php

namespace App\Domains\Assets\Models;

use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetCommunicationLog extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $table = 'asset_communication_logs';

    protected $fillable = [
        'company_id',
        'asset_id',
        'recipient_user_id',
        'event',
        'channel',
        'status',
        'recipient_email',
        'recipient_phone',
        'reminder_date',
        'dedupe_key',
        'message',
        'metadata',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'reminder_date' => 'date',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}

