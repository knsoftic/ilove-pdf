<?php

namespace Tests\Unit;

use App\Exceptions\ToolException;
use App\Helpers\FileName;
use App\Helpers\PageRange;
use App\Helpers\UnicodeText;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function test_page_ranges(): void
    {
        $this->assertSame([[1, 10]], PageRange::ranges('', 10));
        $this->assertSame([[1, 5], [8, 10], [15, 15]], PageRange::ranges('1-5, 8-10, 15', 20));
        $this->assertSame([[5, 20], [1, 3]], PageRange::ranges('5-end; -3', 20));
        $this->assertSame([[2, 4]], PageRange::ranges('4-2', 9));
        $this->assertSame([3, 1, 2], PageRange::pages('3, 1-3', 5));
    }

    public function test_invalid_page_ranges(): void
    {
        foreach (['0', '12', 'abc', '3-30'] as $input) {
            try {
                PageRange::ranges($input, 10);
                $this->fail("{$input} should be rejected");
            } catch (ToolException $e) {
                $this->assertSame('invalid_page_range', $e->errorCode);
            }
        }
    }

    public function test_file_names_are_sanitised(): void
    {
        $this->assertSame('My-Report-2026-compressed.pdf', FileName::make('My Report 2026.pdf', 'compressed', 'pdf'));
        $this->assertSame('passwd.txt', FileName::make('../../etc/passwd', '', 'txt'));
        $this->assertSame('Resume.docx', FileName::make('Résumé.pdf', '', 'docx'));
        $this->assertSame('php.pdf', FileName::make('<?php ?>.pdf', '', 'pdf'));
        $this->assertSame('document.pdf', FileName::make('<>.pdf', '', 'pdf'));
        $this->assertStringContainsString("filename*=UTF-8''", FileName::disposition('Tést.pdf'));
    }

    public function test_arabic_presentation_forms_are_normalised_and_reordered(): void
    {
        // Visual order with presentation forms, as stored in many PDFs.
        $visual = 'ﺭﺎﺒﺘﺧﺍ ﺍﺬﻫ';

        $this->assertTrue(UnicodeText::isRtl($visual));
        $this->assertSame('هذا اختبار', UnicodeText::visualToLogical(UnicodeText::normalize($visual)));
        $this->assertSame('fi', UnicodeText::normalize("\u{FB01}"));
    }

    public function test_numbers_keep_their_direction_in_rtl_text(): void
    {
        $this->assertSame('رقم 2026', UnicodeText::visualToLogical('2026 مقر'));
    }
}
