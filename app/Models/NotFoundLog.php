<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $path
 * @property int $hits
 */
class NotFoundLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['path', 'hits', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }
}
