<?php
declare(strict_types=1);

namespace Core\Database;

/**
 * Ordered, journaled migrations. MySQL DDL may commit independently of a
 * transaction, so migrations are written to be re-runnable where practical
 * and the journal records what actually applied — no promise of DDL rollback.
 */
abstract class Migration
{
    abstract public function id(): string;          // unique, sortable, e.g. "2026_10_14_0001"
    abstract public function description(): string;
    abstract public function up(Connection $db): void;
    public function down(Connection $db): void {}    // best-effort; DDL may not rollback

    /** Driver-aware column/type helpers for portability of the core schema. */
    protected function autoId(): string
    {
        return 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    }

    protected function nowExpr(Connection $db): string
    {
        return 'CURRENT_TIMESTAMP';
    }

    protected function exec(Connection $db, string $sql): void
    {
        $db->pdo()->exec($sql);
    }
}
