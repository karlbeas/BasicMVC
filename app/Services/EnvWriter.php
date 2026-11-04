<?php

declare(strict_types=1);

namespace App\Services;

/** Writes a .env file readable by App\Core\Env (values always double-quoted). */
final class EnvWriter
{
    /** @param array<string, string|int|bool> $values */
    public static function render(array $values, string $header = ''): string
    {
        $out = $header === '' ? '' : '# ' . str_replace("\n", "\n# ", $header) . "\n\n";
        foreach ($values as $key => $value) {
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
                throw new \InvalidArgumentException('Invalid env key: ' . $key);
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $value = (string) $value;
            if (preg_match('/[\r\n\0]/', $value)) {
                throw new \InvalidArgumentException('Env values cannot contain line breaks: ' . $key);
            }
            $out .= $key . '="' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . "\"\n";
        }

        return $out;
    }

    /** @param array<string, string|int|bool> $values */
    public static function write(string $file, array $values, string $header = ''): void
    {
        if (is_file($file)) {
            throw new \RuntimeException('Refusing to overwrite an existing .env');
        }
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, self::render($values, $header), LOCK_EX) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Cannot write ' . $file);
        }
        @chmod($file, 0o600);
    }
}
