<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class XlsxReader
{
    /**
     * Read the first worksheet as a list of associative rows keyed by the header row.
     *
     * @return list<array<string, string>>
     */
    public function rows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open xlsx file: {$path}");
        }

        $shared = $this->sharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheet === false) {
            return [];
        }

        $xml = new SimpleXMLElement($sheet);
        $matrix = [];

        foreach ($xml->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $column = $this->columnIndex((string) $cell['r']);
                $cells[$column] = $this->cellValue($cell, $shared);
            }

            $matrix[] = $cells;
        }

        return $this->applyHeader($matrix);
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');

        if ($content === false) {
            return [];
        }

        $xml = new SimpleXMLElement($content);
        $strings = [];

        foreach ($xml->si as $item) {
            $text = (string) $item->t;

            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $shared
     */
    private function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            return $shared[(int) $cell->v] ?? '';
        }

        if ($type === 'inlineStr') {
            return (string) $cell->is->t;
        }

        return trim((string) $cell->v);
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d/', '', $reference);
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param  list<array<int, string>>  $matrix
     * @return list<array<string, string>>
     */
    private function applyHeader(array $matrix): array
    {
        $header = array_shift($matrix);

        if ($header === null) {
            return [];
        }

        $header = array_map(fn ($value) => trim($value), $header);
        $rows = [];

        foreach ($matrix as $cells) {
            $row = [];

            foreach ($header as $column => $name) {
                if ($name !== '') {
                    $row[$name] = $cells[$column] ?? '';
                }
            }

            if (count(array_filter($row, fn ($value) => $value !== '')) > 0) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
