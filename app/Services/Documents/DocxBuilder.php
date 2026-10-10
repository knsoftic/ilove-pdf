<?php

namespace App\Services\Documents;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Style\Language;

/**
 * Builds Word (DOCX) documents with PHPWord (LGPL-3.0).
 */
class DocxBuilder
{
    /**
     * @param  list<list<array{type: string, level?: int, text: string, bold?: bool, italic?: bool}>>  $pages  Blocks per page.
     */
    public function fromBlocks(array $pages, string $output, ?string $title = null, ?array $pageSize = null): string
    {
        $word = $this->make($title);

        foreach ($pages as $i => $blocks) {
            $section = $word->addSection($this->sectionStyle($pageSize));

            foreach ($blocks as $block) {
                if ($block['type'] === 'table') {
                    $this->addTable($section, $block['rows']);

                    continue;
                }

                $text = $this->clean($block['text']);

                if ($text === '') {
                    continue;
                }

                if ($block['type'] === 'heading') {
                    $section->addTitle($text, min(3, max(1, (int) ($block['level'] ?? 2))));
                } else {
                    $section->addText($text, array_filter([
                        'bold' => $block['bold'] ?? false,
                        'italic' => $block['italic'] ?? false,
                    ]), ['spaceAfter' => 160]);
                }
            }

            if ($blocks === []) {
                $section->addText('');
            }
        }

        if ($pages === []) {
            $word->addSection()->addText('');
        }

        return $this->save($word, $output);
    }

    public function fromText(string $text, string $output, ?string $lang = null, ?string $title = null): string
    {
        $word = $this->make($title);
        $rtl = in_array($lang, PdfBuilder::RTL_LANGUAGES, true);

        if ($lang) {
            $word->getSettings()->setThemeFontLang(new Language($lang));
        }

        $section = $word->addSection();
        $paragraphStyle = ['spaceAfter' => 120, 'bidi' => $rtl];
        $fontStyle = $rtl ? ['rtl' => true] : [];

        foreach (explode("\f", str_replace(["\r\n", "\r"], "\n", $text)) as $pageIndex => $page) {
            if ($pageIndex > 0) {
                $section->addPageBreak();
            }

            foreach (preg_split("/\n{2,}/", $page) as $paragraph) {
                if (trim($paragraph) === '') {
                    continue;
                }

                $run = $section->addTextRun($paragraphStyle);

                foreach (explode("\n", $paragraph) as $j => $line) {
                    if ($j > 0) {
                        $run->addTextBreak();
                    }
                    $run->addText($this->clean($line), $fontStyle);
                }
            }
        }

        return $this->save($word, $output);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function addTable($section, array $rows): void
    {
        $table = $section->addTable([
            'borderSize' => 4,
            'borderColor' => 'BFBFBF',
            'cellMargin' => 60,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        foreach ($rows as $r => $row) {
            $table->addRow();

            foreach ($row as $cell) {
                $table->addCell()->addText($this->clean($cell), $r === 0 ? ['bold' => true] : [], ['spaceAfter' => 0]);
            }
        }

        $section->addTextBreak();
    }

    private function make(?string $title): PhpWord
    {
        Settings::setOutputEscapingEnabled(true);

        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(11);
        $word->addTitleStyle(1, ['size' => 20, 'bold' => true], ['spaceAfter' => 200, 'spaceBefore' => 240]);
        $word->addTitleStyle(2, ['size' => 16, 'bold' => true], ['spaceAfter' => 160, 'spaceBefore' => 200]);
        $word->addTitleStyle(3, ['size' => 13, 'bold' => true], ['spaceAfter' => 120, 'spaceBefore' => 160]);

        $properties = $word->getDocInfo();
        $properties->setCreator(setting('site_name', 'PDF Toolkit'));

        if ($title) {
            $properties->setTitle($title);
        }

        return $word;
    }

    private function sectionStyle(?array $pageSize): array
    {
        if (! $pageSize || empty($pageSize['width']) || empty($pageSize['height'])) {
            return [];
        }

        $width = Converter::pointToTwip($pageSize['width']);
        $height = Converter::pointToTwip($pageSize['height']);

        return [
            'pageSizeW' => $width,
            'pageSizeH' => $height,
            'orientation' => $width > $height ? 'landscape' : 'portrait',
        ];
    }

    private function save(PhpWord $word, string $output): string
    {
        IOFactory::createWriter($word, 'Word2007')->save($output);

        return $output;
    }

    /**
     * Removes characters that are not allowed in XML documents.
     */
    private function clean(string $text): string
    {
        return trim(preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '');
    }
}
