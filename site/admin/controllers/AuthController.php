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

    public function logout(Request $r): Response
    {
        $this->auth()->logout();
        return $this->redirect($r, '/admin/login');
    }

    private function standalone(Request $r, string $view, array $data): Response
    {
        $v = $this->c->get(View::class);
        $v->share('csrf', $this->csrf());
        $v->share('basePath', $r->basePath);
        return Response::html($v->render('admin:' . $view, $data));
    }
}
