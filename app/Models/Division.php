<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasActiveFlag;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function employees()
    {
        return $this->hasManyThrough(Employee::class, Department::class);
    }
}
