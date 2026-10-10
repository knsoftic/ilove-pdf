<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Privacy policy, terms and about page (Markdown).
 */
class ContentController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function edit(): View
    {
        $defaults = [];

        foreach (['privacy', 'terms', 'about'] as $page) {
            $defaults[$page] = view('pages.defaults.'.$page, [
                'site' => $this->settings->get('site_name'),
                'ttl' => config('pdftoolkit.job_ttl'),
            ])->render();
        }

        return view('admin.content', ['s' => $this->settings->all(), 'defaults' => $defaults]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'page_privacy' => ['nullable', 'string', 'max:100000'],
            'page_terms' => ['nullable', 'string', 'max:100000'],
            'page_about' => ['nullable', 'string', 'max:100000'],
        ]);

        $this->settings->set(array_map(fn ($value) => $value === null || trim($value) === '' ? null : $value, $data));

        return back()->with('status', 'Pages saved.');
    }
}
