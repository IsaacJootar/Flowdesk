<?php

namespace App\Domains\Requests\Models;

use App\Domains\Approvals\Models\RequestApproval;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestCommunicationLog extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $table = 'request_communication_logs';

    protected $fillable = [
        'company_id',
        'request_id',
        'request_approval_id',
        'recipient_user_id',
        'event',
        'channel',
        'status',
        'message',
        'metadata',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpendRequest::class, 'request_id');
    }

    public function requestApproval(): BelongsTo
    {
        return $this->belongsTo(RequestApproval::class, 'request_approval_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
