<?php

namespace App\Services\Pdf;

use App\Helpers\UnicodeText;

/**
 * Rebuilds document structure from positioned text fragments:
 * lines, paragraphs, headings and tables (rows / columns).
 */
class LayoutAnalyzer
{
    /**
     * Groups fragments into visual lines, top to bottom.
     *
     * @return list<array{y: float, x: float, size: float, bold: bool, italic: bool, rtl: bool, text: string, items: list<array>}>
     */
    public function lines(array $items): array
    {
        usort($items, fn ($a, $b) => [$b['y'], $a['x']] <=> [$a['y'], $b['x']]);

        $lines = [];

        foreach ($items as $item) {
            $last = array_key_last($lines);
            $tolerance = $last === null ? 0 : max(1.5, min($lines[$last]['size'], $item['size']) * 0.45);

            if ($last !== null && abs($lines[$last]['y'] - $item['y']) <= $tolerance) {
                $lines[$last]['items'][] = $item;
                $lines[$last]['size'] = max($lines[$last]['size'], $item['size']);
            } else {
                $lines[] = ['y' => $item['y'], 'size' => $item['size'], 'items' => [$item]];
            }
        }

        foreach ($lines as $i => $line) {
            usort($line['items'], fn ($a, $b) => $a['x'] <=> $b['x']);
            $raw = implode(' ', array_column($line['items'], 'text'));

            $line['rtl'] = UnicodeText::isRtl($raw);
            $line['x'] = $line['items'][0]['x'];
            $line['text'] = $this->join($line['items'], $line['rtl']);
            $line['bold'] = collect($line['items'])->every(fn ($item) => $item['bold']);
            $line['italic'] = collect($line['items'])->every(fn ($item) => $item['italic']);
            $lines[$i] = $line;
        }

        return array_values(array_filter($lines, fn ($line) => trim($line['text']) !== ''));
    }

    /**
     * Joins the fragments of one line (sorted left to right), inserting
     * spaces where there is a visible gap. Right-to-left lines are read
     * from right to left.
     */
    public function join(array $items, bool $rtl = false): string
    {
        $pieces = [];
        $previousEnd = null;

        foreach ($items as $item) {
            $space = false;

            if ($previousEnd !== null) {
                $space = $item['x'] - $previousEnd > $item['size'] * 0.15;
            }

            $pieces[] = [$item['text'], $space];
            $previousEnd = max($previousEnd ?? 0, $item['x'] + $this->estimateWidth($item));
        }

        if ($rtl) {
            // Gap information belongs to the boundary between two pieces.
            $reversed = [];
            $count = count($pieces);

            for ($i = $count - 1; $i >= 0; $i--) {
                $reversed[] = [$pieces[$i][0], $i < $count - 1 && $pieces[$i + 1][1]];
            }

            $pieces = $reversed;
        }

        $text = '';

        foreach ($pieces as [$piece, $space]) {
            if ($space && $text !== '' && ! str_ends_with($text, ' ') && ! str_starts_with($piece, ' ')) {
                $text .= ' ';
            }

            $text .= $piece;
        }

        return trim(preg_replace('/ {2,}/', ' ', $text) ?? $text);
    }

    /**
     * Headings, paragraphs and tables for word-processor output.
     *
     * @return list<array{type: string, level?: int, text?: string, rows?: list<list<string>>, bold?: bool, italic?: bool}>
     */
    public function blocks(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $body = $this->bodySize($lines);
        $spacing = $this->lineSpacing($lines, $body);
        $tables = $this->tableRegions($lines, $spacing);
        $blocks = [];
        $current = null;
        $previous = null;

        foreach ($lines as $index => $line) {
            if (array_key_exists($index, $tables)) {
                if ($tables[$index] !== null) {
                    $this->flush($blocks, $current);
                    $blocks[] = ['type' => 'table', 'rows' => $tables[$index]];
                }

                $previous = $line;

                continue;
            }

            $text = $line['text'];
            $ratio = $line['size'] / max($body, 1);
            $isHeading = mb_strlen($text) < 160 && ($ratio >= 1.18 || ($line['bold'] && $ratio >= 1.05 && mb_strlen($text) < 90));

            if ($isHeading) {
                $this->flush($blocks, $current);
                $level = $ratio >= 1.7 ? 1 : ($ratio >= 1.35 ? 2 : 3);
                $last = array_key_last($blocks);

                // Multi-line heading of the same size.
                if ($previous && $last !== null && $blocks[$last]['type'] === 'heading'
                    && $blocks[$last]['level'] === $level && ($previous['y'] - $line['y']) < $line['size'] * 1.6) {
                    $blocks[$last]['text'] .= ' '.$text;
                } else {
                    $blocks[] = ['type' => 'heading', 'level' => $level, 'text' => $text];
                }

                $previous = $line;

                continue;
            }

            $gap = $previous ? $previous['y'] - $line['y'] : 0;
            $startsNew = $current === null
                || $gap > $spacing * 1.45
                || $gap < 0
                || abs($line['size'] - $previous['size']) > 1.5
                || preg_match('/^(\x{2022}|•|▪|◦|-|\*|\d{1,3}[.)]|[a-z][.)])\s/u', $text);

            if ($startsNew) {
                $this->flush($blocks, $current);
                $current = ['type' => 'paragraph', 'text' => $text, 'bold' => $line['bold'], 'italic' => $line['italic']];
            } else {
                $current['text'] = $this->joinLines($current['text'], $text);
                $current['bold'] = $current['bold'] && $line['bold'];
                $current['italic'] = $current['italic'] && $line['italic'];
            }

            $previous = $line;
        }

        $this->flush($blocks, $current);

        return $blocks;
    }

