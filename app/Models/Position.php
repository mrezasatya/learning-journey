<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasActiveFlag;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public const LEVELS = ['Staf', 'Senior Staf', 'Section Head', 'Dept. Head', 'Division Head'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
