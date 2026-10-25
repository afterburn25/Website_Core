<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Semantic versioning + a small constraint evaluator covering the operators
 * the package spec supports: exact, >=, >, <=, <, caret ^x.y.z, tilde ~x.y.z,
 * x.y.* wildcard, and comma-separated AND ranges. Deliberately no Composer
 * dependency at runtime; package builds may use composer/semver in tooling.
 */
final class Semver
{
    public static function valid(string $version): bool
    {
        return (bool) preg_match(
            '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/',
            $version
        );
    }

    public static function compare(string $a, string $b): int
    {
        $pa = self::parse($a);
        $pb = self::parse($b);
        if ($pa === null || $pb === null) {
            throw new \InvalidArgumentException("Invalid semver: {$a} or {$b}");
        }
        for ($i = 0; $i < 3; $i++) {
            if ($pa['v'][$i] !== $pb['v'][$i]) {
                return $pa['v'][$i] <=> $pb['v'][$i];
            }
        }
        // pre-release < release; otherwise lexical
        return self::comparePre($pa['pre'], $pb['pre']);
    }

    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }
        if (!self::constraintValid($constraint)) return false;
        foreach (explode(',', $constraint) as $part) {
            if (!self::satisfiesOne($version, trim($part))) {
                return false;
            }
        }
        return true;
    }

    /** Validate exactly the constraint grammar this runtime evaluates. */
    public static function constraintValid(string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') return true;
        foreach (explode(',', $constraint) as $part) {
            $part = trim($part);
            if ($part === '' || (!preg_match('/^(?:\^|~|>=|<=|>|<|=)?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/', $part)
                && !preg_match('/^\d+(?:\.\d+)?\.\*$/', $part))) {
                return false;
            }
        }
        return true;
    }

    private static function satisfiesOne(string $version, string $c): bool
    {
        if (str_starts_with($c, '^')) {
            $base = substr($c, 1);
            $p = self::parse($base);
            if ($p === null) return false;
            $upper = $p['v'][0] > 0
                ? ($p['v'][0] + 1) . '.0.0'
                : (($p['v'][1] ?? 0) > 0 ? '0.' . (($p['v'][1] ?? 0) + 1) . '.0' : '0.0.' . (($p['v'][2] ?? 0) + 1));
            return self::compare($version, $base) >= 0 && self::compare($version, $upper) < 0;
        }
        if (str_starts_with($c, '~')) {
            $base = substr($c, 1);
            $p = self::parse($base);
            if ($p === null) return false;
            $upper = $p['v'][0] . '.' . (($p['v'][1] ?? 0) + 1) . '.0';
            return self::compare($version, $base) >= 0 && self::compare($version, $upper) < 0;
        }
        if (str_ends_with($c, '.*')) {
            $prefix = substr($c, 0, -1); // "1.2."
            return str_starts_with($version, $prefix);
        }
        foreach (['>=', '<=', '>', '<', '='] as $op) {
            if (str_starts_with($c, $op)) {
                $cmp = self::compare($version, trim(substr($c, strlen($op))));
                return match ($op) {
                    '>=' => $cmp >= 0, '<=' => $cmp <= 0, '>' => $cmp > 0,
                    '<' => $cmp < 0, default => $cmp === 0,
                };
            }
        }
        return self::compare($version, $c) === 0;
    }

    /** @return array{v:list<int>,pre:string}|null */
    private static function parse(string $version): ?array
    {
        // Accept partial versions in constraints ("8.4" => 8.4.0).
        if (!preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-([0-9A-Za-z.-]+))?/', $version, $m)) {
            return null;
        }
        return ['v' => [(int) $m[1], (int) ($m[2] ?? 0), (int) ($m[3] ?? 0)], 'pre' => $m[4] ?? ''];
    }

    private static function comparePre(string $a, string $b): int
    {
        if ($a === $b) return 0;
        if ($a === '') return 1;   // release > prerelease
        if ($b === '') return -1;
        return strcmp($a, $b);
    }
}
