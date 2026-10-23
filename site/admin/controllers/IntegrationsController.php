<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Api\ApiKeyService;
use Core\Api\WebhookService;
use Core\Content\SiteTransfer;
use Core\Http\Request;
use Core\Http\Response;

final class IntegrationsController extends Controller
{
    private function allowed(): bool
    {
        $user = $this->user();
        return $user !== null && $this->auth()->has($user, 'settings.manage');
    }

    public function index(Request $request): Response
    {
        if (!$this->allowed()) return $this->forbidden();
        $keys = $this->c->get(ApiKeyService::class);
        $hooks = $this->c->get(WebhookService::class);
        if ($request->isPost()) {
            try {
                $action = $request->string('action');
                if ($action === 'key_create') {
                    $caps = $request->input('capabilities', []);
                    $created = $keys->create($request->string('label'), is_array($caps) ? $caps : []);
                    $_SESSION['_wc_api_token'] = $created['token'];
                    $this->flash('ok', 'API key created. Copy it now; it will not be shown again.');
                } elseif ($action === 'key_revoke') {
                    $keys->revoke((int) $request->string('id'));
                    $this->flash('ok', 'API key revoked.');
                } elseif ($action === 'webhook_create') {
                    $events = $request->input('events', []);
                    $created = $hooks->create($request->string('label'), $request->string('url'), is_array($events) ? $events : []);
                    $_SESSION['_wc_webhook_secret'] = $created['secret'];
                    $this->flash('ok', 'Webhook created. Copy its signing secret now.');
                } elseif ($action === 'webhook_disable') {
                    $hooks->disable((int) $request->string('id'));
                    $this->flash('ok', 'Webhook disabled.');
                } elseif ($action === 'site_import') {
                    $file = $request->files['site_file'] ?? null;
                    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new \RuntimeException('Choose a Website Core JSON export.');
                    $raw = file_get_contents((string) $file['tmp_name']);
                    $doc = json_decode((string) $raw, true, 64, JSON_THROW_ON_ERROR);
                    $counts = (new SiteTransfer($this->db()))->import($doc);
                    $this->flash('ok', 'Site content imported: ' . $counts['pages'] . ' pages, ' . $counts['translations'] . ' translations, ' . $counts['menus'] . ' menus and ' . $counts['settings'] . ' settings.');
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
            }
            return $this->redirect($request, '/admin/integrations');
        }
        $token = $_SESSION['_wc_api_token'] ?? null; unset($_SESSION['_wc_api_token']);
        $secret = $_SESSION['_wc_webhook_secret'] ?? null; unset($_SESSION['_wc_webhook_secret']);
        return $this->page($request, 'integrations/index', ['keys' => $keys->all(), 'hooks' => $hooks->all(),
            'deliveries' => $hooks->recent(), 'apiCapabilities' => ApiKeyService::CAPABILITIES,
            'webhookEvents' => WebhookService::EVENTS, 'newToken' => $token, 'newSecret' => $secret], 'Integrations');
    }

    public function export(Request $request): Response
    {
        if (!$this->allowed()) return $this->forbidden();
        $json = json_encode((new SiteTransfer($this->db()))->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return Response::text($json)->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="website-core-site-' . date('Ymd-His') . '.json"');
    }
}
