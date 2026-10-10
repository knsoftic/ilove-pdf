<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Services\Stats\UsageRecorder;
use App\Services\Tools\ToolRegistry;
use Illuminate\Http\Response;

/**
 * Counts a completed in-browser task (anonymous: tool + day only).
 */
class UsageController extends Controller
{
    public function __invoke(string $tool, ToolRegistry $registry, UsageRecorder $usage): Response
    {
        if ($registry->find($tool)) {
            $usage->record($tool, 'browser');
        }

        return response()->noContent();
    }
}
