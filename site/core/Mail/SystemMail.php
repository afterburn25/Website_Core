<?php
declare(strict_types=1);

namespace Core\Mail;

use Core\Database\Connection;
use Core\Settings\SettingsRepository;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Transactional system-message queue + SMTP transport.
 *
 * - queue() dedupes on dedupe_key so retries/retries can't double-send.
 * - The job runner drains 'mail.send' — delivery is recorded as sent/failed
 *   with the transport's own error; "sent" means accepted by the configured
 *   transport, not delivered to a mailbox.
 * - No SMTP configured → PHP mail() fallback only when enabled in settings;
 *   otherwise messages stay queued (shown on the health page) rather than
 *   blocking the site.
 * - Never sends real mail in tests: transport 'capture' writes to a callable.
 */
final class SystemMail
{
    /** @var null|callable(string $to,string $subject,string $body): void */
    private static $capture = null;

    public static function captureWith(callable $fn): void
    {
        self::$capture = $fn;
    }

    public function __construct(
        private Connection $db,
        private SettingsRepository $settings,
    ) {}

    /** Queue a message; dedupe_key prevents duplicates among unsent rows. */
    public function queue(string $to, string $subject, string $body, ?string $dedupeKey = null): int
    {
        if ($dedupeKey !== null) {
            $dup = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $this->db->table('system_messages')
                . "` WHERE dedupe_key = ? AND status IN ('queued','failed')",
                [hash('sha256', $dedupeKey)]
            );
            if ((int) $dup > 0) {
                return 0;
            }
        }
        return (int) $this->db->insert('system_messages', [
            'to' => $to, 'subject' => $subject, 'body' => $body,
            'status' => 'queued', 'dedupe_key' => $dedupeKey ? hash('sha256', $dedupeKey) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Convenience wrappers for the built-in transactional notices. */
    public function passwordReset(string $to, string $link): int
    {
        return $this->queue(
            $to, 'Password reset requested',
            "A password reset was requested for your administrator account.\n\n"
            . "Open this link within 30 minutes to choose a new password:\n{$link}\n\n"
            . 'If you did not request it, ignore this message.',
            'pwreset:' . $to
        );
    }

    /** Drain up to $limit queued messages through the configured transport. */
    public function flush(int $limit = 10): int
    {
        $table = $this->db->table('system_messages');
        // Reclaim claims stranded by a crashed worker — a row left in
        // 'sending' past the lease is safe to retry (the send either never
        // happened or the status write was lost; re-sending duplicates at
        // worst, matching at-least-once semantics).
        $this->db->pdo()->prepare(
            "UPDATE `{$table}` SET status = 'queued', claimed_at = NULL
             WHERE status = 'sending' AND claimed_at < ?"
        )->execute([date('Y-m-d H:i:s', time() - 300)]);

        $rows = $this->db->fetchAll(
            'SELECT * FROM `' . $table
            . "` WHERE status = 'queued' ORDER BY id LIMIT " . (int) $limit
        );
        $sent = 0;
        foreach ($rows as $row) {
            // Atomic claim — concurrent workers may have selected the same
            // row; only the winner of this guarded update may send it.
            if ($this->db->update('system_messages',
                    ['status' => 'sending', 'claimed_at' => date('Y-m-d H:i:s')],
                    "id = ? AND status = 'queued'",
                    [(int) $row['id']]) === 0) {
                continue;
            }
            [$ok, $err] = $this->send((string) $row['to'], (string) $row['subject'], (string) $row['body']);
            $this->db->update('system_messages', [
                'status' => $ok ? 'sent' : 'failed',
                'attempts' => (int) $row['attempts'] + 1,
                'last_error' => $ok ? null : substr((string) $err, 0, 2000),
                'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
                'claimed_at' => null,
            ], 'id = ?', [(int) $row['id']]);
            if ($ok) {
                $sent++;
            }
        }
        return $sent;
    }

    /** @return array{0:bool,1:?string} */
    private function send(string $to, string $subject, string $body): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [false, 'invalid recipient'];
        }
        $transport = (string) $this->settings->get('mail.transport', 'smtp');
        if ($transport === 'capture') {
            // Test/QA capture transport — never a real send.
            (self::$capture ?? fn () => null)($to, $subject, $body);
            return [true, null];
        }
        if ($transport === 'none') {
            return [false, 'mail transport disabled'];
        }

        $mailer = new PHPMailer(true);
        try {
            $from = (string) $this->settings->get('mail.from', 'no-reply@localhost');
            $fromName = (string) $this->settings->get('site.name', 'Website Core');
            if ($transport === 'mail') {
                $mailer->isMail();
            } else {
                $mailer->isSMTP();
                $mailer->Host = (string) $this->settings->get('smtp.host', '');
                if ($mailer->Host === '') {
                    return [false, 'smtp.host not configured'];
                }
                $mailer->Port = (int) $this->settings->get('smtp.port', '587') ?: 587;
                $secure = (string) $this->settings->get('smtp.secure', 'tls');
                if ($secure !== '') {
                    $mailer->SMTPSecure = $secure;
                }
                $user = (string) $this->settings->get('smtp.user', '');
                if ($user !== '') {
                    $mailer->SMTPAuth = true;
                    $mailer->Username = $user;
                    $mailer->Password = (string) $this->settings->get('smtp.password', '');
                }
                $mailer->Timeout = 15;
                $mailer->SMTPKeepAlive = false;
            }
            $mailer->setFrom($from, $fromName);
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->send();
            return [true, null];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }
}
