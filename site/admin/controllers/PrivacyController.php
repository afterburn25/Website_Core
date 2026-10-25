<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

final class PrivacyController extends Controller
{
    public function index(Request $r): Response
    {
        $user = $this->user();
        if (!$user || !$this->auth()->has($user, 'settings.manage')) return $this->forbidden();
        if ($r->isPost()) {
            $action = $r->string('action');
            if ($action === 'settings') $this->saveSettings($r);
            elseif (in_array($action, ['verify', 'complete', 'reopen'], true)) $this->transition($r, $action, $user->id);
            return $this->redirect($r, '/admin/privacy');
        }
        $privacy = [
            'banner_enabled' => (bool) $this->settings()->get('privacy.banner_enabled', true),
            'provider' => (string) $this->settings()->get('analytics.provider', 'none'),
            'site_id' => (string) $this->settings()->get('analytics.site_id', ''),
        ];
        $submitted = $_SESSION['_wc_privacy_settings'] ?? null;
        unset($_SESSION['_wc_privacy_settings']);
        if (is_array($submitted)) $privacy = array_replace($privacy, $submitted);
        return $this->page($r, 'privacy/index', [
            'requests' => $this->db()->fetchAll('SELECT * FROM `' . $this->db()->table('privacy_requests') . '` ORDER BY id DESC LIMIT 200'),
            'privacy' => $privacy,
        ], 'Privacy');
    }

    private function saveSettings(Request $r): void
    {
        $provider = in_array($r->string('provider'), ['none', 'ga4', 'plausible'], true) ? $r->string('provider') : 'none';
        $id = trim($r->string('site_id'));
        $banner = $r->string('banner_enabled') === '1';
        $error = null;
        if (!$banner && $provider !== 'none') $error = 'Enable the cookie choice banner before configuring optional analytics.';
        elseif ($provider === 'ga4' && !preg_match('/^G-[A-Z0-9]+$/i', $id)) $error = 'Enter a valid Google Analytics measurement ID such as G-ABC123.';
        elseif ($provider === 'plausible' && !preg_match('/^(?=.{1,253}$)(?!-)[A-Za-z0-9.-]+(?<!-)$/', $id)) $error = 'Enter a valid Plausible site domain.';
        if ($error) {
            $_SESSION['_wc_privacy_settings'] = ['banner_enabled' => $banner, 'provider' => $provider, 'site_id' => $id];
            $this->flash('error', $error . ' Your entered settings are still below.');
            return;
        }
        $this->settings()->set('privacy.banner_enabled', $banner);
        $this->settings()->set('analytics.provider', $provider);
        $this->settings()->set('analytics.site_id', $provider === 'none' ? '' : $id);
        $this->audit('privacy.settings_saved', ['analytics_provider' => $provider, 'banner_enabled' => $banner]);
        $this->flash('ok', 'Privacy and analytics settings saved.');
    }

    private function transition(Request $r, string $action, int $actorId): void
    {
        $id = max(1, (int) $r->string('id'));
        $row = $this->db()->fetch('SELECT * FROM `' . $this->db()->table('privacy_requests') . '` WHERE id = ?', [$id]);
        if (!$row) { $this->flash('error', 'Privacy request not found.'); return; }
        $now = date('Y-m-d H:i:s');
        if ($action === 'verify') {
            if ($row['status'] !== 'new') { $this->flash('error', 'Only a new request can be marked verified.'); return; }
            $this->db()->update('privacy_requests', ['status' => 'verified', 'verified_at' => $now, 'updated_at' => $now], 'id = ?', [$id]);
            $message = 'Identity verification recorded.';
        } elseif ($action === 'complete') {
            if ($row['status'] !== 'verified') { $this->flash('error', 'Verify the requester’s identity before completing the request.'); return; }
            $note = trim(strip_tags($r->string('resolution_note')));
            if (mb_strlen($note) < 3) { $this->flash('error', 'Add a short resolution note before completing the request.'); return; }
            $this->db()->update('privacy_requests', ['status' => 'completed', 'resolution_note' => mb_substr($note, 0, 2000), 'completed_by' => $actorId, 'completed_at' => $now, 'updated_at' => $now], 'id = ?', [$id]);
            $message = 'Privacy request completed with resolution evidence.';
        } else {
            if ($row['status'] !== 'completed') { $this->flash('error', 'Only a completed request can be reopened.'); return; }
            $this->db()->update('privacy_requests', ['status' => 'verified', 'completed_by' => null, 'completed_at' => null, 'updated_at' => $now], 'id = ?', [$id]);
            $message = 'Privacy request reopened for follow-up.';
        }
        $this->audit('privacy.request_' . $action, ['request' => $id]);
        $this->flash('ok', $message);
    }

