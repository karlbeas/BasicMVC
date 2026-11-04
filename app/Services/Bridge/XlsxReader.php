<?php

declare(strict_types=1);

namespace App\Services\Bridge;

/**
 * Minimal streaming .xlsx reader (ZipArchive + XMLReader): only needs ext-zip and
 * ext-xmlreader, available on virtually every shared host — unlike PhpSpreadsheet,
 * whose platform requirements (gd, …) could stop the whole application from booting.
 * Reads cell values as text (shared strings, inline strings, numbers, booleans).
 */
final class XlsxReader
{
    public const MAX_UNCOMPRESSED_BYTES = 50 * 1024 * 1024;
    public const MAX_ROWS = 5000;
    public const MAX_COLUMNS = 200;

    private \ZipArchive $zip;

    public function __construct(string $path)
    {
        if (!class_exists(\ZipArchive::class) || !class_exists(\XMLReader::class)) {
            throw new ImportException('server', 'ext-zip and ext-xmlreader are required');
        }
        $this->zip = new \ZipArchive();
        if ($this->zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new ImportException('unreadable', 'Not a valid .xlsx (zip) file');
        }
        $total = 0;
        for ($i = 0; $i < $this->zip->numFiles; ++$i) {
            $stat = $this->zip->statIndex($i);
            $total += (int) ($stat['size'] ?? 0);
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                throw new ImportException('too_large', 'Uncompressed content too large');
            }
        }
        if ($this->zip->locateName('xl/workbook.xml') === false) {
            throw new ImportException('unreadable', 'Not an Excel workbook');
        }
    }

    public function __destruct()
    {
        @$this->zip->close();
    }

    /** @return array<string, string> sheet name => path inside the zip, in workbook order */
    public function sheets(): array
    {
        $rels = [];
        $relsXml = $this->read('xl/_rels/workbook.xml.rels');
        $r = $this->xml($relsXml);
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'Relationship') {
                $target = (string) $r->getAttribute('Target');
                $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
                $rels[(string) $r->getAttribute('Id')] = $target;
            }
        }
        $sheets = [];
        $w = $this->xml($this->read('xl/workbook.xml'));
        while ($w->read()) {
            if ($w->nodeType === \XMLReader::ELEMENT && $w->localName === 'sheet') {
                $id = (string) $w->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                if (isset($rels[$id])) {
                    $sheets[(string) $w->getAttribute('name')] = $rels[$id];
                }
            }
        }

        return $sheets;
    }

    /**
     * Rows of a sheet as lists of strings (empty trailing cells trimmed, empty rows skipped).
     *
     * @return list<list<string>>
     */
    public function rows(string $sheetName): array
    {
        $sheets = $this->sheets();
        $path = $sheets[$sheetName] ?? null;
        if ($path === null) {
            foreach ($sheets as $name => $p) {
                if (strcasecmp($name, $sheetName) === 0) {
                    $path = $p;
                }
            }
        }
        if ($path === null) {
            return [];
        }
        $strings = $this->sharedStrings();
        $x = $this->xml($this->read($path));
        $rows = [];
        $row = null;
        $col = 0;
        $type = '';
        $value = null;
        while ($x->read()) {
            if ($x->nodeType === \XMLReader::ELEMENT) {
                switch ($x->localName) {
                    case 'row':
                        $row = [];
                        $col = 0;
                        if ($x->isEmptyElement) {
                            $row = null;
                        }
                        break;
                    case 'c':
                        $ref = (string) $x->getAttribute('r');
                        $col = $ref !== '' ? self::columnIndex($ref) : $col + 1;
                        $type = (string) $x->getAttribute('t');
                        $value = null;
                        if ($x->isEmptyElement) {
                            $col = $ref !== '' ? $col : $col;
                        }
                        break;
                    case 'v':
                        $value = $x->readString();
                        break;
                    case 'is':
                        $value = $this->innerText($x);
                        break;
                }
            } elseif ($x->nodeType === \XMLReader::END_ELEMENT) {
                if ($x->localName === 'c' && $row !== null && $value !== null && $col < self::MAX_COLUMNS) {
                    $row[$col] = match ($type) {
                        's' => $strings[(int) $value] ?? '',
                        'b' => $value === '1' ? 'TRUE' : 'FALSE',
                        default => self::number($value),
                    };
                } elseif ($x->localName === 'row' && $row !== null) {
                    if ($row !== [] && implode('', $row) !== '') {
                        $max = max(array_keys($row));
                        $line = [];
                        for ($i = 0; $i <= $max; ++$i) {
                            $line[] = trim((string) ($row[$i] ?? ''));
                        }
                        $rows[] = $line;
                        if (count($rows) > self::MAX_ROWS) {
                            throw new ImportException('too_many_rows', 'More than ' . self::MAX_ROWS . ' rows');
                        }
                    }
                    $row = null;
                }
            }
        }

        return $rows;
    }

    /** "C12" → 2 (zero-based). */
    public static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref)) ?? '';
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }

    /** @return list<string> */
    private function sharedStrings(): array
    {
        if ($this->zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }
        $x = $this->xml($this->read('xl/sharedStrings.xml'));
        $strings = [];
        while ($x->read()) {
            if ($x->nodeType === \XMLReader::ELEMENT && $x->localName === 'si') {
                $strings[] = $this->innerText($x);
            }
        }

        return $strings;
    }

    /** Concatenates <t> texts under the current element (rich text runs), skipping phonetic hints. */
    private function innerText(\XMLReader $x): string
    {
        $depth = $x->depth;
        $name = $x->localName;
        $text = '';
        if ($x->isEmptyElement) {
            return '';
        }
        while ($x->read()) {
            if ($x->nodeType === \XMLReader::END_ELEMENT && $x->depth === $depth && $x->localName === $name) {
                break;
            }
            if ($x->nodeType === \XMLReader::ELEMENT && $x->localName === 'rPh') {
                $x->next();
                continue;
            }
            if ($x->nodeType === \XMLReader::ELEMENT && $x->localName === 't') {
                $text .= $x->readString();
            }
        }

        return $text;
    }

    private static function number(string $v): string
    {
        // Excel stores 1 as "1" but sometimes 0.1+0.2 noise: keep integers clean.
        if (is_numeric($v) && str_contains($v, '.') && abs((float) $v - round((float) $v)) < 1e-9) {
            return (string) (int) round((float) $v);
        }

        return $v;
    }

    private function read(string $name): string
    {
        $content = $this->zip->getFromName($name);
        if ($content === false) {
            throw new ImportException('unreadable', 'Missing part ' . $name);
        }

        return $content;
    }

    private function xml(string $content): \XMLReader
    {
        $r = new \XMLReader();
        // LIBXML_NONET: never fetch external resources; no entity substitution (XXE safe).
        if (!$r->XML($content, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new ImportException('unreadable', 'Invalid XML');
        }

        return $r;
    }
}
