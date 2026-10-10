<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Services\Seo\SeoManager;
use App\Services\System\Capabilities;
use App\Services\Tools\ToolRegistry;
use App\Services\Translation\TranslationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders tool pages. Every tool has its own SEO friendly URL such as
 * /merge-pdf or /pdf-to-word (see routes/web.php).
 */
class ToolController extends Controller
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly SeoManager $seo,
    ) {}

    public function index(Request $request): View
    {
        return view('tools.index', [
            'seo' => $this->seo->toolsIndex(),
            'groups' => $this->registry->grouped(),
            'total' => $this->registry->enabled()->count(),
            'query' => mb_substr((string) $request->query('q', ''), 0, 100),
        ]);
    }

    public function show(string $tool, Capabilities $capabilities, TranslationManager $translation): View|RedirectResponse
    {
        if (isset($this->registry->aliases()[$tool])) {
            return redirect()->route('tools.show', $this->registry->aliases()[$tool], 301);
        }

        $tool = $this->registry->findEnabledOrFail($tool);
        $engines = $this->registry->enginesFor($tool);

        $view = match ($tool->ui()) {
            'editor' => 'editor.editor',
            'organize' => 'pdf.organize',
            'text' => 'pdf.text',
            'translator' => 'pdf.translator',
            default => 'tools.show',
        };

        return view($view, [
            'tool' => $tool,
            'seo' => $this->seo->tool($tool),
            'config' => $this->registry->clientConfig($tool),
            'engines' => $engines,
            'color' => $this->registry->categoryColor($tool->category()),
            'category' => $this->registry->categories()[$tool->category()] ?? null,
            'related' => $this->registry->related($tool),
            'acceptLabel' => $this->registry->acceptLabel($tool),
            'capabilities' => $capabilities,
            'translation' => $tool->ui() === 'translator' ? [
                'languages' => $translation->languages(),
                'server' => $translation->available(),
                'engine' => $translation->driver()->name(),
            ] : null,
        ]);
    }
}
