<?php

namespace App\Console\Commands;

use App\Services\System\Capabilities;
use App\Services\Tools\ToolRegistry;
use App\Services\Translation\TranslationManager;
use Illuminate\Console\Command;

class PdfDoctor extends Command
{
    protected $signature = 'pdf:doctor';

    protected $description = 'Show which PDF binaries, PHP extensions and tool engines are available';

    public function handle(Capabilities $capabilities, ToolRegistry $registry, TranslationManager $translation): int
    {
        $report = $capabilities->refresh();

        $this->components->twoColumnDetail('<fg=gray>Binary</>', '<fg=gray>Status</>');

        foreach ($report['binaries'] as $name => $binary) {
            $this->components->twoColumnDetail(
                $name,
                $binary['available'] ? "<fg=green>✔ {$binary['version']}</> <fg=gray>{$binary['path']}</>" : '<fg=yellow>not found</>'
            );
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>PHP extension</>', '<fg=gray>Status</>');

        foreach ($report['php'] as $name => $available) {
            $this->components->twoColumnDetail($name, $available ? '<fg=green>✔</>' : '<fg=yellow>missing</>');
        }

        $this->newLine();
        $this->components->twoColumnDetail('Translation engine', $translation->available()
            ? '<fg=green>✔ '.$translation->driver()->name().'</>'
            : '<fg=yellow>none (browser translator fallback)</>');

        if ($report['tesseract_languages']) {
            $this->components->twoColumnDetail('Tesseract languages', implode(', ', $report['tesseract_languages']));
        }

        $this->newLine();
        $rows = [];

        foreach ($registry->all() as $tool) {
            $engines = $registry->enginesFor($tool);
            $rows[] = [
                $tool->slug,
                $engines === [] ? '<fg=red>unavailable</>' : implode(' → ', $engines),
                $tool->isEnabled() ? 'yes' : 'no',
            ];
        }

        $this->table(['Tool', 'Engines (preferred first)', 'Enabled'], $rows);

        return self::SUCCESS;
    }
}
