<?php

namespace App\Http\Controllers\Pdf;

use App\Exceptions\ToolException;
use App\Http\Controllers\Controller;
use App\Services\Stats\UsageRecorder;
use App\Services\Translation\TranslationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Translation API used by the PDF Translator. Text is sent in chunks by the
 * browser so long documents show real progress and no request runs long.
 */
class TranslationController extends Controller
{
    public function __construct(private readonly TranslationManager $translation) {}

    public function languages(): JsonResponse
    {
        return response()->json([
            'languages' => $this->translation->languages(),
            'server' => [
                'available' => $this->translation->available(),
                'engine' => $this->translation->driver()->name(),
            ],
        ]);
    }

    public function detect(Request $request): JsonResponse
    {
        $data = $this->validated($request, ['text' => ['required', 'string', 'max:20000']]);

        return response()->json($this->translation->detect($data['text']));
    }

    public function translate(Request $request, UsageRecorder $usage): JsonResponse
    {
        $codes = implode(',', array_keys($this->translation->languages()));

        $data = $this->validated($request, [
            'text' => ['required', 'string', 'max:6000'],
            'source' => ['required', 'string', 'in:auto,'.$codes],
            'target' => ['required', 'string', 'in:'.$codes],
            'first' => ['nullable', 'boolean'],
        ]);

        $translation = $this->translation->translate($data['text'], $data['source'], $data['target']);

        if ($request->boolean('first')) {
            $usage->record('pdf-translator', 'server');
        }

        return response()->json(['translation' => $translation]);
    }

    private function validated(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ToolException::make('invalid_options', $validator->errors()->first());
        }

        return $validator->validated();
    }
}
