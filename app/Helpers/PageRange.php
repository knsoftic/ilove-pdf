<?php

namespace App\Helpers;

use App\Exceptions\ToolException;

/**
 * Parses page selections such as "1-5, 8, 10-end".
 *
 * Supported tokens: "7", "2-5", "5-" or "5-end" (to the last page),
 * "-3" (first three pages) and "last". Pages are 1-based.
 */
final class PageRange
{
    /**
     * @return list<array{0:int,1:int}> Inclusive [start, end] pairs in the given order.
     */
    public static function ranges(?string $input, int $pageCount): array
    {
        $input = trim((string) $input);

        if ($input === '') {
            return [[1, $pageCount]];
        }

        $ranges = [];

        foreach (preg_split('/[,;]+/', $input) as $token) {
            $token = strtolower(preg_replace('/\s+/', '', $token));

            if ($token === '') {
                continue;
            }

            $token = str_replace(['end', 'last', '–', '—'], [(string) $pageCount, (string) $pageCount, '-', '-'], $token);

            if (preg_match('/^(\d+)$/', $token, $m)) {
                $start = $end = (int) $m[1];
            } elseif (preg_match('/^(\d*)-(\d*)$/', $token, $m) && ($m[1] !== '' || $m[2] !== '')) {
                $start = $m[1] === '' ? 1 : (int) $m[1];
                $end = $m[2] === '' ? $pageCount : (int) $m[2];
            } else {
                throw ToolException::make('invalid_page_range', "\"{$token}\" is not a valid page or page range.");
            }

            if ($start < 1 || $end < 1 || $start > $pageCount || $end > $pageCount) {
                throw ToolException::make('invalid_page_range', "Page selection \"{$token}\" is outside this document (1–{$pageCount}).");
            }

            $ranges[] = $start <= $end ? [$start, $end] : [$end, $start];
        }

        if ($ranges === []) {
            throw ToolException::make('invalid_page_range');
        }

        return $ranges;
    }

    /**
     * @return list<int> Unique page numbers in the order they were given.
     */
    public static function pages(?string $input, int $pageCount): array
    {
        $pages = [];

        foreach (self::ranges($input, $pageCount) as [$start, $end]) {
            foreach (range($start, $end) as $page) {
                $pages[$page] = $page;
            }
        }

        return array_values($pages);
    }

    /**
     * @param  list<int>  $pages
     */
    public static function toQpdf(array $pages): string
    {
        return implode(',', $pages);
    }
}
