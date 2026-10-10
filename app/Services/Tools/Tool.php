<?php

namespace App\Services\Tools;

use Illuminate\Support\Str;

/**
 * A single tool from config/tools.php, with admin overrides applied.
 */
final class Tool
{
    private ?array $options = null;

    public function __construct(
        public readonly string $slug,
        private readonly array $definition,
        private readonly array $override = [],
        private readonly bool $enabled = true,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->override[$key] ?? $this->definition[$key] ?? $default;
    }

    public function name(): string
    {
        return $this->get('name');
    }

    public function description(): string
    {
        return $this->get('description', '');
    }

    public function heading(): string
    {
        return $this->get('heading', $this->name());
    }

    public function seoTitle(): string
    {
        return $this->get('title') ?: "{$this->name()} Online – Free, Fast & Secure";
    }

    public function metaDescription(): string
    {
        return $this->get('meta') ?: rtrim($this->description(), '.').'. Free online tool – no registration, no watermark, files deleted automatically.';
    }

    public function keywords(): string
    {
        return $this->get('keywords', Str::lower($this->name()).', free pdf tools, online pdf');
    }

    public function focusKeyword(): ?string
    {
        return $this->override['focus'] ?? null;
    }

    /**
     * Long-form SEO text (Markdown) written in the admin panel.
     */
    public function article(): ?string
    {
        return $this->override['article'] ?? null;
    }

    /**
     * The article as safe HTML (raw HTML removed, H1 demoted – the page already has one).
     */
    public function articleHtml(): ?string
    {
        if (! $this->article()) {
            return null;
        }

        $html = Str::markdown($this->article(), ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return preg_replace('#<(/?)h1(\s|>)#', '<$1h2$2', $html);
    }

    public function noindex(): bool
    {
        return (bool) ($this->override['noindex'] ?? false);
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    public function customFaq(): array
    {
        return array_values(array_filter(
            (array) ($this->override['custom_faq'] ?? []),
            fn ($faq) => is_array($faq) && trim($faq[0] ?? '') !== '' && trim($faq[1] ?? '') !== '',
        ));
    }

    public function icon(): string
    {
        return $this->get('icon', 'file');
    }

    public function category(): string
    {
        return $this->get('category');
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return array_values(array_unique([$this->category(), ...$this->get('also_in', [])]));
    }

    public function ui(): string
    {
        return $this->get('ui', 'generic');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return list<string>
     */
    public function accept(): array
    {
        return (array) $this->get('accept', ['pdf']);
    }

    public function acceptsOnlyPdf(): bool
    {
        return $this->accept() === ['pdf'];
    }

    public function multiple(): bool
    {
        return (bool) $this->get('multiple', false);
    }

    public function minFiles(): int
    {
        return (int) $this->get('min_files', 1);
    }

    public function maxFiles(): int
    {
        return $this->multiple() ? (int) $this->get('max_files', 20) : 1;
    }

    public function sortable(): bool
    {
        return (bool) $this->get('sortable', false);
    }

    /**
     * Files may be omitted when an option has a given value, e.g. "source:paste".
     *
     * @return array{0:string,1:string}|null
     */
    public function filesOptionalWhen(): ?array
    {
        $rule = $this->get('files_optional');

        return $rule ? explode(':', $rule, 2) : null;
    }

    /**
     * @return list<string>
     */
    public function engines(): array
    {
        return (array) $this->get('engines', ['server']);
    }

    public function browserHandler(): ?string
    {
        return $this->get('browser');
    }

    public function processorClass(): ?string
    {
        return $this->get('processor');
    }

    public function params(): array
    {
        return (array) $this->get('params', []);
    }

    public function preset(): ?string
    {
        return $this->params()['preset'] ?? null;
    }

    public function action(): string
    {
        return $this->get('action', 'Process');
    }

    public function notice(): ?string
    {
        return $this->get('notice');
    }

    public function isPopular(): bool
    {
        return (bool) $this->get('popular', false);
    }

    public function inNav(): bool
    {
        return (bool) $this->get('nav', false);
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return (array) $this->get('aliases', []);
    }

    public function url(): string
    {
        return route('tools.show', $this->slug);
    }

    /**
     * Normalised option field definitions.
     *
     * @return array<string, array>
     */
    public function options(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $options = [];

        foreach ((array) $this->get('options', []) as $key => $field) {
            $field['key'] = $key;
            $field['type'] ??= 'text';
            $field['label'] ??= Str::headline($key);
            $field['default'] ??= match ($field['type']) {
                'toggle' => false,
                'choice', 'select' => array_key_first($field['choices'] ?? []),
                default => null,
            };

            if (isset($field['choices'])) {
                $field['choices'] = collect($field['choices'])->mapWithKeys(fn ($choice, $value) => [
                    (string) $value => is_array($choice)
                        ? ['label' => $choice[0], 'hint' => $choice[1] ?? null]
                        : ['label' => $choice, 'hint' => null],
                ])->all();
            }

            $options[$key] = $field;
        }

        return $this->options = $options;
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    public function faq(): array
    {
        $custom = $this->customFaq();

        if ($custom !== [] && ! empty($this->override['faq_replace'])) {
            return $custom;
        }

        $specific = [...$custom, ...(array) ($this->definition['faq'] ?? [])];
        $where = in_array('server', $this->engines(), true) && ! in_array('browser', $this->engines(), true)
            ? 'Files are uploaded over an encrypted connection, processed on our server and deleted automatically right after processing.'
            : 'Whenever possible your file is processed directly in your browser and never leaves your device. When server processing is needed, files are deleted automatically right after processing.';

        return [
            ...$specific,
            ["Is {$this->name()} free?", "Yes. {$this->name()} is completely free. There is no registration, no subscription and no watermark on your files."],
            ['Are my files safe?', $where],
            ['Does it work on my phone?', 'Yes. The tool works in any modern browser on Windows, macOS, Linux, Android and iPhone – nothing to install.'],
        ];
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    public function steps(): array
    {
        if ($steps = $this->get('steps')) {
            return $steps;
        }

        $what = $this->multiple() ? 'your files' : 'your file';

        return match ($this->ui()) {
            'editor' => [
                ['Upload your PDF', 'Drop your PDF into the upload area or choose it from your device.'],
                ['Edit in your browser', 'Use the toolbar to add text, images, shapes, highlights or your signature.'],
                ['Download', 'Click "Download" to save the edited PDF.'],
            ],
            'organize' => [
                ['Upload your PDF', 'Drop your PDF into the upload area to see all pages as thumbnails.'],
                ['Arrange the pages', 'Drag pages to reorder them and rotate, duplicate or delete pages.'],
                ['Download', 'Download the new PDF or only the selected pages.'],
            ],
            default => [
                ['Upload '.$what, 'Drag and drop '.$what.' into the upload area or click "Choose file".'],
                ['Choose your options', 'Adjust the settings and click "'.$this->action().'".'],
                ['Download the result', 'Your file is ready in seconds. Download it – files are deleted automatically.'],
            ],
        };
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name(),
            'description' => $this->description(),
            'icon' => $this->icon(),
            'category' => $this->category(),
            'url' => $this->url(),
        ];
    }
}
