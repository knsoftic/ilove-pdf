<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsRepository;
use App\Services\Tools\ToolRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToolController extends Controller
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly SettingsRepository $settings,
    ) {}

    public function index(): View
    {
        return view('admin.tools', [
            'categories' => $this->registry->categories(),
            'tools' => $this->registry->all()->groupBy(fn ($tool) => $tool->category()),
            'registry' => $this->registry,
            'overrides' => (array) $this->settings->get('tool_overrides', []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $slugs = $this->registry->all()->keys()->all();

        $data = $request->validate([
            'enabled' => ['nullable', 'array'],
            'enabled.*' => ['string'],
            'names' => ['nullable', 'array'],
            'names.*' => ['nullable', 'string', 'max:80'],
        ]);

        $enabled = array_intersect($slugs, (array) ($data['enabled'] ?? []));

        // Merge: SEO texts of a tool are edited under SEO → Tool pages.
        $overrides = (array) $this->settings->get('tool_overrides', []);

        foreach ((array) ($data['names'] ?? []) as $slug => $name) {
            if (! in_array($slug, $slugs, true)) {
                continue;
            }

            $name = trim((string) $name);
            $overrides[$slug] = array_filter(['name' => $name !== '' ? $name : null] + (array) ($overrides[$slug] ?? []), fn ($value) => $value !== null);

            if ($name === '') {
                unset($overrides[$slug]['name']);
            }

            if ($overrides[$slug] === []) {
                unset($overrides[$slug]);
            }
        }

        $this->settings->set([
            'disabled_tools' => array_values(array_diff($slugs, $enabled)),
            'tool_overrides' => $overrides,
        ]);

        return back()->with('status', 'Tools updated.');
    }
}
