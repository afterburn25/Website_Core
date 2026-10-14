<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

final class SettingsController extends Controller
{
    private const FIELDS = [
        'site.name'          => ['label' => 'Site name', 'type' => 'text'],
        'site.tagline'       => ['label' => 'Tagline', 'type' => 'text'],
        'site.contact_email' => ['label' => 'Public contact email', 'type' => 'email'],
        'site.phone'         => ['label' => 'Phone', 'type' => 'text'],
        'site.address'       => ['label' => 'Address', 'type' => 'text'],
        'site.locale'        => ['label' => 'Locale', 'type' => 'text'],
    ];

    public function index(Request $r): Response
    {
        $user = $this->user();
        if (!$user || !$this->auth()->has($user, 'settings.manage')) {
            return $this->forbidden();
        }

        $settings = $this->settings();
        if ($r->isPost()) {
            foreach (self::FIELDS as $key => $meta) {
                $settings->set($key, $r->string(str_replace('.', '_', $key)));
            }
            if ($settings->get('system.cron_token', '') === '') {
                $settings->set('system.cron_token', bin2hex(random_bytes(24)));
            }
            $this->flash('ok', 'Settings saved.');
            return $this->redirect($r, '/admin/settings');
        }

        $values = [];
        foreach (self::FIELDS as $key => $meta) {
            $values[$key] = (string) $settings->get($key, '');
        }
        return $this->page($r, 'settings/index', [
            'fields'    => self::FIELDS,
            'values'    => $values,
            'cronToken' => (string) $settings->get('system.cron_token', ''),
        ], 'Settings');
    }
}
