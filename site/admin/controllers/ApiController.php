<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Api\ApiKeyService;
use Core\Http\Request;
use Core\Http\Response;

final class ApiController extends Controller
{
    public function health(Request $request): Response
    {
        if (!$this->authorized($request, 'health.read')) return $this->unauthorized();
        return $this->json(['ok' => true, 'core' => WC_VERSION, 'api' => WC_API_VERSION,
            'site' => (string) $this->settings()->get('site.name', 'Website'), 'time' => date('c')]);
    }

    public function pages(Request $request): Response
    {
        if (!$this->authorized($request, 'content.read')) return $this->unauthorized();
        $limit = max(1, min(100, (int) ($request->query['limit'] ?? 25)));
        $after = max(0, (int) ($request->query['after'] ?? 0));
        $rows = $this->db()->fetchAll('SELECT id,slug,title,status,updated_at,published_at FROM `' . $this->db()->table('pages') . '` WHERE deleted_at IS NULL AND id > ? ORDER BY id LIMIT ' . $limit, [$after]);
        return $this->json(['data' => $rows, 'next_after' => $rows === [] ? null : (int) (end($rows)['id'])]);
    }

    public function page(Request $request): Response
    {
        if (!$this->authorized($request, 'content.read')) return $this->unauthorized();
        $row = $this->db()->fetch('SELECT id,slug,title,body,blocks_json,status,meta_title,meta_description,updated_at,published_at FROM `' . $this->db()->table('pages') . '` WHERE id = ? AND deleted_at IS NULL', [(int) $request->attributes['id']]);
        return $row ? $this->json(['data' => $row]) : $this->json(['error' => 'not_found'], 404);
    }

    private function authorized(Request $request, string $capability): bool
    {
        return $this->c->get(ApiKeyService::class)->authenticate($request, $capability) !== null;
    }

    private function unauthorized(): Response
    {
        return $this->json(['error' => 'unauthorized'], 401)->header('WWW-Authenticate', 'Bearer realm="Website Core API"');
    }

    private function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status)->header('Cache-Control', 'private, no-store');
    }
}
