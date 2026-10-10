<?php

namespace App\Services\Stats;

use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Anonymous, aggregated usage counters (tool + engine + day).
 * No IP addresses, file names or any personal data are stored.
 */
class UsageRecorder
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function record(string $tool, string $engine): void
    {
        if (! $this->settings->get('stats_enabled', true)) {
            return;
        }

        try {
            DB::table('tool_usages')->upsert(
                [['tool' => $tool, 'engine' => $engine, 'date' => now()->toDateString(), 'count' => 1]],
                ['tool', 'engine', 'date'],
                ['count' => DB::raw('count + 1')]
            );
        } catch (Throwable) {
            // Statistics must never break processing.
        }
    }
}
