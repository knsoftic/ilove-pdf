<?php

namespace App\Models;

use App\Services\Ads\AdManager;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $network
 * @property string $format
 * @property string $placement
 * @property string|null $code
 * @property string|null $url
 * @property array|null $options
 * @property string $devices
 * @property string $page_scope
 * @property array|null $tools
 * @property bool $is_active
 * @property int $sort_order
 */
class AdUnit extends Model
{
    protected $fillable = [
        'name', 'network', 'format', 'placement', 'code', 'url', 'options',
        'devices', 'page_scope', 'tools', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'tools' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => AdManager::flush());
        static::deleted(fn () => AdManager::flush());
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
