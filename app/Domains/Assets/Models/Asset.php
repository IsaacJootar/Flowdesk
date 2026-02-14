<?php

namespace App\Domains\Assets\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'assets';
}
