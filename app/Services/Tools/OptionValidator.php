<?php

namespace App\Services\Tools;

use App\Exceptions\ToolException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validates and normalises tool options against the schema in
 * config/tools.php. Unknown options are dropped, defaults are applied and
 * values are cast to their proper types.
 */
class OptionValidator
{
    public const POSITIONS = [
        'top-left', 'top-center', 'top-right',
        'middle-left', 'middle-center', 'middle-right',
        'bottom-left', 'bottom-center', 'bottom-right', 'tile',
    ];

    /**
     * @return array<string, mixed>
     */
    public function validate(Tool $tool, array $input): array
    {
        $fields = array_filter($tool->options(), fn ($field) => ! in_array($field['type'], ['file', 'metadata'], true));
        $data = [];

        foreach ($fields as $key => $field) {
            $value = $input[$key] ?? null;

            if ($field['type'] === 'toggle' && $value !== null) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }

            $data[$key] = is_string($value) ? trim($value) : $value;
        }

        $rules = [];

        foreach ($fields as $key => $field) {
            $rules[$key] = $this->rulesFor($key, $field, $fields);
        }

        $validator = Validator::make($data, $rules, [
            'required' => 'Please fill in ":attribute".',
            'required_if' => 'Please fill in ":attribute".',
        ], collect($fields)->mapWithKeys(fn ($f, $k) => [$k => strtolower($f['label'])])->all());

        if ($validator->fails()) {
            throw ToolException::make('invalid_options', $validator->errors()->first());
        }

        $options = [];

        foreach ($fields as $key => $field) {
            $value = $data[$key];

            if ($value === null || $value === '') {
                $value = $field['default'];
            }

            $options[$key] = match ($field['type']) {
                'toggle' => (bool) $value,
                'number', 'range' => $value === null ? null : (str_contains((string) $value, '.') ? (float) $value : (int) $value),
                default => $value,
            };
        }

        return $options;
    }

    private function rulesFor(string $key, array $field, array $fields): array
    {
        $rules = [];

        if (! empty($field['required'])) {
            $condition = $field['show_if'] ?? null;

            if ($condition && count($condition) === 1) {
                $depends = array_key_first($condition);
                $rules[] = 'required_if:'.$depends.','.implode(',', (array) $condition[$depends]);
            } else {
                $rules[] = 'required';
            }
        } elseif (! empty($field['required_if'])) {
            $depends = array_key_first($field['required_if']);
            $rules[] = 'required_if:'.$depends.','.implode(',', (array) $field['required_if'][$depends]);
        } else {
            $rules[] = 'nullable';
        }

        $rules = array_merge($rules, match ($field['type']) {
            'choice', 'select' => [Rule::in(array_map('strval', array_keys($field['choices'] ?? [])))],
            'number', 'range' => ['numeric', 'min:'.($field['min'] ?? -1000000), 'max:'.($field['max'] ?? 1000000)],
            'toggle' => ['boolean'],
            'color' => ['regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => [Rule::in(self::POSITIONS)],
            'pages' => ['string', 'max:500', 'regex:/^([0-9,;\-\s]|end|last)*$/i'],
            'password' => array_filter([
                'string',
                'max:'.($field['max'] ?? 128),
                ! empty($field['required']) ? 'min:'.($field['min'] ?? 1) : null,
                'not_regex:/[\x00-\x1F\x7F]/',
            ]),
            'ocr-language' => ['string', 'regex:/^[a-z_]{3,10}(\+[a-z_]{3,10}){0,3}$/'],
            'textarea' => ['string', 'max:'.($field['max'] ?? 100000)],
            'hidden' => ['string', 'max:100'],
            default => ['string', 'max:'.($field['max'] ?? 1000)],
        });

        return $rules;
    }
}
