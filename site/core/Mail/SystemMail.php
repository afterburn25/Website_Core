<?php
declare(strict_types=1);

namespace Core\Mail;

use Core\Config;
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
        private ?Config $config = null,
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

    /** Send one immediate administrator-requested message to verify transport settings. */
    public function testDelivery(string $to): array
    {
        return $this->send(
            $to,
            'Website Core mail test',
            "This message confirms that Website Core can send transactional email.\n\nSent " . date('c'),
        );
    }

    /**
     * Verify an SMTP handshake and optional authentication without sending mail.
     * Posted values may be supplied so an administrator can test before saving.
     *
     * @param array{host?:string,port?:int,secure?:string,username?:string,password?:string} $configuration
     * @return array{0:bool,1:?string}
     */
    public function testSmtpConnection(array $configuration = []): array
    {
        $config = $this->smtpConfiguration($configuration);
        if ($config['host'] === '') return [false, 'smtp.host not configured'];
        if ($config['port'] < 1 || $config['port'] > 65535) return [false, 'invalid SMTP port'];
        if (!in_array($config['secure'], ['', 'tls', 'ssl'], true)) return [false, 'invalid SMTP encryption mode'];

        $resolved = @gethostbynamel($config['host']);
        if ($resolved === false || $resolved === []) {
            return [false, 'DNS lookup failed for ' . $config['host'] . '. Confirm the SMTP hostname in your hosting email settings; mail.domain.com is only a suggested default.'];
        }
        $errno = 0; $errstr = '';
        $probe = @stream_socket_client(
            'tcp://' . $config['host'] . ':' . $config['port'],
            $errno, $errstr, 4, STREAM_CLIENT_CONNECT
        );
        if (!is_resource($probe)) {
            $detail = trim($errstr) !== '' ? trim($errstr) : 'connection refused or timed out';
            return [false, 'The hostname resolved, but TCP port ' . $config['port'] . ' could not be reached (' . $detail
                . '). Confirm the port/encryption pair with the mail provider. Shared hosts may require their exact server hostname or localhost and may block outbound SMTP ports.'];
        }
        fclose($probe);

        $mailer = new PHPMailer(true);
        try {
            $this->configureSmtp($mailer, $config);
            if (!$mailer->smtpConnect()) {
                $error = $mailer->ErrorInfo !== '' ? $mailer->ErrorInfo : 'SMTP handshake was rejected';
                $mailer->smtpClose();
                return [false, $this->friendlySmtpError($error, $config)];
            }
            $mailer->smtpClose();
            return [true, null];
        } catch (\Throwable $e) {
            if ($mailer->getSMTPInstance()->connected()) $mailer->smtpClose();
            return [false, $this->friendlySmtpError($e->getMessage(), $config)];
        }
    }

    /** Turn transport-library messages into actionable, non-secret diagnostics. */
    private function friendlySmtpError(string $error, array $config): string
    {
        $lower = strtolower($error);
        if (str_contains($lower, 'authenticate') || str_contains($lower, '535')) {
            return 'The SMTP server was reached, but authentication failed. Confirm the complete mailbox username and password. Server detail: ' . $error;
        }
        if (str_contains($lower, 'certificate') || str_contains($lower, 'crypto') || str_contains($lower, 'tls')) {
            return 'The SMTP server was reached, but the encrypted handshake failed. Confirm that '
                . $config['secure'] . ' encryption matches port ' . $config['port'] . ' and that the hostname matches the mail certificate. Server detail: ' . $error;
        }
        if (str_contains($lower, 'connect')) {
            return 'TCP port ' . $config['port'] . ' answered, but the SMTP greeting or encrypted handshake did not complete. '
                . 'Use Diagnose host and ports in Settings to check SSL/465, STARTTLS/587, and a verified provider hostname. Server detail: ' . $error;
        }
        return 'The SMTP server was reached but rejected the connection. Server detail: ' . $error;
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
        $stored=$this->settings->all();$defaults=$this->defaults();
        $transport = (string) (array_key_exists('mail.transport',$stored)?$stored['mail.transport']:$defaults['transport']);
        if ($transport === 'capture') {
            // Test/QA capture transport — never a real send.
            (self::$capture ?? fn () => null)($to, $subject, $body);
            return [true, null];
        }
        if ($transport === 'none') {
            return [false, 'mail transport disabled'];
        }

        $mailer = new PHPMailer(true);
        $smtp = null;
        try {
            $from = (string) (array_key_exists('mail.from',$stored)?$stored['mail.from']:$defaults['from']);
            $fromName = (string) $this->settings->get('site.name', 'Website Core');
            if ($transport === 'mail') {
                $mailer->isMail();
            } else {
                $smtp = $this->smtpConfiguration();
                if ($smtp['host'] === '') {
                    return [false, 'smtp.host not configured'];
                }
                $this->configureSmtp($mailer, $smtp);
            }
            $mailer->setFrom($from, $fromName);
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->send();
            return [true, null];
        } catch (\Throwable $e) {
            return [false, is_array($smtp) ? $this->friendlySmtpError($e->getMessage(), $smtp) : $e->getMessage()];
        }
    }

    /** @return array{host:string,port:int,secure:string,username:string,password:string} */
    private function smtpConfiguration(array $overrides = []): array
    {
        $stored=$this->settings->all();$defaults=$this->defaults();
        return [
            'host' => trim((string) ($overrides['host'] ?? (array_key_exists('smtp.host',$stored)?$stored['smtp.host']:$defaults['host']))),
            'port' => (int) ($overrides['port'] ?? (array_key_exists('smtp.port',$stored)?$stored['smtp.port']:$defaults['port'])),
            'secure' => (string) ($overrides['secure'] ?? (array_key_exists('smtp.secure',$stored)?$stored['smtp.secure']:$defaults['secure'])),
            'username' => (string) ($overrides['username'] ?? $this->settings->get('smtp.user', '')),
            'password' => (string) ($overrides['password'] ?? $this->settings->get('smtp.password', '')),
        ];
    }

    private function defaults(): array
    {
        $url=(string)($this->settings->get('app.url','')?:$this->config?->get('app.url',''));
        return MailDefaults::fromUrl($url);
    }

    /** @param array{host:string,port:int,secure:string,username:string,password:string} $config */
    private function configureSmtp(PHPMailer $mailer, array $config): void
    {
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->Port = $config['port'];
        $mailer->SMTPSecure = $config['secure'];
        $mailer->SMTPAutoTLS = $config['secure'] !== '';
        $mailer->SMTPAuth = $config['username'] !== '';
        if ($mailer->SMTPAuth) {
            $mailer->Username = $config['username'];
            $mailer->Password = $config['password'];
        }
        // Admin connection tests must finish before ordinary shared-host
        // proxy timeouts turn the response into a generic Bad Gateway page.
        $mailer->Timeout = 8;
        $mailer->Timelimit = 10;
        $mailer->SMTPKeepAlive = false;
    }
}