    /**
     * Table rows for spreadsheet output. Every line becomes a row; cells are
     * assigned to columns derived from the horizontal extent of the cells of
     * all multi-cell lines (so right-aligned numbers and centred headers end
     * up in the same column).
     *
     * @return list<list<string>>
     */
    public function rows(array $lines): array
    {
        // Spreadsheets favour splitting: half an em separates cells.
        $cellLines = array_map(fn ($line) => $this->cells($line, 0.5), $lines);
        $rows = [];
        $region = [];

        $flush = function () use (&$region, &$rows) {
            if (count($region) >= 2) {
                // Columns are computed per table region, so text above or
                // below a table cannot distort its columns.
                array_push($rows, ...$this->alignRows($region));
            } else {
                foreach ($region as $cells) {
                    $rows[] = [implode(' ', array_column($cells, 'text'))];
                }
            }
            $region = [];
        };

        foreach ($cellLines as $cells) {
            if (count($cells) >= 2) {
                $region[] = $cells;
            } else {
                $flush();
                $rows[] = [implode(' ', array_column($cells, 'text'))];
            }
        }

        $flush();

        return $rows;
    }

    /**
     * Table rows from `pdftotext -layout` output, where columns are kept
     * apart by runs of two or more spaces.
     *
     * @return list<list<string>>
     */
    public function rowsFromLayoutText(string $text): array
    {
        $cellLines = [];

        foreach (preg_split('/\n/', $text) as $line) {
            if (trim($line) === '') {
                continue;
            }

            preg_match_all('/\S+(?: \S+)*/u', $line, $matches, PREG_OFFSET_CAPTURE);
            $cells = [];

            foreach ($matches[0] as [$value, $byteOffset]) {
                $start = mb_strlen(substr($line, 0, $byteOffset));
                $cells[] = ['x0' => $start, 'x1' => $start + mb_strlen($value), 'text' => $value];
            }

            $cellLines[] = $cells;
        }

        return $this->alignRows($cellLines);
    }

    /**
     * Number of rows that have at least two non-empty cells: a measure of how
     * well a table was recognised.
     */
    public function tableScore(array $rows): int
    {
        return count(array_filter($rows, fn ($row) => count(array_filter($row, fn ($cell) => $cell !== '')) >= 2));
    }

    /**
     * Splits a line into cells on large horizontal gaps.
     *
     * @return list<array{x0: float, x1: float, text: string}>
     */
    private function cells(array $line, float $gapFactor = 1.0): array
    {
        $groups = [];
        $current = null;
        $previousEnd = null;

        foreach ($line['items'] as $item) {
            $gap = $previousEnd === null ? 0 : $item['x'] - $previousEnd;

            if ($current === null || $gap > $item['size'] * $gapFactor) {
                if ($current) {
                    $groups[] = $current;
                }
                $current = [$item];
            } else {
                $current[] = $item;
            }

            $previousEnd = max($previousEnd ?? 0, $item['x'] + $this->estimateWidth($item));
        }

        if ($current) {
            $groups[] = $current;
        }

        $rtl = $line['rtl'] ?? false;
        $cells = [];

        foreach ($groups as $group) {
            $last = end($group);
            $cells[] = [
                'x0' => $group[0]['x'],
                // Slightly under-estimate the width so neighbouring columns don't merge.
                'x1' => $last['x'] + $this->estimateWidth($last) * 0.85,
                'text' => $this->join($group, $rtl),
            ];
        }

        // Right-to-left tables are read from the right.
        return $rtl ? array_reverse($cells) : $cells;
    }

