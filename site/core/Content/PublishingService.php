<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;

/** Applies due publish/unpublish transitions; safe to run repeatedly. */
final class PublishingService
{
    public function __construct(private Connection $db) {}

    /** @return array{published:int,unpublished:int} */
    public function runDue(?string $now = null): array
    {
        $now ??= date('Y-m-d H:i:s');
        $table = $this->db->table('pages');
        $published = $this->db->run(
            "UPDATE `{$table}` SET status = 'published', published_at = ?, publish_at = NULL, updated_at = ?"
            . " WHERE deleted_at IS NULL AND publish_at IS NOT NULL AND publish_at <= ? AND status <> 'published'",
            [$now, $now, $now]
        )->rowCount();
        $unpublished = $this->db->run(
            "UPDATE `{$table}` SET status = 'draft', unpublish_at = NULL, updated_at = ?"
            . " WHERE deleted_at IS NULL AND unpublish_at IS NOT NULL AND unpublish_at <= ? AND status = 'published'",
            [$now, $now]
        )->rowCount();
        return ['published' => $published, 'unpublished' => $unpublished];
    }
}
