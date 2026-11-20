<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasActiveFlag;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
