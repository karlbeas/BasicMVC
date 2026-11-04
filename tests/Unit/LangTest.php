<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Lang;
use PHPUnit\Framework\TestCase;

final class LangTest extends TestCase
{
    private const DIR = __DIR__ . '/../../lang';

    public function testFrenchAndEnglishHaveTheSameKeys(): void
    {
        $lang = new Lang(self::DIR, 'fr');
        $fr = $lang->load('fr');
        $en = $lang->load('en');

        self::assertSame([], array_keys(array_diff_key($fr, $en)), 'Keys missing in en.php');
        self::assertSame([], array_keys(array_diff_key($en, $fr)), 'Keys missing in fr.php');
    }

    public function testEveryKeyUsedInCodeExists(): void
    {
        $fr = (new Lang(self::DIR, 'fr'))->load('fr');
        $missing = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../app'));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/\\bt\\('([a-z0-9_]+\\.[a-z0-9_.]+)'/", (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $key) {
                if (!str_ends_with($key, '.') && !isset($fr[$key])) {
                    $missing[] = $key . ' (' . $file->getFilename() . ')';
                }
            }
        }
        self::assertSame([], $missing);
    }

    public function testPlaceholdersAndFallback(): void
    {
        $lang = new Lang(self::DIR, 'xx', 'en');

        self::assertSame('en', $lang->locale());
        self::assertSame('Hello Awa', $lang->get('dashboard.hello', ['name' => 'Awa']));
        self::assertSame('unknown.key', $lang->get('unknown.key'));
    }
}
