<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;

/**
 * Purpose-bound, hashed, expiring preview links for draft pages. Tokens
 * are shown once (plaintext), stored hashed, single-purpose (a specific
 * page), and marked used on first hit — previews never enter caches,
 * sitemaps, or search metadata.
 */
final class PreviewTokens
{
    public function __construct(private Connection $db) {}

    /** @return string plaintext token (show once) */
    public function issue(int $pageId, int $adminId, int $ttlSeconds = 86400): string
    {
        $token = bin2hex(random_bytes(24));
        $this->db->insert('preview_tokens', [
            'token_hash' => hash('sha256', $token),
            'page_id'    => $pageId,
            'created_by' => $adminId,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    /** @return ?int page id when the token is valid and unexpired */
    public function redeem(string $token): ?int
    {
        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('preview_tokens') . '` WHERE token_hash = ?',
            [hash('sha256', $token)]
        );
        if (!$row || $row['used_at'] !== null) {
            return null;
        }
        if ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        $this->db->update('preview_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
        return (int) $row['page_id'];
    }
    /** Non-consuming validity check (multi-use links within TTL). */
    public function peek(string $token): ?int
    {
        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('preview_tokens') . '` WHERE token_hash = ?',
            [hash('sha256', $token)]
        );
        if (!$row || ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time())) {
            return null;
        }
        return (int) $row['page_id'];
    }
}
