<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdUnit;
use App\Services\Ads\AdCatalog;
use App\Services\Settings\SettingsRepository;
use App\Services\Tools\ToolRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Ad units (Adsterra, Monetag, AdSense, custom) and site-wide ad settings.
 * Ad code is trusted HTML entered by the site owner and rendered as-is.
 */
class AdsController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ToolRegistry $registry,
    ) {}

    public function index(): View
    {
        return view('admin.ads.index', [
            's' => $this->settings->all(),
            'units' => AdUnit::query()->orderBy('placement')->orderBy('sort_order')->orderBy('id')->get(),
            'presets' => AdCatalog::presets(),
        ]);
    }

    public function create(Request $request): View
    {
        $network = array_key_exists($request->query('network'), AdCatalog::NETWORKS) ? $request->query('network') : 'adsterra';
        $format = in_array($request->query('format'), AdCatalog::NETWORKS[$network]['formats'], true) ? $request->query('format') : AdCatalog::NETWORKS[$network]['formats'][0];
        $size = array_key_exists((string) $request->query('size'), AdCatalog::SIZES) ? $request->query('size') : null;

        $unit = new AdUnit([
            'name' => AdCatalog::NETWORKS[$network]['name'].' – '.AdCatalog::formatLabel($format, $network).($size ? ' '.str_replace('x', '×', $size) : ''),
            'network' => $network,
            'format' => $format,
            'placement' => AdCatalog::FORMATS[$format]['placement'],
            'devices' => array_key_exists((string) $request->query('devices'), AdCatalog::DEVICES) ? $request->query('devices') : 'all',
            'page_scope' => 'all',
            'is_active' => true,
            'sort_order' => 0,
            'options' => array_filter(['size' => $size, 'frequency' => 30, 'label' => 'Sponsored link']),
        ]);

        return $this->form($unit);
    }

    public function store(Request $request): RedirectResponse
    {
        AdUnit::query()->create($this->validated($request));

        return redirect()->route('admin.ads')->with('status', 'Ad unit created.');
    }

    public function edit(AdUnit $unit): View
    {
        return $this->form($unit);
    }

    public function update(Request $request, AdUnit $unit): RedirectResponse
    {
        $unit->update($this->validated($request));

        return redirect()->route('admin.ads')->with('status', 'Ad unit saved.');
    }

    public function toggle(AdUnit $unit): RedirectResponse
    {
        $unit->update(['is_active' => ! $unit->is_active]);

        return back()->with('status', $unit->is_active ? "“{$unit->name}” is now active." : "“{$unit->name}” is paused.");
    }

    public function destroy(AdUnit $unit): RedirectResponse
    {
        $unit->delete();

        return back()->with('status', 'Ad unit deleted.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ads_enabled' => ['nullable', 'boolean'],
            'ads_label' => ['nullable', 'boolean'],
            'ads_hide_for_admin' => ['nullable', 'boolean'],
            'ads_txt' => ['nullable', 'string', 'max:20000'],
            'sw_filename' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+\.js$/'],
            'sw_code' => ['nullable', 'string', 'max:20000'],
        ], [
            'sw_filename.regex' => 'The service worker file name may only contain letters, numbers, - and _ and must end with .js (e.g. sw.js).',
        ]);

        $this->settings->set([
            'ads_enabled' => $request->boolean('ads_enabled'),
            'ads_label' => $request->boolean('ads_label'),
            'ads_hide_for_admin' => $request->boolean('ads_hide_for_admin'),
            'ads_txt' => isset($data['ads_txt']) ? trim(str_replace("\r\n", "\n", $data['ads_txt'])) : null,
            'sw_filename' => $data['sw_filename'],
            'sw_code' => isset($data['sw_code']) ? trim($data['sw_code']) : null,
        ]);

        return back()->with('status', 'Advertising settings saved.');
    }

    /**
     * Shows labelled placeholders for every ad position while browsing the site.
     */
    public function preview(Request $request): RedirectResponse
    {
        $on = ! $request->session()->get('ads_preview', false);
        $request->session()->put('ads_preview', $on);

        return $on
            ? redirect()->route('home')
            : back()->with('status', 'Ad position preview turned off.');
    }

    private function form(AdUnit $unit): View
    {
        return view('admin.ads.form', [
            'unit' => $unit,
            'tools' => $this->registry->all(),
            'catalog' => AdCatalog::forClient(),
        ]);
    }

    private function validated(Request $request): array
    {
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'network' => ['required', Rule::in(array_keys(AdCatalog::NETWORKS))],
            'format' => ['required', Rule::in(array_keys(AdCatalog::FORMATS))],
            'placement' => ['required', Rule::in(array_keys(AdCatalog::PLACEMENTS))],
            'code' => ['nullable', 'string', 'max:50000'],
            'url' => ['nullable', 'url:https,http', 'max:2048'],
            'size' => ['nullable', Rule::in(array_keys(AdCatalog::SIZES))],
            'label' => ['nullable', 'string', 'max:60'],
            'frequency' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'devices' => ['required', Rule::in(array_keys(AdCatalog::DEVICES))],
            'page_scope' => ['required', Rule::in(array_keys(AdCatalog::PAGE_SCOPES))],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string', Rule::in($this->registry->all()->keys()->all())],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
        ], [
            'url.url' => 'Please enter a complete link starting with https://',
        ]);

        $validator->after(function (Validator $validator) use ($request) {
            $format = (string) $request->input('format');
            $network = (string) $request->input('network');

            if (! isset(AdCatalog::FORMATS[$format], AdCatalog::NETWORKS[$network])) {
                return;
            }

            if (! in_array($format, AdCatalog::NETWORKS[$network]['formats'], true)) {
                $validator->errors()->add('format', AdCatalog::NETWORKS[$network]['name'].' does not offer this ad format.');
            }

            if (! in_array($request->input('placement'), AdCatalog::placementsFor($format), true)) {
                $validator->errors()->add('placement', 'This position is not suitable for the selected ad format.');
            }

            if ($format === 'direct_link' && ! $request->filled('url')) {
                $validator->errors()->add('url', 'Please enter the Direct Link URL.');
            }

            if ($format !== 'direct_link' && trim((string) $request->input('code')) === '') {
                $validator->errors()->add('code', 'Please paste the ad code from your ad network.');
            }

            if (in_array($request->input('page_scope'), ['only', 'except'], true) && empty($request->input('tools'))) {
                $validator->errors()->add('tools', 'Select at least one tool.');
            }
        });

        $data = $validator->validate();
        $isLink = $data['format'] === 'direct_link';

        return [
            'name' => $data['name'],
            'network' => $data['network'],
            'format' => $data['format'],
            'placement' => $data['placement'],
            'code' => $isLink ? null : trim($data['code']),
            'url' => $isLink ? $data['url'] : null,
            'options' => array_filter([
                'size' => $data['format'] === 'banner' ? ($data['size'] ?? null) : null,
                'label' => $isLink ? ($data['label'] ?? null) : null,
                'frequency' => $isLink ? (int) ($data['frequency'] ?? 30) : null,
            ], fn ($value) => $value !== null),
            'devices' => $data['devices'],
            'page_scope' => $data['page_scope'],
            'tools' => in_array($data['page_scope'], ['only', 'except'], true) ? array_values($data['tools'] ?? []) : null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
