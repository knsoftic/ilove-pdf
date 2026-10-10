<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Services\Seo\Redirector;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 301/302/410 redirects and the 404 monitor.
 */
class RedirectController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(Request $request): View
    {
        return view('admin.seo.redirects', [
            'redirects' => Redirect::query()->orderByDesc('updated_at')->paginate(50)->withQueryString(),
            'notFound' => NotFoundLog::query()->orderByDesc('hits')->orderByDesc('last_seen_at')->limit(100)->get(),
            'notFoundTotal' => NotFoundLog::query()->count(),
            'logEnabled' => (bool) $this->settings->get('log_404', true),
            'prefill' => mb_substr((string) $request->query('source', ''), 0, 255),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'status_code' => ['required', Rule::in(array_keys(Redirect::CODES))],
            'target' => ['nullable', 'required_unless:status_code,410', 'string', 'max:2048'],
        ], [
            'target.required_unless' => 'Enter where visitors should be sent.',
        ]);

        $source = Redirector::normalize($data['source']);
        $admin = '/'.trim(config('pdftoolkit.admin.path', 'admin'), '/');
        $target = trim((string) ($data['target'] ?? ''));

        $errors = match (true) {
            preg_match('#^[a-z][a-z0-9+.-]*:#i', $data['source']) && ! str_starts_with($data['source'], url('/')) => ['source' => 'The old URL must be a path on this website, e.g. /old-page.'],
            $source === '/' => ['source' => 'The home page cannot be redirected.'],
            $source === $admin || str_starts_with($source, $admin.'/') => ['source' => 'The admin area cannot be redirected.'],
            (int) $data['status_code'] !== 410 && ! (str_starts_with($target, '/') || preg_match('#^https?://#i', $target)) => ['target' => 'The new URL must start with / or https://'],
            (int) $data['status_code'] !== 410 && Redirector::normalize($target) === $source && str_starts_with($target, '/') => ['target' => 'A page cannot redirect to itself.'],
            default => [],
        };

        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }

        Redirect::query()->updateOrCreate(['source' => $source], [
            'target' => (int) $data['status_code'] === 410 ? null : $target,
            'status_code' => (int) $data['status_code'],
        ]);

        NotFoundLog::query()->where('path', $source)->delete();

        return redirect()->route('admin.seo.redirects')->with('status', "Redirect for {$source} saved.");
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $redirect->delete();

        return back()->with('status', 'Redirect deleted.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $this->settings->set(['log_404' => $request->boolean('log_404')]);

        return back()->with('status', '404 monitor settings saved.');
    }

    public function clear(): RedirectResponse
    {
        NotFoundLog::query()->delete();

        return back()->with('status', '404 log cleared.');
    }

    public function forget(NotFoundLog $log): RedirectResponse
    {
        $log->delete();

        return back()->with('status', 'Entry removed from the 404 log.');
    }
}
