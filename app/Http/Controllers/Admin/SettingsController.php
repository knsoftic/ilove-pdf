<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsRepository;
use App\Services\Tools\FileTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function edit(): View
    {
        return view('admin.settings', [
            's' => $this->settings->all(),
            'groups' => FileTypes::GROUPS,
            'phpLimit' => min(php_size_to_bytes(ini_get('upload_max_filesize')), php_size_to_bytes(ini_get('post_max_size'))),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'site_tagline' => ['nullable', 'string', 'max:120'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'privacy_notice' => ['required', 'string', 'max:300'],
            'max_upload_mb' => ['nullable', 'integer', 'min:1', 'max:2048'],
            'max_files' => ['nullable', 'integer', 'min:1', 'max:200'],
            'engine_preference' => ['required', 'in:browser,server'],
            'stats_enabled' => ['nullable', 'boolean'],
            'allowed' => ['nullable', 'array'],
            'allowed.*' => ['array'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
            'og_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'remove_og_image' => ['nullable', 'boolean'],
        ]);

        // Allowed file types: only extensions that the group actually supports.
        $allowed = [];

        foreach (FileTypes::GROUPS as $group => $definition) {
            $selected = array_values(array_intersect($definition['extensions'], (array) ($data['allowed'][$group] ?? [])));
            // Nothing selected means "all" – a group can't be disabled here (disable tools instead).
            $allowed[$group] = count($selected) === count($definition['extensions']) ? [] : $selected;
        }

        $values = [
            'site_name' => $data['site_name'],
            'site_tagline' => $data['site_tagline'] ?? null,
            'footer_text' => $data['footer_text'] ?? null,
            'privacy_notice' => $data['privacy_notice'],
            'max_upload_mb' => $data['max_upload_mb'] ?? null,
            'max_files' => $data['max_files'] ?? null,
            'engine_preference' => $data['engine_preference'],
            'stats_enabled' => $request->boolean('stats_enabled'),
            'allowed_types' => array_filter($allowed),
        ];

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path', 'og_image' => 'og_image_path'] as $input => $key) {
            if ($request->boolean('remove_'.$input)) {
                $this->deleteBranding($this->settings->get($key));
                $values[$key] = null;
            }

            if ($request->file($input) instanceof UploadedFile) {
                $this->deleteBranding($this->settings->get($key));
                $values[$key] = $this->storeBranding($request->file($input));
            }
        }

        $this->settings->set($values);

        return back()->with('status', 'Settings saved.');
    }

    /**
     * Branding images live in public/uploads/branding (no symlink needed).
     * SVG is not accepted because it can carry scripts.
     */
    private function storeBranding(UploadedFile $file): string
    {
        $dir = public_path('uploads/branding');
        File::ensureDirectoryExists($dir);

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $extension = in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'ico'], true) ? $extension : 'png';
        $name = Str::random(24).'.'.$extension;
        $file->move($dir, $name);

        return 'uploads/branding/'.$name;
    }

    private function deleteBranding(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/branding/') && ! str_contains($path, '..')) {
            File::delete(public_path($path));
        }
    }
}
