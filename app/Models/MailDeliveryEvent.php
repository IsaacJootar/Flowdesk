<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailDeliveryEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'message_id',
        'recipient_email',
        'tags',
        'payload',
        'flowdesk_log_id',
        'log_source',
        'event_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'payload' => 'array',
        'event_at' => 'datetime',
    ];
}
