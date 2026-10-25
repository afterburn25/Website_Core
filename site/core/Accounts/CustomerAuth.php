<?php
declare(strict_types=1);

namespace Core\Accounts;

use Core\Database\Connection;
use Core\Mail\SystemMail;

final class CustomerAuth
{
    private const SESSION_ID = '_wc_customer_id';
    private const SESSION_PACKAGE = '_wc_customer_package';

    public function __construct(private Connection $db, private SystemMail $mail) {}

    public function user(string $ownerPackage): ?array
    {
        $id = (int) ($_SESSION[self::SESSION_ID] ?? 0);
        if ($id < 1 || !hash_equals((string) ($_SESSION[self::SESSION_PACKAGE] ?? ''), $ownerPackage)) return null;
        $row = $this->db->fetch('SELECT id,owner_package,email,name,phone,status,last_login_at,created_at FROM `'
            . $this->db->table('customer_accounts') . '` WHERE id = ? AND owner_package = ?', [$id, $ownerPackage]);
        if (!$row || $row['status'] !== 'active') { $this->logout(); return null; }
        return $row;
    }

    /** @return array{0:?array,1:?string} */
    public function register(string $ownerPackage, array $input): array
    {
        $name = trim(strip_tags((string) ($input['name'] ?? '')));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = mb_substr(trim(strip_tags((string) ($input['phone'] ?? ''))), 0, 60);
        $password = (string) ($input['password'] ?? '');
        if ($name === '' || mb_strlen($name) > 190) return [null, 'Enter your name.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) return [null, 'Enter a valid email address.'];
        if (!$this->strongPassword($password)) return [null, 'Use at least 12 characters with upper and lowercase letters and a number.'];
        try {
            $id = (int) $this->db->insert('customer_accounts', [
                'owner_package' => $ownerPackage, 'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'name' => $name,
                'phone' => $phone, 'status' => 'active', 'last_login_at' => null,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException) {
            return [null, 'An account already exists for that email address.'];
        }
        $this->establish($id, $ownerPackage);
        return [$this->user($ownerPackage), null];
    }

    public function login(string $ownerPackage, string $email, string $password): ?string
    {
        $email = strtolower(trim($email));
        $row = $this->db->fetch('SELECT * FROM `' . $this->db->table('customer_accounts') . '` WHERE owner_package = ? AND email = ?', [$ownerPackage, $email]);
        if (!$row || $row['status'] !== 'active' || !password_verify($password, (string) $row['password_hash'])) {
            password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            return 'Email or password was not recognized.';
        }
        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->update('customer_accounts', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [(int) $row['id']]);
        }
        $this->db->update('customer_accounts', ['last_login_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
        $this->establish((int) $row['id'], $ownerPackage);
        return null;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_ID], $_SESSION[self::SESSION_PACKAGE]);
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    }

    public function update(string $ownerPackage, int $id, array $input): ?string
    {
        $name = trim(strip_tags((string) ($input['name'] ?? '')));
        $phone = mb_substr(trim(strip_tags((string) ($input['phone'] ?? ''))), 0, 60);
        if ($name === '' || mb_strlen($name) > 190) return 'Enter your name.';
        $this->db->update('customer_accounts', ['name' => $name, 'phone' => $phone, 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND owner_package = ?', [$id, $ownerPackage]);
        return null;
    }

    public function requestReset(string $ownerPackage, string $email, string $absoluteResetBase): void
    {
        $row = $this->db->fetch('SELECT id,email FROM `' . $this->db->table('customer_accounts') . '` WHERE owner_package = ? AND email = ? AND status = ?', [$ownerPackage, strtolower(trim($email)), 'active']);
        if (!$row) return;
        $token = bin2hex(random_bytes(24));
        $this->db->insert('customer_password_resets', [
            'customer_id' => (int) $row['id'], 'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 1800), 'used_at' => null, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->mail->queue((string) $row['email'], 'Reset your customer account password',
            "Open this link within 30 minutes to choose a new password:\n\n" . $absoluteResetBase . rawurlencode($token)
            . "\n\nIf you did not request this, ignore this message.", 'customer-reset:' . $row['id'] . ':' . hash('sha256',$token));
    }

    public function reset(string $ownerPackage, string $token, string $password): ?string
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) return 'This reset link is invalid or expired.';
        if (!$this->strongPassword($password)) return 'Use at least 12 characters with upper and lowercase letters and a number.';
        $row = $this->db->fetch('SELECT r.id,r.customer_id FROM `' . $this->db->table('customer_password_resets') . '` r JOIN `'
            . $this->db->table('customer_accounts') . '` c ON c.id=r.customer_id WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at>=? AND c.owner_package=?',
            [hash('sha256', $token), date('Y-m-d H:i:s'), $ownerPackage]);
        if (!$row) return 'This reset link is invalid or expired.';
        $this->db->transaction(function () use ($row, $password): void {
            $this->db->update('customer_accounts', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$row['customer_id']]);
            $this->db->update('customer_password_resets', ['used_at' => date('Y-m-d H:i:s')], 'customer_id = ? AND used_at IS NULL', [(int)$row['customer_id']]);
        });
        return null;
    }

    public function all(string $ownerPackage): array
    {
        return $this->db->fetchAll('SELECT id,email,name,phone,status,last_login_at,created_at FROM `' . $this->db->table('customer_accounts') . '` WHERE owner_package=? ORDER BY created_at DESC', [$ownerPackage]);
    }

    public function setStatus(string $ownerPackage, int $id, string $status): bool
    {
        if (!in_array($status, ['active','disabled'], true)) return false;
        return $this->db->update('customer_accounts', ['status'=>$status,'updated_at'=>date('Y-m-d H:i:s')], 'id=? AND owner_package=?', [$id,$ownerPackage]) > 0;
    }

    public function cleanupExpiredResets(): int
    {
        return $this->db->run('DELETE FROM `' . $this->db->table('customer_password_resets')
            . '` WHERE expires_at < ? OR (used_at IS NOT NULL AND used_at < ?)',
            [date('Y-m-d H:i:s', time()-86400),date('Y-m-d H:i:s', time()-86400)])->rowCount();
    }

    private function establish(int $id, string $ownerPackage): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        $_SESSION[self::SESSION_ID] = $id; $_SESSION[self::SESSION_PACKAGE] = $ownerPackage;
    }

    private function strongPassword(string $password): bool
    {
        return strlen($password) >= 12 && preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password) && preg_match('/\d/', $password);
    }
}
