<?php

namespace App\Domains\Requests\Models;

use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestComment extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $table = 'request_comments';

    protected $fillable = [
        'company_id',
        'request_id',
        'user_id',
        'body',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpendRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

