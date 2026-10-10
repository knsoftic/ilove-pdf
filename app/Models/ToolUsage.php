<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToolUsage extends Model
{
    public $timestamps = false;

    protected $fillable = ['tool', 'engine', 'date', 'count'];

    protected function casts(): array
    {
        return ['date' => 'date', 'count' => 'integer'];
    }
}
