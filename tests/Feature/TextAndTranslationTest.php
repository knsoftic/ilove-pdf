<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TextAndTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_can_be_exported_as_docx_pdf_and_txt(): void
    {
        foreach (['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=UTF-8'] as $format => $mime) {
            $this->postJson(route('api.export.text'), ['text' => "Hello\n\nWorld", 'format' => $format, 'filename' => 'My File.pdf'])
                ->assertOk()
                ->assertHeader('Content-Type', $mime)
                ->assertDownload("My-File.{$format}");
        }
    }

    public function test_right_to_left_text_exports_to_pdf(): void
    {
        $this->postJson(route('api.export.text'), ['text' => 'یہ ایک ٹیسٹ ہے۔', 'format' => 'pdf', 'lang' => 'ur'])->assertOk();
    }

    public function test_language_detection_without_an_engine(): void
    {
        $cases = [
            'This is a simple document about the results of the quarter and the plans for the year.' => 'en',
            'Dies ist ein Dokument über die Ergebnisse und die Pläne für das nächste Jahr, das wir haben.' => 'de',
            'یہ ایک ٹیسٹ دستاویز ہے جس میں ہم اپنے منصوبے بیان کرتے ہیں۔ ہمیں امید ہے کہ یہ اچھا ہوگا۔' => 'ur',
            'هذا مستند تجريبي يصف الخطط والنتائج للسنة القادمة في الشركة' => 'ar',
            'यह एक परीक्षण दस्तावेज़ है जिसमें हमारी योजनाएँ हैं' => 'hi',
            'Это тестовый документ с нашими планами на следующий год' => 'ru',
            '这是一个测试文件，描述了我们明年的计划' => 'zh',
        ];

        foreach ($cases as $text => $expected) {
            $this->postJson(route('api.translate.detect'), ['text' => $text])
                ->assertOk()
                ->assertJsonPath('language', $expected);
        }
    }

    public function test_translation_is_unavailable_without_a_configured_engine(): void
    {
        config(['pdftoolkit.translation.driver' => 'none']);

        $this->postJson(route('api.translate'), ['text' => 'Hello', 'source' => 'en', 'target' => 'ur'])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'translation_unavailable');
    }

    public function test_libretranslate_engine_translates_line_by_line(): void
    {
        config(['pdftoolkit.translation.driver' => 'libretranslate', 'pdftoolkit.translation.libretranslate.url' => 'http://lt.test']);

        Http::fake([
            'lt.test/languages' => Http::response([['code' => 'en'], ['code' => 'de']]),
            'lt.test/translate' => fn ($request) => Http::response([
                'translatedText' => array_map(fn ($line) => strtoupper($line), $request['q']),
            ]),
        ]);

        $this->postJson(route('api.translate'), ['text' => "Hello\n\nWorld\n123", 'source' => 'en', 'target' => 'de'])
            ->assertOk()
            ->assertJsonPath('translation', "HELLO\n\nWORLD\n123");

        Http::assertSent(fn ($request) => $request->url() === 'http://lt.test/translate' && $request['target'] === 'de' && $request['q'] === ['Hello', 'World']);
    }

    public function test_translation_validates_languages(): void
    {
        $this->postJson(route('api.translate'), ['text' => 'Hello', 'source' => 'en', 'target' => 'xx'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_options');
    }
}
