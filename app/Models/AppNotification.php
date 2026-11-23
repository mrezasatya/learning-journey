<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $guarded = ['id'];

    protected $casts = ['sent_at' => 'datetime', 'read_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
