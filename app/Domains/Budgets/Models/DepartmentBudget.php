<?php

namespace App\Domains\Budgets\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepartmentBudget extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'department_budgets';
}
