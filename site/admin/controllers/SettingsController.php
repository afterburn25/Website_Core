<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

final class SettingsController extends Controller
{
    private const FIELDS = [
        'app.url'            => ['label' => 'Base URL (https://…)', 'type' => 'text'],
        'site.name'          => ['label' => 'Site name', 'type' => 'text'],
        'site.tagline'       => ['label' => 'Tagline', 'type' => 'text'],
        'site.contact_email' => ['label' => 'Public contact email', 'type' => 'email'],
        'site.phone'         => ['label' => 'Phone', 'type' => 'text'],
        'site.address'       => ['label' => 'Address', 'type' => 'text'],
        'site.locale'        => ['label' => 'Locale', 'type' => 'text'],
    ];

    /** Mail settings — password is write-only and never rendered back. */
    private const MAIL_FIELDS = [
        'mail.transport' => ['label' => 'Mail transport (smtp | mail | none)', 'type' => 'text'],
        'mail.from'      => ['label' => 'From address', 'type' => 'email'],
        'smtp.host'      => ['label' => 'SMTP host', 'type' => 'text'],
        'smtp.port'      => ['label' => 'SMTP port', 'type' => 'number'],
        'smtp.secure'    => ['label' => 'SMTP encryption (tls | ssl | blank)', 'type' => 'text'],
        'smtp.user'      => ['label' => 'SMTP username', 'type' => 'text'],
    ];

    public function index(Request $r): Response
    {
        $user = $this->user();
        if (!$user || !$this->auth()->has($user, 'settings.manage')) {
            return $this->forbidden();
        }

        $settings = $this->settings();
        if ($r->isPost()) {
            $baseUrl = trim($r->string('app_url'));
            if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
                $this->flash('error', 'Base URL must start with http:// or https://');
                return $this->redirect($r, '/admin/settings');
            }
            foreach (self::FIELDS + self::MAIL_FIELDS as $key => $meta) {
                $settings->set($key, $r->string(str_replace('.', '_', $key)));
            }
            $pw = $r->string('smtp_password');
            if ($pw !== '') {
                $settings->set('smtp.password', $pw);
            }
            if ($settings->get('system.cron_token', '') === '') {
                $settings->set('system.cron_token', bin2hex(random_bytes(24)));
            }
            $this->flash('ok', 'Settings saved.');
            return $this->redirect($r, '/admin/settings');
        }

        $values = [];
        $fields = self::FIELDS;
        $fields['app.url']['label'] .= ' — leave blank to keep '
            . ((string) $this->c->get(\Core\Config::class)->get('app.url', '') ?: 'the installed URL');
        foreach ($fields + self::MAIL_FIELDS as $key => $meta) {
            $values[$key] = (string) $settings->get($key, '');
        }
        // License status
        $licenseStatus = null;
        try {
            $licenseValidator = $this->c->get(\Core\License\LicenseValidation::class);
            $licenseStatus = $licenseValidator->status();
        } catch (\Throwable $e) {
            // License table may not exist yet
        }
        return $this->page($r, 'settings/index', [
            'fields'    => $fields,
            'mailFields' => self::MAIL_FIELDS,
            'values'    => $values,
            'licenseStatus' => $licenseStatus,
            'hasSmtpPassword' => $settings->get('smtp.password', '') !== '',
            'cronToken' => (string) $settings->get('system.cron_token', ''),
        ], 'Settings');
    }

    /**
     * Admin-triggered license revalidation. Lets an owner who fixed a
     * domain registration restore a deactivated site immediately instead
     * of waiting for the next scheduled check.
     */
    public function validateLicense(Request $r): Response
    {
        $user = $this->user();
        if (!$user || !$this->auth()->has($user, 'settings.manage')) {
            return $this->forbidden();
        }

        try {
            $validator = $this->c->get(\Core\License\LicenseValidation::class);
            $status = $validator->status();
            if ($status['license_key'] === null) {
                $this->flash('error', 'No license is installed on this site.');
                return $this->redirect($r, '/admin/settings');
            }
            $domain = $validator->detectDomain();
            if ($domain === '') {
                $domain = (string) ($status['license_domain'] ?? '');
            }
            [$valid, $error, $denied] = $validator->validate(
                (string) $status['license_key'],
                $domain
            );
            if ($denied) {
                $validator->deactivate();
            }
            if ($valid) {
                $this->flash('ok', 'License validated — this site is active.');
            } else {
                $this->flash('error', 'License validation failed: ' . ($error ?? 'unknown error'));
            }
        } catch (\Throwable $e) {
            $this->flash('error', 'License validation unavailable: ' . $e->getMessage());
        }
        return $this->redirect($r, '/admin/settings');
    }
}
