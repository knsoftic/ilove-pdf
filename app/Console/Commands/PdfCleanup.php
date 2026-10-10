<?php

namespace App\Console\Commands;

use App\Services\Pdf\TempCleaner;
use Illuminate\Console\Command;

class PdfCleanup extends Command
{
    protected $signature = 'pdf:cleanup
                            {--all : Delete every temporary file, including active jobs}
                            {--dry-run : Only show what would be deleted}';

    protected $description = 'Delete expired temporary PDF files (uploads and results)';

    public function handle(TempCleaner $cleaner): int
    {
        $result = $cleaner->run((bool) $this->option('all'), (bool) $this->option('dry-run'));

        $this->components->info(sprintf(
            '%s %d expired job folder(s) (%s), %d still active.',
            $this->option('dry-run') ? 'Would delete' : 'Deleted',
            $result['deleted'],
            format_bytes($result['freed']),
            $result['kept'],
        ));

        return self::SUCCESS;
    }
}
