<?php
declare(strict_types=1);

namespace Core\Security;

use Core\Http\Request;

/**
 * Session-bound CSRF tokens with rotation support. Token comparisons use
 * hash_equals; tokens are per-session, not per-request, so multi-tab use works.
 */
final class Csrf
{
    private const KEY = '_wc_csrf';

    public function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public function validate(Request $request): bool
    {
        $session = $_SESSION[self::KEY] ?? null;
        if (!is_string($session) || $session === '') {
            return false;
        }
        $presented = $request->post['_csrf'] ?? $request->header('X-CSRF-Token');
        return is_string($presented) && hash_equals($session, $presented);
    }

    public function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($this->token(), ENT_QUOTES) . '">';
    }

    public function rotate(): void
    {
        unset($_SESSION[self::KEY]);
    }
}
