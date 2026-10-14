<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/wc-i18n-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/core/lang', 0777, true);
        mkdir($this->tmp . '/mod/lang', 0777, true);
        file_put_contents($this->tmp . '/core/lang/en.php', "<?php return ['a' => 'A', 'b' => 'B', 'shared' => 'core'];");
        file_put_contents($this->tmp . '/core/lang/fr.php', "<?php return ['a' => 'FR-A'];");
        file_put_contents($this->tmp . '/mod/lang/en.php', "<?php return ['shared' => 'module', 'm' => 'M'];");
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->tmp);
    }

    public function testLayeredLookupAndFallback(): void
    {
        $t = new Translator('en');
        $t->addDir($this->tmp . '/core', false);
        $t->addDir($this->tmp . '/mod');          // prepended — module wins
        $this->assertSame('A', $t->t('a'));
        $this->assertSame('module', $t->t('shared')); // module overrides core
        $this->assertSame('M', $t->t('m'));
        $this->assertSame('missing.key', $t->t('missing.key')); // key passthrough
    }

    public function testLocaleFallback(): void
    {
        $t = new Translator('fr', 'en');
        $t->addDir($this->tmp . '/core', false);
        $this->assertSame('FR-A', $t->t('a'));   // fr exists
        $this->assertSame('B', $t->t('b'));      // falls back to en
    }

    public function testPlaceholders(): void
    {
        file_put_contents($this->tmp . '/core/lang/en.php', "<?php return ['hi' => 'Hello %s'];");
        $t = new Translator('en');
        $t->addDir($this->tmp . '/core', false);
        $this->assertSame('Hello Bob', $t->t('hi', 'Bob'));
    }

    public function testLangTag(): void
    {
        $this->assertSame('en', (new Translator('en_US'))->langTag());
        $this->assertSame('fr', (new Translator('fr-FR'))->langTag());
    }

    /** The shipped catalogs: every en key resolves under fr (own or fallback). */
    public function testShippedFrenchCatalogCoversEnglishKeys(): void
    {
        $en = require WC_ROOT . '/lang/en.php';
        $fr = require WC_ROOT . '/lang/fr.php';
        $t = new Translator('fr', 'en');
        $t->addDir(WC_ROOT, false);
        foreach (array_keys($en) as $key) {
            $this->assertNotSame($key, $t->t($key), "unresolved shipped key {$key}");
        }
        // A key translated in fr differs from en; an absent key falls back.
        $this->assertSame($fr['site.powered_by'], $t->t('site.powered_by'));
        $frOnly = array_diff_key($fr, $en);
        $this->assertSame([], $frOnly, 'fr catalog must not carry keys absent from en');
    }
}