    public function request(Request $r): Response
    {
        $message = '';
        $success = false;
        if ($r->isPost()) {
            if (!$this->csrf()->validate($r)) return Response::text('Invalid CSRF token.', 419);
            if ($r->string('website') !== '') return Response::html($this->publicForm($r, 'Thank you.', true));
            $limiter = $this->c->get(\Core\Security\RateLimiter::class);
            if (!$limiter->allow('privacy:' . hash('sha256', $r->ip()), 3, 3600)) return Response::html($this->publicForm($r, 'Please wait before sending another request.', false), 429);
            $type = in_array($r->string('request_type'), ['access', 'delete', 'correct'], true) ? $r->string('request_type') : 'access';
            $email = strtolower(trim($r->string('email')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $message = 'Enter a valid email address.';
            else {
                $id = (int) $this->db()->insert('privacy_requests', ['request_type' => $type, 'email' => $email, 'details' => mb_substr(strip_tags($r->string('details')), 0, 5000), 'ip_hash' => hash('sha256', $r->ip()), 'status' => 'new', 'created_at' => date('Y-m-d H:i:s')]);
                $message = 'Request #' . $id . ' was received. The site owner will verify your identity before making changes.';
                $success = true;
            }
        }
        return Response::html($this->publicForm($r, $message, $success))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex');
    }

    public function export(Request $r): Response
    {
        $user = $this->user(); if (!$user || !$this->auth()->has($user, 'settings.manage')) return $this->forbidden();
        $rows = $this->db()->fetchAll('SELECT id,request_type,email,details,status,created_at,verified_at,resolution_note,completed_at FROM `' . $this->db()->table('privacy_requests') . '` ORDER BY id DESC');
        $stream = fopen('php://temp', 'w+'); fputcsv($stream, ['id','request_type','email','details','status','created_at','verified_at','resolution_note','completed_at'], ',', '"', '');
        foreach ($rows as $row) fputcsv($stream, array_map([$this, 'csvCell'], array_values($row)), ',', '"', '');
        rewind($stream); $csv = (string) stream_get_contents($stream); fclose($stream);
        $this->audit('privacy.requests_exported', ['count' => count($rows)]);
        return Response::text($csv)->header('Content-Type', 'text/csv; charset=utf-8')->header('Content-Disposition', 'attachment; filename="privacy-requests-' . date('Ymd') . '.csv"')->header('Cache-Control', 'private, no-store');
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+\-@]/', ltrim($value)) ? "'" . $value : $value;
    }

    private function publicForm(Request $r, string $message, bool $success): string
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action = $e($r->routeUrl('/privacy/request')); $home = $e($r->routeUrl('/'));
        $email = $success ? '' : $e($r->string('email')); $details = $success ? '' : $e($r->string('details')); $selected = $success ? 'access' : $r->string('request_type', 'access');
        $options = ''; foreach (['access' => 'Access my data', 'correct' => 'Correct my data', 'delete' => 'Delete my data'] as $value => $label) $options .= '<option value="' . $value . '"' . ($selected === $value ? ' selected' : '') . '>' . $label . '</option>';
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Privacy request</title><style>body{margin:0;background:radial-gradient(circle at 80% 0,#164e63 0,transparent 35rem),#071722;color:#e8f5f3;font:16px/1.6 system-ui}.wrap{max-width:720px;margin:5vh auto;padding:2rem}.card{background:#102733;border:1px solid #ffffff1f;border-radius:22px;padding:clamp(1.3rem,5vw,2.5rem);box-shadow:0 30px 80px #0008}.eyebrow{color:#5eead4;font-size:.75rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}label{display:block;margin-top:1rem;font-weight:700}input,select,textarea{width:100%;box-sizing:border-box;padding:.85rem;border-radius:9px;border:1px solid #ffffff2b;background:#071722;color:white;font:inherit}button{margin-top:1rem;padding:.85rem 1.1rem;border:0;border-radius:9px;background:#5eead4;color:#042f2e;font-weight:800}.notice{padding:.85rem;background:#0f766e44;border:1px solid #5eead455;border-radius:9px}a{color:#99f6e4}.trap{position:absolute;left:-9999px}small{display:block;color:#b9cbd1;font-weight:400}</style></head><body><main class="wrap"><a href="'.$home.'">← Return to website</a><div class="card"><p class="eyebrow">Privacy center</p><h1>Control your information</h1><p>Ask to access, correct, or delete personal information connected with your email address.</p>'.($message !== '' ? '<p class="notice" role="status">'.$e($message).'</p>' : '').'<form method="post" action="'.$action.'">'.$this->csrf()->field().'<div class="trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label>What would you like us to do?<select name="request_type">'.$options.'</select></label><label>Email<input type="email" name="email" value="'.$email.'" autocomplete="email" required><small>We use this address to verify the request.</small></label><label>Helpful details<textarea name="details" rows="5" maxlength="5000">'.$details.'</textarea></label><button>Send privacy request</button></form></div></main></body></html>';
    }
}
