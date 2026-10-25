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
        'smtp.secure'    => ['label' => 'SMTP encryption', 'type' => 'text'],
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
            $action = $r->string('action');
            if ($action === 'test_mail') {
                $recipient = strtolower($r->string('test_recipient'));
                if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $this->flash('error', 'Enter a valid recipient for the test message.');
                } else {
                    [$sent, $error] = $this->c->get(\Core\Mail\SystemMail::class)->testDelivery($recipient);
                    $this->audit('settings.mail_tested', ['recipient' => $recipient, 'sent' => $sent]);
                    $this->flash($sent ? 'ok' : 'error', $sent ? 'Test message accepted by the configured mail transport.' : 'Mail test failed: ' . ($error ?? 'unknown transport error'));
                }
                return $this->redirect($r, '/admin/settings');
            }
            if ($action === 'test_smtp') {
                [$smtpConfig,$smtpErrors] = $this->smtpTestInput($r);
                $_SESSION['_wc_settings_input'] = array_filter($r->post, static fn (string $key): bool => $key !== '_csrf' && $key !== 'smtp_password', ARRAY_FILTER_USE_KEY);
                if ($smtpErrors !== []) {
                    $this->flash('error', implode(' ', $smtpErrors) . ' Your non-secret entries are still below.');
                } else {
                    [$connected, $error] = $this->c->get(\Core\Mail\SystemMail::class)->testSmtpConnection($smtpConfig);
                    $this->audit('settings.smtp_connection_tested', [
                        'host' => $smtpConfig['host'], 'port' => $smtpConfig['port'], 'secure' => $smtpConfig['secure'], 'connected' => $connected,
                    ]);
                    $this->flash($connected ? 'ok' : 'error', $connected
                        ? 'SMTP connection and authentication succeeded. No message was sent and settings were not saved.'
                        : 'SMTP connection failed: ' . ($error ?? 'unknown connection error'));
                }
                return $this->redirect($r, '/admin/settings');
            }
            if ($action === 'rotate_cron') {
                $settings->set('system.cron_token', bin2hex(random_bytes(24)));
                $this->audit('settings.cron_token_rotated');
                $this->flash('ok', 'Scheduled-task token rotated. Update the URL in your hosting control panel.');
                return $this->redirect($r, '/admin/settings');
            }
            $baseUrl = trim($r->string('app_url'));
            $transport = $r->string('mail_transport');
            $errors = [];
            if ($baseUrl !== '' && !preg_match('#^https://[^\s/]+(?:/.*)?$#i', $baseUrl)) $errors[] = 'Base URL must be a complete HTTPS URL.';
            if (trim($r->string('site_name')) === '') $errors[] = 'Site name is required.';
            $contact = trim($r->string('site_contact_email'));
            if ($contact !== '' && !filter_var($contact, FILTER_VALIDATE_EMAIL)) $errors[] = 'Public contact email is invalid.';
            if (!preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $r->string('site_locale', 'en'))) $errors[] = 'Locale must look like en or en-US.';
            if (!in_array($transport, ['none', 'smtp', 'mail'], true)) $errors[] = 'Choose a supported mail transport.';
            $from = trim($r->string('mail_from'));
            if ($transport !== 'none' && !filter_var($from, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid From address is required when mail is enabled.';
            if ($transport === 'smtp') {
                if (trim($r->string('smtp_host')) === '' || preg_match('/[\s\/]/', $r->string('smtp_host'))) $errors[] = 'Enter a valid SMTP hostname.';
                $port = $this->smtpPort($r->string('smtp_port'), $r->string('smtp_secure'));
                if ($port < 1 || $port > 65535) $errors[] = 'SMTP port must be between 1 and 65535.';
                if (!in_array($r->string('smtp_secure'), ['', 'tls', 'ssl'], true)) $errors[] = 'Choose a supported SMTP encryption mode.';
                if ($r->string('smtp_user') !== '' && $r->string('smtp_password') === '' && $settings->get('smtp.password', '') === '') $errors[] = 'Enter the SMTP password for this username.';
            }
            if ($errors !== []) {
                $_SESSION['_wc_settings_input'] = array_filter($r->post, static fn (string $key): bool => $key !== '_csrf' && $key !== 'smtp_password', ARRAY_FILTER_USE_KEY);
                $this->flash('error', implode(' ', $errors) . ' Your non-secret entries are still below.');
                return $this->redirect($r, '/admin/settings');
            }
            foreach (self::FIELDS as $key => $meta) {
                $settings->set($key, $r->string(str_replace('.', '_', $key)));
            }
            $settings->set('mail.transport', $transport);
            $settings->set('mail.from', $r->string('mail_from'));
            // Preserve saved SMTP credentials while another transport is in
            // use. Disabled presentation fields are intentionally omitted by
            // browsers and must never erase a working SMTP configuration.
            if ($transport === 'smtp') {
                foreach (['smtp.host', 'smtp.port', 'smtp.secure', 'smtp.user'] as $key) {
                    $settings->set($key, $key === 'smtp.port' ? $port : $r->string(str_replace('.', '_', $key)));
                }
                $pw = $r->string('smtp_password');
                if ($pw !== '') {
                    $settings->set('smtp.password', $pw);
                }
            }
            if ($settings->get('system.cron_token', '') === '') {
                $settings->set('system.cron_token', bin2hex(random_bytes(24)));
            }
            $this->audit('settings.saved', ['mail_transport' => $transport]);
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
        $submitted = $_SESSION['_wc_settings_input'] ?? null;
        unset($_SESSION['_wc_settings_input']);
        if (is_array($submitted)) {
            foreach (array_keys($values) as $key) {
                $field = str_replace('.', '_', $key);
                if (array_key_exists($field, $submitted)) $values[$key] = (string) $submitted[$field];
            }
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

    /** In-page SMTP handshake test. Always returns JSON so a failed host cannot replace the Settings screen. */
    public function testSmtp(Request $r): Response
    {
        $user=$this->user();
        if(!$user||!$this->auth()->has($user,'settings.manage'))return Response::json(['ok'=>false,'message'=>'Your account cannot test mail settings.'],403);
        [$config,$errors]=$this->smtpTestInput($r);
        if($errors!==[])return Response::json(['ok'=>false,'message'=>implode(' ',$errors)])->header('Cache-Control','private, no-store');
        [$connected,$error]=$this->c->get(\Core\Mail\SystemMail::class)->testSmtpConnection($config);
        $this->audit('settings.smtp_connection_tested',['host'=>$config['host'],'port'=>$config['port'],'secure'=>$config['secure'],'connected'=>$connected]);
        return Response::json(['ok'=>$connected,'message'=>$connected
            ? 'Connection and authentication succeeded. No message was sent and nothing was saved.'
            : 'Connection failed: '.($error??'unknown connection error')])->header('Cache-Control','private, no-store');
    }

    /** In-page delivery test against the currently saved transport. */
    public function testMail(Request $r): Response
    {
        $user=$this->user();
        if(!$user||!$this->auth()->has($user,'settings.manage'))return Response::json(['ok'=>false,'message'=>'Your account cannot send a test message.'],403);
        $recipient=strtolower($r->string('test_recipient'));
        if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))return Response::json(['ok'=>false,'message'=>'Enter a valid recipient email address.'])->header('Cache-Control','private, no-store');
        [$sent,$error]=$this->c->get(\Core\Mail\SystemMail::class)->testDelivery($recipient);
        $this->audit('settings.mail_tested',['recipient'=>$recipient,'sent'=>$sent]);
        return Response::json(['ok'=>$sent,'message'=>$sent
            ? 'Test message accepted by the saved mail transport.'
            : 'Delivery test failed: '.($error??'unknown transport error')])->header('Cache-Control','private, no-store');
    }

    /** @return array{0:array{host:string,port:int,secure:string,username:string,password:string},1:list<string>} */
    private function smtpTestInput(Request $r): array
    {
        $host=trim($r->string('smtp_host'));$secure=$r->string('smtp_secure');$port=$this->smtpPort($r->string('smtp_port'),$secure);$username=$r->string('smtp_user');$password=$r->string('smtp_password');
        if($username!==''&&$password==='')$password=(string)$this->settings()->get('smtp.password','');
        $errors=[];
        if($r->string('mail_transport')!=='smtp')$errors[]='Choose SMTP server as the mail transport.';
        if($host===''||preg_match('/[\s\/]/',$host))$errors[]='Enter a valid SMTP hostname.';
        if($port<1||$port>65535)$errors[]='SMTP port must be between 1 and 65535.';
        if(!in_array($secure,['','tls','ssl'],true))$errors[]='Choose a supported SMTP encryption mode.';
        if($username!==''&&$password==='')$errors[]='Enter the SMTP password for this username.';
        return [['host'=>$host,'port'=>$port,'secure'=>$secure,'username'=>$username,'password'=>$password],$errors];
    }

    private function smtpPort(string $value, string $secure): int
    {
        if ($value !== '') return (int) $value;
        return $secure === 'ssl' ? 465 : ($secure === 'tls' ? 587 : 25);
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
