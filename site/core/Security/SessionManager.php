<?php
declare(strict_types=1);

namespace Core\Security;

use Core\Config;
use Core\Http\Request;

/**
 * Secure PHP session wrapper: hardened cookie flags, ID rotation on
 * privilege change, and application-level idle + absolute expiry.
 */
final class SessionManager
{
    public function __construct(private Config $config) {}

    public function start(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = $this->cookieSecure($request);
        session_name((string) $this->config->get('security.session_name', 'WCSID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $request->basePath === '' ? '/' : $request->basePath . '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $this->enforceExpiry();
    }

    private function cookieSecure(Request $request): bool
    {
        $mode = (string) $this->config->get('security.cookie_secure', 'auto');
        return match ($mode) {
            'always' => true,
            'never'  => false,
            default  => $request->isSecure(),
        };
    }

    private function enforceExpiry(): void
    {
        $now = time();
        $idle = (int) $this->config->get('security.session_idle', 7200);
        $absolute = (int) $this->config->get('security.session_absolute', 43200);

        if (isset($_SESSION['_wc_started']) && $now - (int) $_SESSION['_wc_started'] > $absolute) {
            $this->destroy();
            session_start();
        } elseif (isset($_SESSION['_wc_touched']) && $now - (int) $_SESSION['_wc_touched'] > $idle) {
            $this->destroy();
            session_start();
        }

        $_SESSION['_wc_started'] ??= $now;
        $_SESSION['_wc_touched'] = $now;
    }

    /** Rotate on login / privilege change; call before storing identity. */
    public function rotate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_wc_started'] = time();
        $_SESSION['_wc_touched'] = time();
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
