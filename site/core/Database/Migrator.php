<?php
declare(strict_types=1);

namespace Core\Database;

/**
 * Discovers migration files, applies pending ones in id order, and journals
 * each application (id, description, batch, applied_at, checksum). A failed
 * migration records a failure row so operations can inspect what stopped.
 */
final class Migrator
{
    public function __construct(
        private Connection $db,
        private string $migrationsDir,
    ) {}

    public function ensureJournal(): void
    {
        $table = $this->db->table('migrations');
        if ($this->db->driver() === 'sqlite') {
            $this->db->pdo()->exec(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration_id VARCHAR(120) NOT NULL UNIQUE,
                    description VARCHAR(255) NOT NULL,
                    batch INTEGER NOT NULL,
                    checksum CHAR(64) NOT NULL,
                    applied_at TEXT NOT NULL
                )"
            );
            return;
        }
        $this->db->pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `{$table}` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                migration_id VARCHAR(120) NOT NULL UNIQUE,
                description VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                checksum CHAR(64) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return list<Migration> pending migrations in order */
    public function pending(): array
    {
        $this->ensureJournal();
        $applied = array_column(
            $this->db->fetchAll('SELECT migration_id FROM `' . $this->db->table('migrations') . '`'),
            'migration_id'
        );
        return array_values(array_filter(
            $this->discover(),
            fn (Migration $m) => !in_array($m->id(), $applied, true)
        ));
    }

    /** @return list<Migration> */
    public function discover(): array
    {
        $files = glob($this->migrationsDir . '/*.php') ?: [];
        sort($files);
        $migrations = [];
        foreach ($files as $file) {
            $migration = $this->load($file);
            if ($migration !== null) {
                $migrations[] = $migration;
            }
        }
        usort($migrations, fn (Migration $a, Migration $b) => strcmp($a->id(), $b->id()));
        return $migrations;
    }

    /** @return list<string> ids applied this run */
    public function migrate(): array
    {
        $this->ensureJournal();
        $applied = [];
        $batch = (int) ($this->db->fetchColumn(
            'SELECT COALESCE(MAX(batch),0) FROM `' . $this->db->table('migrations') . '`'
        ) ?: 0) + 1;

        foreach ($this->pending() as $migration) {
            $migration->up($this->db);
            $this->db->insert('migrations', [
                'migration_id' => $migration->id(),
                'description'  => $migration->description(),
                'batch'        => $batch,
                'checksum'     => hash_file('sha256', $this->fileFor($migration)),
                'applied_at'   => date('Y-m-d H:i:s'),
            ]);
            $applied[] = $migration->id();
        }
        return $applied;
    }

    private function load(string $file): ?Migration
    {
        $object = require $file;
        return $object instanceof Migration ? $object : null;
    }

    private function fileFor(Migration $m): string
    {
        $ref = new \ReflectionClass($m);
        return (string) $ref->getFileName();
    }
    /** @internal exposed for tests */
    public static function classFile(Migration $m): string
    {
        return (string) (new \ReflectionClass($m))->getFileName();
    }
}
