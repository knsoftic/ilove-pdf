<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $source
 * @property string|null $target
 * @property int $status_code
 * @property int $hits
 */
class Redirect extends Model
{
    public const CODES = [
        301 => '301 – Moved permanently (SEO value is passed on)',
        302 => '302 – Temporary redirect',
        410 => '410 – Gone (page removed on purpose)',
    ];

    protected $fillable = ['source', 'target', 'status_code', 'hits', 'last_hit_at'];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }
}
