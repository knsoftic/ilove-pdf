<?php

use App\Services\Settings\SettingsRepository;

if (! function_exists('setting')) {
    /**
     * Read a site setting (database value or config default).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsRepository::class)->get($key, $default);
    }
}

if (! function_exists('format_bytes')) {
    function format_bytes(int|float|null $bytes, int $precision = 1): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return round($bytes / (1024 ** $power), $power === 0 ? 0 : $precision).' '.$units[$power];
    }
}

if (! function_exists('php_size_to_bytes')) {
    /**
     * Converts php.ini sizes like "128M" to bytes.
     */
    function php_size_to_bytes(string|false $value): int
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
