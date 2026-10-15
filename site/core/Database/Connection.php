<?php
declare(strict_types=1);

namespace Core\Database;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. MySQL/MariaDB is the supported driver; every query goes
 * through prepared statements. A PDO may be injected for test doubles, but
 * only the mysql driver is supported for production and CI acceptance.
 */
final class Connection
{
    public function __construct(
        private PDO $pdo,
        private string $prefix = '',
    ) {}

    public static function mysql(
        string $host,
        int $port,
        string $name,
        string $user,
        string $password,
        string $charset = 'utf8mb4',
        string $prefix = '',
    ): self {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET NAMES {$charset}");
        return new self($pdo, $prefix);
    }

    /**
     * Development/test driver only. Production installs and CI acceptance
     * always use ::mysql(). Documented in COMPATIBILITY.md.
     */
    public static function sqlite(string $path = ':memory:', string $prefix = ''): self
    {
        return new self(new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]), $prefix);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /** Prefix-aware table name. Callers always use logical names. */
    public function table(string $name): string
    {
        self::assertIdent($name);
        return $this->prefix . $name;
    }

    /** Identifiers are interpolated, never bound — allowlist them. */
    private static function assertIdent(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException("Unsafe SQL identifier: {$name}");
        }
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    public function insert(string $table, array $data): string
    {
        $cols = array_keys($data);
        foreach ($cols as $c) {
            self::assertIdent($c);
        }
        $sql = 'INSERT INTO `' . $this->table($table) . '` (`'
            . implode('`,`', $cols) . '`) VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
        $this->run($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $cols = array_keys($data);
        foreach ($cols as $c) {
            self::assertIdent($c);
        }
        $set = implode(',', array_map(fn (string $c) => "`{$c}`=?", $cols));
        $stmt = $this->run(
            'UPDATE `' . $this->table($table) . "` SET {$set} WHERE {$where}",
            [...array_values($data), ...$params]
        );
        return $stmt->rowCount();
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function tableExists(string $name): bool
    {
        if ($this->driver() === 'sqlite') {
            return (bool) $this->fetchColumn(
                "SELECT count(*) FROM sqlite_master WHERE type='table' AND name=?",
                [$this->table($name)]
            );
        }
        return (bool) $this->fetchColumn(
            'SELECT count(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$this->table($name)]
        );
    }
}
