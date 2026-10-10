<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Site settings editable from the admin panel.
 *
 * Defaults come from config('pdftoolkit.defaults'); values saved by the admin
 * are stored as JSON in the `settings` table and cached forever until changed.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'pdftoolkit.settings.v1';

    private ?array $values = null;

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $defaults = config('pdftoolkit.defaults', []);

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
                ->pluck('value', 'key')
                ->map(fn ($value) => json_decode($value, true))
                ->all());
        } catch (Throwable) {
            // Database not migrated yet – run on defaults.
            $stored = [];
        }

        return $this->values = array_replace($defaults, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = Arr::get($this->all(), $key);

        return $value ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        }

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
        $this->version++;
    }

    /**
     * Incremented whenever settings change, so services that cache derived
     * data (e.g. the tool registry) know when to rebuild.
     */
    public int $version = 0;

    /**
     * Upload limit in bytes, capped by what PHP itself accepts.
     */
    public function maxUploadBytes(): int
    {
        $configured = (int) ($this->get('max_upload_mb') ?: config('pdftoolkit.limits.max_upload_mb', 100));

        return min(
            $configured * 1024 * 1024,
            php_size_to_bytes(ini_get('upload_max_filesize')),
            php_size_to_bytes(ini_get('post_max_size')),
        );
    }

    public function maxFiles(): int
    {
        return (int) ($this->get('max_files') ?: config('pdftoolkit.limits.max_files', 30));
    }

    public function enginePreference(): string
    {
        return $this->get('engine_preference') ?: config('pdftoolkit.engine_preference', 'browser');
    }
}
