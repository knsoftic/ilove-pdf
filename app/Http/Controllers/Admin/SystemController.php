<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Pdf\TempCleaner;
use App\Services\Pdf\TempStorage;
use App\Services\System\Capabilities;
use App\Services\Tools\ToolRegistry;
use App\Services\Translation\TranslationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function index(Capabilities $capabilities, TempStorage $storage, TranslationManager $translation, ToolRegistry $registry): View
    {
        return view('admin.system', [
            'report' => $capabilities->report(),
            'php' => [
                'version' => PHP_VERSION,
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'queue' => config('pdftoolkit.queue.connection') ?: config('queue.default'),
            'tempPath' => $storage->root(),
            'jobs' => count($storage->all()),
            'translation' => ['available' => $translation->available(), 'engine' => $translation->driver()->name(), 'driver' => config('pdftoolkit.translation.driver')],
            'engines' => $registry->all()->map(fn ($tool) => ['tool' => $tool, 'engines' => $registry->enginesFor($tool)]),
        ]);
    }

    public function cleanup(TempCleaner $cleaner): RedirectResponse
    {
        $result = $cleaner->run();

        return back()->with('status', "Cleanup finished: {$result['deleted']} expired folder(s) removed, ".format_bytes($result['freed']).' freed.');
    }

    public function refresh(Capabilities $capabilities): RedirectResponse
    {
        $capabilities->refresh();

        return back()->with('status', 'Server capabilities detected again.');
    }
}
