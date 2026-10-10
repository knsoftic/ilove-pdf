<?php

namespace Tests\Unit;

use App\Services\Documents\HtmlSanitizer;
use App\Services\Documents\SpreadsheetBuilder;
use App\Services\Pdf\ImagePdfWriter;
use App\Services\Pdf\LayoutAnalyzer;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    private function item(float $x, float $y, string $text, float $size = 10, bool $bold = false): array
    {
        return ['x' => $x, 'y' => $y, 'size' => $size, 'text' => $text, 'bold' => $bold, 'italic' => false];
    }

    public function test_layout_analyzer_builds_headings_paragraphs_and_tables(): void
    {
        $layout = new LayoutAnalyzer;
        $items = [
            $this->item(50, 800, 'Annual report', 22, true),
            $this->item(50, 770, 'The first line of a paragraph that'),
            $this->item(50, 758, 'continues on the next line.'),
            $this->item(50, 720, 'Region', 10, true), $this->item(150, 720, 'Q2', 10, true), $this->item(220, 720, 'Q3', 10, true),
            $this->item(50, 706, 'North'), $this->item(140, 706, '1,200.50'), $this->item(210, 706, '1,450.00'),
            $this->item(50, 692, 'South'), $this->item(148, 692, '980.00'), $this->item(210, 692, '1,010.25'),
        ];

        $blocks = $layout->blocks($layout->lines($items));

        $this->assertSame('heading', $blocks[0]['type']);
        $this->assertSame('Annual report', $blocks[0]['text']);
        $this->assertSame('The first line of a paragraph that continues on the next line.', $blocks[1]['text']);
        $this->assertSame('table', $blocks[2]['type']);
        $this->assertSame([['Region', 'Q2', 'Q3'], ['North', '1,200.50', '1,450.00'], ['South', '980.00', '1,010.25']], $blocks[2]['rows']);
    }

    public function test_layout_text_columns(): void
    {
        $rows = (new LayoutAnalyzer)->rowsFromLayoutText("Name        Amount    Date\nAlpha           10    2026-01-01\nBeta            20    2026-02-01");

        $this->assertSame(['Alpha', '10', '2026-01-01'], $rows[1]);
    }

    public function test_html_sanitizer_blocks_scripts_and_remote_resources(): void
    {
        $clean = (new HtmlSanitizer)->sanitize('<p onclick="x()">Hi</p><script>alert(1)</script><img src="http://evil.test/x.png"><img src="/etc/passwd"><a href="javascript:alert(1)">x</a><div style="background:url(http://evil.test)">y</div><img src="data:image/png;base64,iVBORw0KGgo=">');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('evil.test', $clean);
        $this->assertStringNotContainsString('/etc/passwd', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('data:image/png;base64', $clean);
    }

    public function test_spreadsheet_numbers_and_formula_injection(): void
    {
        $sheet = new SpreadsheetBuilder;

        $this->assertSame(1234.5, $sheet->toNumber('1,234.50'));
        $this->assertSame(-12.0, $sheet->toNumber('(12)'));
        $this->assertSame(0.45, $sheet->toNumber('45%'));
        $this->assertSame(1234.56, $sheet->toNumber('1.234,56'));
        $this->assertNull($sheet->toNumber('Total'));

        $csv = $this->fixtures.'/out.csv';
        $sheet->csv([['=SUM(A1)', '+1', 'ok']], $csv);
        $this->assertStringContainsString("'=SUM(A1)", file_get_contents($csv));
    }

    public function test_image_pdf_writer_embeds_jpeg_and_png(): void
    {
        $image = imagecreatetruecolor(120, 80);
        imagejpeg($image, $jpg = $this->fixtures.'/w.jpg');
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 0, 0, 80));
        imagepng($image, $png = $this->fixtures.'/w.png');

        $writer = new ImagePdfWriter($out = $this->fixtures.'/images.pdf');
        $writer->addImagePage($jpg, [595.28, 841.89], 20);
        $writer->addImagePage($png, null);
        $writer->close();

        $pdf = file_get_contents($out);
        $this->assertStringStartsWith('%PDF-1.5', $pdf);
        $this->assertStringContainsString('/DCTDecode', $pdf);
        $this->assertStringContainsString('/SMask', $pdf);
        $this->assertSame(2, preg_match_all('#/Type /Page(?!s)#', $pdf));
    }
}
