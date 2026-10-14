<?php
declare(strict_types=1);

namespace Core\I18n;

/**
 * Localization: flat key => string catalogs loaded from PHP array files.
 * Layered lookup — enabled modules first, then the active theme, then
 * core — so packages and themes can add or refine strings without
 * touching core files. Missing keys fall back to the fallback locale,
 * then to the key itself (never a fatal).
 *
 * Catalog files live at <dir>/lang/<locale>.php and `return ['key' => '…']`.
 */
final class Translator
{
    private static ?self $shared = null;

    /** @var list<string> catalog dirs, most specific first */
    private array $dirs = [];
    /** @var array<string, array<string, string>> locale => merged catalog */
    private array $catalogs = [];

    public function __construct(
        private string $locale = 'en',
        private string $fallbackLocale = 'en',
    ) {}

    public static function setInstance(self $t): void
    {
        self::$shared = $t;
    }

    public static function instance(): self
    {
        return self::$shared ??= new self();
    }

    /** Register a catalog dir; modules prepend so they win over core. */
    public function addDir(string $dir, bool $prepend = true): void
    {
        if (!is_dir($dir . '/lang')) {
            return;
        }
        if ($prepend) {
            array_unshift($this->dirs, $dir);
        } else {
            $this->dirs[] = $dir;
        }
        $this->catalogs = []; // reset lazy cache
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /** BCP-47-ish primary subtag for <html lang> (en_US → en). */
    public function langTag(): string
    {
        return (string) preg_replace('/[_-].*/', '', $this->locale);
    }

    /** Translate a key; %s-style placeholders are substituted positionally. */
    public function t(string $key, string|int|float ...$args): string
    {
        $catalog = $this->catalog($this->locale);
        $str = $catalog[$key]
            ?? $this->catalog($this->fallbackLocale)[$key]
            ?? $key;
        return $args === [] ? $str : vsprintf($str, $args);
    }

    /** @return array<string, string> */
    private function catalog(string $locale): array
    {
        if (isset($this->catalogs[$locale])) {
            return $this->catalogs[$locale];
        }
        $merged = [];
        // Later dirs (more specific) win.
        foreach (array_reverse($this->dirs) as $dir) {
            $file = $dir . '/lang/' . $locale . '.php';
            if (is_file($file)) {
                $data = require $file;
                if (is_array($data)) {
                    $merged = $data + $merged;
                }
            }
        }
        return $this->catalogs[$locale] = $merged;
    }
}
