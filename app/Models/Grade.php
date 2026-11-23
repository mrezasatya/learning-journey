<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasActiveFlag;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
