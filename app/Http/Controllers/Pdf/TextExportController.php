<?php

namespace App\Http\Controllers\Pdf;

use App\Exceptions\ToolException;
use App\Helpers\FileName;
use App\Http\Controllers\Controller;
use App\Services\Documents\DocxBuilder;
use App\Services\Documents\PdfBuilder;
use App\Services\Pdf\TempStorage;
use App\Services\Translation\LanguageDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Turns (edited) text from the browser into a DOCX or PDF download.
 * Used by "PDF to Text", "OCR" and the "PDF Translator".
 */
class TextExportController extends Controller
{
    public function __invoke(
        Request $request,
        TempStorage $storage,
        DocxBuilder $docx,
        PdfBuilder $pdf,
        LanguageDetector $detector,
    ): BinaryFileResponse {
        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'max:'.config('pdftoolkit.limits.max_text_chars')],
            'format' => ['required', 'in:docx,pdf,txt'],
            'filename' => ['nullable', 'string', 'max:200'],
            'lang' => ['nullable', 'string', 'regex:/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/'],
        ]);

        if ($validator->fails()) {
            throw ToolException::make('invalid_options', $validator->errors()->first());
        }

        $text = (string) $request->input('text');
        $format = $request->input('format');
        $lang = $request->input('lang') ?: $detector->detect($text)['language'];
        $name = FileName::make($request->input('filename'), '', $format, 'text');

        $id = $storage->create();
        $path = $storage->path($id, 'output/text.'.$format);

        // Remove the temporary folder as soon as the response has been sent.
        app()->terminating(fn () => $storage->delete($id));

        match ($format) {
            'docx' => $docx->fromText($text, $path, $lang, pathinfo($name, PATHINFO_FILENAME)),
            'pdf' => $pdf->fromText($text, $path, ['lang' => $lang, 'title' => pathinfo($name, PATHINFO_FILENAME)]),
            default => file_put_contents($path, "\xEF\xBB\xBF".$text),
        };

        return response()->download($path, $name, [
            'Content-Type' => match ($format) {
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'pdf' => 'application/pdf',
                default => 'text/plain; charset=UTF-8',
            },
            'Content-Disposition' => FileName::disposition($name),
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
