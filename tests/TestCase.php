<?php

namespace Tests;

use App\Services\Documents\PdfBuilder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->fixtures = storage_path('framework/testing/fixtures');
        File::ensureDirectoryExists($this->fixtures);

        config([
            'pdftoolkit.temp_path' => storage_path('framework/testing/pdf-temp'),
            'pdftoolkit.cleanup_lottery' => [0, 100],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/pdf-temp'));

        // Keep the cached PDF fixtures, drop the per-test upload copies.
        foreach (File::files($this->fixtures) as $file) {
            if (! preg_match('/^[0-9a-f]{32}\.pdf$/', $file->getFilename())) {
                File::delete($file->getPathname());
            }
        }

        parent::tearDown();
    }

    /**
     * A real PDF created with mPDF (cached per HTML content).
     */
    protected function pdfFixture(string $html = '<h1>Hello PDF</h1><p>This is a test document.</p>'): string
    {
        $path = $this->fixtures.'/'.md5($html).'.pdf';

        if (! is_file($path)) {
            app(PdfBuilder::class)->fromHtml($html, $path);
        }

        return $path;
    }

    protected function upload(string $path, ?string $name = null): UploadedFile
    {
        $copy = $this->fixtures.'/upload-'.uniqid().'-'.basename($name ?? $path);
        copy($path, $copy);

        return new UploadedFile($copy, $name ?? basename($path), null, null, true);
    }

    protected function fakeFile(string $name, string $content): UploadedFile
    {
        $path = $this->fixtures.'/'.uniqid().'-'.$name;
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }
}
