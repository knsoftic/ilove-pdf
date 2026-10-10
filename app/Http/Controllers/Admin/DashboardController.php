<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdUnit;
use App\Models\ToolUsage;
use App\Services\Pdf\TempStorage;
use App\Services\Seo\SeoAudit;
use App\Services\System\Capabilities;
use App\Services\Tools\ToolRegistry;
use App\Services\Translation\TranslationManager;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(ToolRegistry $registry, TempStorage $storage, Capabilities $capabilities, TranslationManager $translation, SeoAudit $audit): View
    {
        $from = Carbon::today()->subDays(29);
        $usage = ToolUsage::query()->where('date', '>=', $from->toDateString())->get();

        $days = [];

        for ($d = $from->copy(); $d->lte(Carbon::today()); $d->addDay()) {
            $days[$d->toDateString()] = 0;
        }

        foreach ($usage as $row) {
            $key = $row->date->toDateString();
            $days[$key] = ($days[$key] ?? 0) + $row->count;
        }

        $topTools = $usage->groupBy('tool')
            ->map(fn ($rows, $tool) => [
                'tool' => $registry->find($tool)?->name() ?? $tool,
                'browser' => $rows->where('engine', 'browser')->sum('count'),
                'server' => $rows->where('engine', 'server')->sum('count'),
                'total' => $rows->sum('count'),
            ])
            ->sortByDesc('total')
            ->take(10)
            ->values();

        $jobs = $storage->all();
        $tempBytes = 0;

        foreach ($jobs as $id) {
            try {
                $tempBytes += $storage->size($id);
            } catch (Throwable) {
                // folder removed meanwhile
            }
        }

        return view('admin.dashboard', [
            'days' => $days,
            'total30' => array_sum($days),
            'today' => $days[Carbon::today()->toDateString()] ?? 0,
            'topTools' => $topTools,
            'activeJobs' => count($jobs),
            'tempBytes' => $tempBytes,
            'toolsEnabled' => $registry->enabled()->count(),
            'toolsTotal' => $registry->all()->count(),
            'unavailable' => $registry->enabled()->filter(fn ($tool) => $registry->enginesFor($tool) === [])->values(),
            'capabilities' => $capabilities->report(),
            'translation' => ['available' => $translation->available(), 'engine' => $translation->driver()->name()],
            'seoScore' => $audit->run()['score'],
            'adsEnabled' => (bool) setting('ads_enabled'),
            'adUnits' => AdUnit::query()->where('is_active', true)->count(),
        ]);
    }
}