    /**
     * @param  list<list<array{x0: float, x1: float, text: string}>>  $cellLines
     * @return list<list<string>>
     */
    private function alignRows(array $cellLines): array
    {
        $columns = $this->spanColumns($cellLines);

        if (count($columns) < 2) {
            return array_map(fn ($cells) => [implode(' ', array_column($cells, 'text'))], $cellLines);
        }

        $rows = [];

        foreach ($cellLines as $cells) {
            $row = array_fill(0, count($columns), '');

            if (count($cells) === 1 && ($cells[0]['x1'] - $cells[0]['x0']) > ($columns[count($columns) - 1][1] - $columns[0][0]) * 0.6) {
                // Full-width text (titles, paragraphs) goes to the first column.
                $row[0] = $cells[0]['text'];
            } else {
                foreach ($cells as $cell) {
                    $column = $this->columnFor($columns, $cell);
                    $row[$column] = trim($row[$column].' '.$cell['text']);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Merges the horizontal spans of all cells of multi-cell lines into
     * column intervals.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function spanColumns(array $cellLines): array
    {
        $spans = [];

        foreach ($cellLines as $cells) {
            if (count($cells) >= 2) {
                foreach ($cells as $cell) {
                    $spans[] = [$cell['x0'], max($cell['x0'] + 0.1, $cell['x1'])];
                }
            }
        }

        usort($spans, fn ($a, $b) => $a[0] <=> $b[0]);
        $columns = [];

        foreach ($spans as [$x0, $x1]) {
            $last = array_key_last($columns);

            if ($last !== null && $x0 <= $columns[$last][1]) {
                $columns[$last][1] = max($columns[$last][1], $x1);
            } else {
                $columns[] = [$x0, $x1];
            }
        }

        return $columns;
    }

    private function columnFor(array $columns, array $cell): int
    {
        $best = 0;
        $bestOverlap = -INF;
        $center = ($cell['x0'] + $cell['x1']) / 2;

        foreach ($columns as $i => [$x0, $x1]) {
            $overlap = min($x1, $cell['x1']) - max($x0, $cell['x0']);

            // No overlap: prefer the nearest column centre.
            if ($overlap <= 0) {
                $overlap = -abs((($x0 + $x1) / 2) - $center);
            }

            if ($overlap > $bestOverlap) {
                $bestOverlap = $overlap;
                $best = $i;
            }
        }

        return $best;
    }

    /**
     * Finds runs of consecutive multi-cell lines and converts them to tables.
     *
     * @return array<int, list<list<string>>|null> line index => rows for the first
     *                                             line of a table, null for the others
     */
    private function tableRegions(array $lines, float $spacing): array
    {
        $regions = [];
        $run = [];

        $close = function () use (&$run, &$regions, $lines) {
            if (count($run) >= 2) {
                $rows = $this->alignRows(array_map(fn ($i) => $this->cells($lines[$i], 1.2), $run));

                if ($this->tableScore($rows) >= 2) {
                    foreach ($run as $k => $index) {
                        $regions[$index] = $k === 0 ? $rows : null;
                    }
                }
            }

            $run = [];
        };

        foreach ($lines as $index => $line) {
            $isRow = count($this->cells($line, 1.2)) >= 2;
            $previous = $run === [] ? null : $lines[end($run)];
            $near = $previous === null || ($previous['y'] - $line['y']) < $spacing * 2.6;

            if ($isRow && $near) {
                $run[] = $index;
            } else {
                $close();

                if ($isRow) {
                    $run[] = $index;
                }
            }
        }

        $close();

        return $regions;
    }

    private function estimateWidth(array $item): float
    {
        return mb_strlen($item['text']) * $item['size'] * ($item['bold'] ? 0.55 : 0.5);
    }

    private function bodySize(array $lines): float
    {
        $weights = [];

        foreach ($lines as $line) {
            $key = (string) round($line['size']);
            $weights[$key] = ($weights[$key] ?? 0) + mb_strlen($line['text']);
        }

        arsort($weights);

        return (float) array_key_first($weights);
    }

    private function lineSpacing(array $lines, float $body): float
    {
        $gaps = [];

        for ($i = 1, $n = count($lines); $i < $n; $i++) {
            $gap = $lines[$i - 1]['y'] - $lines[$i]['y'];

            if ($gap > 0 && abs($lines[$i]['size'] - $body) < 1.5) {
                $gaps[] = $gap;
            }
        }

        if ($gaps === []) {
            return $body * 1.2;
        }

        sort($gaps);

        return $gaps[intdiv(count($gaps), 2)];
    }

    private function joinLines(string $a, string $b): string
    {
        // Re-join words hyphenated at the end of a line.
        if (preg_match('/\p{L}-$/u', $a) && preg_match('/^\p{Ll}/u', $b)) {
            return mb_substr($a, 0, -1).$b;
        }

        return $a.' '.$b;
    }

    private function flush(array &$blocks, ?array &$current): void
    {
        if ($current !== null && trim($current['text']) !== '') {
            $blocks[] = $current;
        }

        $current = null;
    }
}
