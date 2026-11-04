<?php

declare(strict_types=1);

namespace App\Services\Bridge;

/** Reads a CSV file: BOM, Windows-1252 fallback, delimiter detection (; , tab). */
final class CsvReader
{
    /** @return array{rows: list<list<string>>, delimiter: string, converted_encoding: bool} */
    public static function read(string $content): array
    {
        $converted = false;
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } elseif (!mb_check_encoding($content, 'UTF-8')) {
            $content = (string) mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            $converted = true;
        }
        $firstLine = strtok($content, "\r\n") ?: '';
        $counts = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);
        if ($counts[$delimiter] === 0) {
            $delimiter = ';';
        }
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new ImportException('server');
        }
        fwrite($stream, $content);
        rewind($stream);
        $rows = [];
        while (($line = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $line = array_map(static fn($v): string => trim((string) $v), $line);
            if (implode('', $line) === '') {
                continue;
            }
            $rows[] = $line;
            if (count($rows) > XlsxReader::MAX_ROWS) {
                throw new ImportException('too_many_rows');
            }
        }
        fclose($stream);

        return ['rows' => $rows, 'delimiter' => $delimiter, 'converted_encoding' => $converted];
    }
}
