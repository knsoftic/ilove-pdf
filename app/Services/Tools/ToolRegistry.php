<?php

namespace App\Services\Tools;

use App\Services\Settings\SettingsRepository;
use App\Services\System\Capabilities;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Central access to all tools and categories.
 */
class ToolRegistry
{
    /** @var Collection<string, Tool>|null */
    private ?Collection $tools = null;

    private int $settingsVersion = -1;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Capabilities $capabilities,
    ) {}

    /**
     * Every tool, including disabled ones (for the admin panel).
     *
     * @return Collection<string, Tool>
     */
    public function all(): Collection
    {
        if ($this->tools !== null && $this->settingsVersion === $this->settings->version) {
            return $this->tools;
        }

        $this->settingsVersion = $this->settings->version;

        $disabled = (array) $this->settings->get('disabled_tools', []);
        $overrides = (array) $this->settings->get('tool_overrides', []);

        return $this->tools = collect(config('tools.tools', []))
            ->map(fn (array $definition, string $slug) => new Tool(
                $slug,
                $definition,
                array_filter((array) ($overrides[$slug] ?? []), fn ($value) => $value !== null && $value !== ''),
                ! in_array($slug, $disabled, true),
            ));
    }

    /**
     * @return Collection<string, Tool>
     */
    public function enabled(): Collection
    {
        return $this->all()->filter(fn (Tool $tool) => $tool->isEnabled());
    }

    public function find(string $slug): ?Tool
    {
        return $this->all()->get($slug);
    }

    public function findEnabledOrFail(string $slug): Tool
    {
        $tool = $this->find($slug);

        if (! $tool || ! $tool->isEnabled()) {
            throw new NotFoundHttpException;
        }

        return $tool;
    }

    /**
     * alias => canonical slug
     *
     * @return array<string, string>
     */
    public function aliases(): array
    {
        $aliases = [];

        foreach ($this->all() as $tool) {
            foreach ($tool->aliases() as $alias) {
                $aliases[$alias] = $tool->slug;
            }
        }

        return $aliases;
    }

    public function categories(): array
    {
        return config('tools.categories', []);
    }

    /**
     * Enabled tools grouped by category (a tool can appear in several).
     *
     * @return array<string, array{category: array, tools: Collection<string, Tool>}>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->categories() as $key => $category) {
            $tools = $this->enabled()->filter(fn (Tool $tool) => in_array($key, $tool->categories(), true));

            if ($tools->isNotEmpty()) {
                $groups[$key] = ['key' => $key, 'category' => $category, 'tools' => $tools];
            }
        }

        return $groups;
    }

    /**
     * @return Collection<string, Tool>
     */
    public function popular(): Collection
    {
        return $this->enabled()->filter(fn (Tool $tool) => $tool->isPopular());
    }

    /**
     * @return Collection<string, Tool>
     */
    public function related(Tool $tool, int $limit = 6): Collection
    {
        $explicit = collect($tool->get('related', []))
            ->map(fn ($slug) => $this->find($slug))
            ->filter(fn (?Tool $t) => $t?->isEnabled());

        $sameCategory = $this->enabled()
            ->filter(fn (Tool $t) => $t->slug !== $tool->slug && array_intersect($t->categories(), $tool->categories()));

        return $explicit->merge($sameCategory)->unique('slug')->take($limit);
    }

    public function categoryColor(string $category): string
    {
        return $this->categories()[$category]['color'] ?? '#2563eb';
    }

    /**
     * Can the server process this tool right now (binaries/extensions present)?
     */
    public function serverAvailable(Tool $tool): bool
    {
        $class = $tool->processorClass();

        if (! $class || ! class_exists($class)) {
            return false;
        }

        $requirements = $class::requirements($tool->params());

        if ($requirements === []) {
            return true;
        }

        foreach ($requirements as $set) {
            if (collect((array) $set)->every(fn ($name) => $this->capabilities->has($name))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Usable engines for the tool in order of preference.
     *
     * @return list<string>
     */
    public function enginesFor(Tool $tool): array
    {
        $browserBased = in_array($tool->ui(), ['editor', 'organize'], true) || $tool->browserHandler();
        $available = [];

        foreach ($tool->engines() as $engine) {
            if ($engine === 'browser' && $browserBased) {
                $available[] = 'browser';
            }

            if ($engine === 'server' && $this->serverAvailable($tool)) {
                $available[] = 'server';
            }
        }

        if ($this->settings->enginePreference() === 'server' && in_array('server', $available, true)) {
            $available = ['server', ...array_values(array_diff($available, ['server']))];
        }

        return $available;
    }

    /**
     * Extensions accepted by the tool, after admin restrictions.
     *
     * @return list<string>
     */
    public function extensionsFor(Tool $tool): array
    {
        $allowed = (array) $this->settings->get('allowed_types', []);
        $extensions = [];

        foreach ($tool->accept() as $group) {
            $groupExtensions = FileTypes::GROUPS[$group]['extensions'] ?? [];

            if (! empty($allowed[$group])) {
                $groupExtensions = array_values(array_intersect($groupExtensions, (array) $allowed[$group]));
            }

            array_push($extensions, ...$groupExtensions);
        }

        return array_values(array_unique($extensions));
    }

    public function acceptLabel(Tool $tool): string
    {
        return collect($this->extensionsFor($tool))
            ->reject(fn ($ext) => in_array($ext, ['jpeg', 'htm'], true))
            ->map(fn ($ext) => strtoupper($ext))
            ->implode(', ');
    }

    /**
     * Configuration handed to the JavaScript tool runner.
     */
    public function clientConfig(Tool $tool): array
    {
        $extensions = $this->extensionsFor($tool);
        $optionalWhen = $tool->filesOptionalWhen();

        return [
            'slug' => $tool->slug,
            'name' => $tool->name(),
            'ui' => $tool->ui(),
            'action' => $tool->action(),
            'engines' => $this->enginesFor($tool),
            'browser' => $tool->browserHandler(),
            'params' => $tool->params(),
            'output' => $tool->get('output', 'file'),
            'accept' => [
                'groups' => $tool->accept(),
                'extensions' => $extensions,
                'mimes' => array_values(array_unique(array_filter(array_map(fn ($ext) => FileTypes::BROWSER_MIMES[$ext] ?? null, $extensions)))),
                'label' => $this->acceptLabel($tool),
            ],
            'multiple' => $tool->multiple(),
            'sortable' => $tool->sortable(),
            'minFiles' => $tool->minFiles(),
            'maxFiles' => min($tool->maxFiles(), $this->settings->maxFiles()),
            'maxBytes' => $this->settings->maxUploadBytes(),
            'filesOptional' => $optionalWhen ? ['option' => $optionalWhen[0], 'value' => $optionalWhen[1]] : null,
            'options' => collect($tool->options())->map(fn ($field) => [
                'type' => $field['type'],
                'default' => $field['default'],
                'required' => (bool) ($field['required'] ?? false),
                'label' => $field['label'],
            ])->all(),
            'endpoints' => [
                'process' => route('api.jobs.store', $tool->slug),
                'usage' => route('api.usage', $tool->slug),
            ],
        ];
    }
}
