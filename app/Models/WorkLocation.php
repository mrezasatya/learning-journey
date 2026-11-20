<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;

class WorkLocation extends Model
{
    use HasActiveFlag;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public const TYPES = ['HO' => 'Head Office', 'PLANT' => 'Plant', 'SITE' => 'Site/Proyek'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
