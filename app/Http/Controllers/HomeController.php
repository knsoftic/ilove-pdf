<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoManager;
use App\Services\Settings\SettingsRepository;
use App\Services\Tools\ToolRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(ToolRegistry $registry, SeoManager $seo, SettingsRepository $settings): View
    {
        $popular = $registry->popular();

        return view('home', [
            'seo' => $seo->home(),
            'popular' => $popular,
            'groups' => $registry->grouped(),
            'toolCount' => $registry->enabled()->count(),
            'maxUpload' => $settings->maxUploadBytes(),
            // Tools offered after dropping a file on the home page.
            'quickTools' => $registry->enabled()
                ->filter(fn ($tool) => $tool->isPopular() || $tool->inNav())
                ->map(fn ($tool) => $tool->toArray() + [
                    'accept' => $registry->extensionsFor($tool),
                    'multiple' => $tool->multiple(),
                    'color' => $registry->categoryColor($tool->category()),
                    'svg' => Blade::render('<x-icon :name="$name" class="size-5" />', ['name' => $tool->icon()]),
                ])
                ->values(),
        ]);
    }
}
