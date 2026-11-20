<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyTemplate extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['items' => 'array', 'is_default' => 'boolean', 'is_active' => 'boolean'];

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::first();
    }

    /** @return array<array{code:string,text:string,type:string}> */
    public function likertItems(): array
    {
        return array_values(array_filter($this->items, fn ($i) => $i['type'] === 'LIKERT'));
    }
}
