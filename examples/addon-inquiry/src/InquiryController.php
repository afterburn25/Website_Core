<?php
declare(strict_types=1);

namespace Wcref\Inquiry;

use Core\Admin\Nav;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\SystemMail;
use Core\Modules\ModuleContext;
use Core\Security\Csrf;
use Core\Settings\SettingsRepository;
use Core\View\View;

final class InquiryController
{
    public function __construct(
        private ModuleContext $ctx,
        private string $ns,
    ) {}

    public function form(Request $r, ?string $error = null, array $old = []): Response
    {
        $v = $this->ctx->service(View::class);
        $settings = $this->ctx->service(SettingsRepository::class);
        $v->share('basePath', $r->basePath);
        $v->share('siteName', $settings->get('site.name', 'Website Core'));
        $v->share('tagline', $settings->get('site.tagline', ''));
        $v->share('pages', []);
        $body = $v->renderInLayout($this->ns . ':form', 'theme:layout', [
            'csrf' => $this->ctx->service(Csrf::class),
            'error' => $error,
            'sent' => $r->string('sent') === '1',
            'old' => $old,
            'page' => ['title' => 'Contact us', 'meta_title' => 'Contact'],
        ]);
        return Response::html($body);
    }

    public function submit(Request $r): Response
    {
        $csrf = $this->ctx->service(Csrf::class);
        if (!$csrf->validate($r)) {
            return Response::text('Invalid CSRF token.', 419);
        }
        // Honeypot — bots fill the hidden 'website' field; pretend success.
        if ($r->string('website') !== '') {
            return Response::redirect($r->basePath . '/inquiry?sent=1');
        }
        if (!$this->ctx->service(\Core\Security\RateLimiter::class)
            ->attempt('inquiry:' . $r->ip(), 5, 600)) {
            return $this->form($r, 'Too many submissions — please try again later.');
        }

        $name = trim($r->string('name'));
        $email = trim($r->string('email'));
        $message = trim((string) ($r->post['message'] ?? ''));
        $errors = [];
        if ($name === '' || strlen($name) > 190) {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if ($message === '' || strlen($message) > 5000) {
            $errors[] = 'A message of 1–5000 characters is required.';
        }
        if ($errors !== []) {
            return $this->form($r, implode(' ', $errors), ['name' => $name, 'email' => $email, 'message' => $message]);
        }

        $db = $this->ctx->service(Connection::class);
        $db->insert('inquiry_submissions', [
            'name' => $name, 'email' => $email, 'message' => $message,
            'ip' => $r->ip(), 'status' => 'new',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Optional notification through the transactional queue — never a
        // direct send from the request path.
        $settings = $this->ctx->service(SettingsRepository::class);
        $to = (string) $settings->get('site.contact_email', '');
        if ($to !== '') {
            $this->ctx->service(SystemMail::class)->queue(
                $to,
                'New inquiry from ' . $name,
                "Name: {$name}\nEmail: {$email}\n\n{$message}",
                'inquiry:' . sha1($name . $email . $message . date('YmdH'))
            );
        }
        return Response::redirect($r->basePath . '/inquiry?sent=1');
    }

    public function adminList(Request $r): Response
    {
        $auth = $this->ctx->service(\Core\Auth\AuthService::class);
        $user = $auth->user();
        if ($user === null || !$auth->has($user, 'inquiry.view')) {
            return Response::html('<h1>403 — Forbidden</h1>', 403);
        }
        $db = $this->ctx->service(Connection::class);
        $rows = $db->fetchAll(
            'SELECT * FROM `' . $db->table('inquiry_submissions') . '` ORDER BY id DESC LIMIT 200'
        );

        $v = $this->ctx->service(View::class);
        $settings = $this->ctx->service(SettingsRepository::class);
        $v->share('user', $user);
        $v->share('csrf', $this->ctx->service(Csrf::class));
        $v->share('basePath', $r->basePath);
        $v->share('currentPath', $r->path);
        $v->share('nav', $this->ctx->service(Nav::class)->visible($user));
        $v->share('siteName', $settings->get('site.name', 'Website Core'));
        $v->share('pageTitle', 'Inquiries');
        $v->share('flash', null);
        return Response::html($v->renderInLayout($this->ns . ':admin-list', 'admin:layouts/admin', [
            'rows' => $rows,
        ]));
    }
}
