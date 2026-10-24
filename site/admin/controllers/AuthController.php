<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Container;
use Core\Http\Request;
use Core\Http\Response;
use Core\View\View;

final class AuthController extends Controller
{
    public function login(Request $r): Response
    {
        if ($this->user() !== null) {
            return $this->redirect($r, '/admin');
        }
        $error = null;
        if ($r->isPost()) {
            if (!$this->csrf()->validate($r)) {
                $error = 'Session expired — try again.';
            } else {
                try {
                    $user = $this->auth()->attemptPassword(
                        $r,
                        $r->string('username'),
                        (string) $r->post['password']
                    );
                    if ($user === null) {
                        return $this->redirect($r, '/admin/mfa');
                    }
                    return $this->redirect($r, '/admin');
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }
        return $this->standalone($r, 'auth/login', ['error' => $error, 'username' => $r->string('username')]);
    }

    public function mfa(Request $r): Response
    {
        if ($this->user() !== null) {
            return $this->redirect($r, '/admin');
        }
        if (empty($_SESSION[\Core\Auth\AuthService::PENDING_KEY])) {
            return $this->redirect($r, '/admin/login');
        }
        $error = null;
        if ($r->isPost()) {
            if (!$this->csrf()->validate($r)) {
                $error = 'Session expired — try again.';
            } else {
                try {
                    $this->auth()->completeMfa($r, $r->string('code'));
                    return $this->redirect($r, '/admin');
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }
        return $this->standalone($r, 'auth/mfa', ['error' => $error]);
    }

    public function forgot(Request $r): Response
    {
        if ($this->user() !== null) {
            return $this->redirect($r, '/admin');
        }
        $sent = false;
        $error = null;
        if ($r->isPost()) {
            if (!$this->csrf()->validate($r)) {
                $error = 'Session expired — try again.';
            } else {
                try {
                    $this->auth()->requestReset($r, $r->string('identifier'));
                    $sent = true;
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }
        return $this->standalone($r, 'auth/forgot', [
            'error' => $error,
            'sent' => $sent,
            'identifier' => $r->string('identifier'),
        ]);
    }

    public function reset(Request $r): Response
    {
        if ($this->user() !== null) {
            return $this->redirect($r, '/admin');
        }
        $token = $r->string('token');
        $error = null;
        if ($r->isPost()) {
            if (!$this->csrf()->validate($r)) {
                $error = 'Session expired — try again.';
            } else {
                $new = (string) $r->post['password'];
                if (strlen($new) < 12) {
                    $error = 'New password must be at least 12 characters.';
                } elseif ($new !== (string) $r->post['password2']) {
                    $error = 'New passwords do not match.';
                } else {
                    try {
                        if ($this->auth()->completeReset($r, $token, $new)) {
                            return $this->standalone($r, 'auth/reset', [
                                'error' => null, 'token' => '', 'done' => true,
                            ]);
                        }
                        $error = 'That reset link is invalid or has expired.';
                    } catch (\RuntimeException $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }
        return $this->standalone($r, 'auth/reset', [
            'error' => $error,
            'token' => $token,
        ]);
    }

    public function logout(Request $r): Response
    {
        $this->auth()->logout();
        return $this->redirect($r, '/admin/login');
    }

    private function standalone(Request $r, string $view, array $data): Response
    {
        $v = $this->c->get(View::class);
        $v->share('csrf', $this->csrf());
        $v->share('basePath', $r->routeBase());
        return Response::html($v->render('admin:' . $view, $data));
    }
}
