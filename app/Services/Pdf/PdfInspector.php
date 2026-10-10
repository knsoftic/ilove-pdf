<?php

namespace App\Services\Pdf;

use App\Exceptions\ToolException;
use App\Services\Pdf\Engines\QpdfEngine;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Basic facts about a PDF (page count, encryption, metadata) using qpdf when
 * available and the pure-PHP smalot/pdfparser otherwise.
 */
class PdfInspector
{
    /** @var array<string, Document> */
    private array $documents = [];

    public function __construct(private readonly QpdfEngine $qpdf) {}

    /**
     * Validates that a PDF can be processed and returns its page count.
     */
    public function assertProcessable(string $path, bool $allowEncrypted = false): int
    {
        if (! $allowEncrypted && $this->needsPassword($path)) {
            throw ToolException::make('password_protected');
        }

        $pages = $this->pageCount($path);

        if ($pages < 1) {
            throw ToolException::make('corrupted_pdf');
        }

        if ($pages > (int) config('pdftoolkit.limits.max_pages', 1000)) {
            throw ToolException::make('too_many_pages', 'This PDF has '.$pages.' pages. The maximum is '.config('pdftoolkit.limits.max_pages').' pages.');
        }

        return $pages;
    }

    public function pageCount(string $path): int
    {
        if ($this->qpdf->available()) {
            try {
                return $this->qpdf->pageCount($path);
            } catch (ToolException $e) {
                if ($e->errorCode === 'password_protected' || $e->errorCode === 'wrong_password') {
                    throw ToolException::make('password_protected');
                }
            }
        }

        if (filesize($path) < 80 * 1024 * 1024) {
            try {
                return count($this->document($path)->getPages());
            } catch (ToolException $e) {
                if ($e->errorCode === 'password_protected') {
                    throw $e;
                }
            } catch (Throwable) {
                // fall through to the raw scan
            }
        }

        return $this->rawPageCount($path);
    }

    /**
     * True when the PDF cannot be opened without a password.
     */
    public function needsPassword(string $path): bool
    {
        if ($this->qpdf->available()) {
            try {
                return $this->qpdf->requiresPassword($path);
            } catch (Throwable) {
                // fall back to the heuristic below
            }
        }

        return $this->looksEncrypted($path);
    }

    /**
     * Heuristic: an /Encrypt entry in a trailer or cross-reference stream
     * dictionary. Trailers are never compressed, so scanning the start and the
     * end of the file is enough.
     */
    public function looksEncrypted(string $path): bool
    {
        $size = filesize($path);
        $handle = fopen($path, 'rb');
        $head = fread($handle, 65536);
        fseek($handle, max(0, $size - 262144));
        $tail = fread($handle, 262144);
        fclose($handle);

        return (bool) preg_match('#/Encrypt\s*(\d+\s+\d+\s+R|<<)#', $head.$tail);
    }

    public function document(string $path): Document
    {
        $key = realpath($path) ?: $path;

        if (isset($this->documents[$key])) {
            return $this->documents[$key];
        }

        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setDataTmFontInfoHasToBeIncluded(true);
        $config->setDecodeMemoryLimit(64 * 1024 * 1024);

        try {
            $document = (new Parser([], $config))->parseFile($path);
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());

            if (str_contains($message, 'secured') || str_contains($message, 'encrypt')) {
                throw ToolException::make('password_protected', null, $e);
            }

            throw ToolException::make('corrupted_pdf', null, $e);
        }

        return $this->documents[$key] = $document;
    }

    /**
     * @return array<string, string>
     */
    public function metadata(string $path): array
    {
        $details = $this->document($path)->getDetails();
        $wanted = ['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer', 'CreationDate', 'ModDate'];

        return collect($wanted)
            ->mapWithKeys(fn ($key) => [$key => is_array($details[$key] ?? null) ? implode(', ', $details[$key]) : (string) ($details[$key] ?? '')])
            ->all();
    }

    private function rawPageCount(string $path): int
    {
        $content = (string) file_get_contents($path);

        if (preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $content) > 0) {
            return preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $content);
        }

        // Pages tree root: the largest /Count value.
        if (preg_match_all('#/Count\s+(\d+)#', $content, $m)) {
            return (int) max($m[1]);
        }

        return 0;
    }
}
