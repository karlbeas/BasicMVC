<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;

/** Reads a JSON document from an upload field or, failing that, a textarea. */
final class UploadedJson
{
    /** @throws \InvalidArgumentException with a translation key as message */
    public static function fromRequest(Request $request, string $fileField, string $textField, int $maxBytes): string
    {
        $file = $request->file($fileField);
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
                throw new \InvalidArgumentException('upload.failed');
            }
            if ((int) ($file['size'] ?? 0) > $maxBytes) {
                throw new \InvalidArgumentException('upload.too_large');
            }
            $name = is_string($file['name'] ?? null) ? $file['name'] : '';
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
                throw new \InvalidArgumentException('upload.bad_type');
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!in_array($mime, ['application/json', 'text/plain'], true)) {
                throw new \InvalidArgumentException('upload.bad_type');
            }
            $content = file_get_contents($file['tmp_name']);

            return $content === false ? '' : self::stripBom($content);
        }
        $text = $request->input($textField, '');
        $text = is_string($text) ? $text : '';
        if (strlen($text) > $maxBytes) {
            throw new \InvalidArgumentException('upload.too_large');
        }
        if (trim($text) === '') {
            throw new \InvalidArgumentException('upload.empty');
        }

        return self::stripBom($text);
    }

    private static function stripBom(string $s): string
    {
        return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s;
    }
}
