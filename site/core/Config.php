<?php
declare(strict_types=1);

namespace Core;

/**
 * Loads the generated config.php array and provides dot-notation access.
 * Absent config = not installed; the installer runs without it.
 */
final class Config
{
    private array $data = [];
    private bool $loaded = false;

    public function __construct(string $path)
    {
        if (is_file($path)) {
            $values = require $path;
            if (is_array($values)) {
                $this->data = $values;
                $this->loaded = true;
            }
        }
    }

    public function loaded(): bool
    {
        return $this->loaded;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $node = $this->data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }
        return $node;
    }

    public function all(): array
    {
        return $this->data;
    }
}
