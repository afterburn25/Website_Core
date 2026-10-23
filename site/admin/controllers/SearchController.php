<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleRegistry;

final class SearchController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u) {
            return $this->forbidden();
        }
        $query = substr($r->string('q'), 0, 100);
        $results = [];
        if ($query !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
            if ($this->auth()->has($u, 'content.manage')) {
                foreach ($this->db()->fetchAll(
                    'SELECT id, title, slug, status FROM `' . $this->db()->table('pages')
                    . '` WHERE title LIKE ? OR slug LIKE ? ORDER BY updated_at DESC LIMIT 20',
                    [$like, $like]
                ) as $row) {
                    $results[] = [
                        'type' => 'Page', 'title' => $row['title'],
                        'detail' => '/' . $row['slug'] . ' · ' . $row['status'],
                        'url' => '/admin/pages/edit/' . $row['id'],
                    ];
                }
            }
            if ($this->auth()->has($u, 'media.manage')) {
                foreach ($this->db()->fetchAll(
                    'SELECT id, original_name, folder, mime FROM `' . $this->db()->table('media')
                    . '` WHERE original_name LIKE ? OR alt_text LIKE ? ORDER BY id DESC LIMIT 20',
                    [$like, $like]
                ) as $row) {
                    $results[] = [
                        'type' => 'Media', 'title' => $row['original_name'],
                        'detail' => trim((string) $row['folder'], '/') . ' · ' . $row['mime'],
                        'url' => '/admin/media?highlight=' . $row['id'],
                    ];
                }
            }
            if ($this->auth()->has($u, 'modules.view')) {
                foreach ($this->c->get(ModuleRegistry::class)->all() as $id => $info) {
                    $name = (string) ($info['manifest']['name'] ?? $id);
                    if (stripos($id . ' ' . $name, $query) !== false) {
                        $results[] = [
                            'type' => 'Package', 'title' => $name,
                            'detail' => $id . ' · ' . $info['state'], 'url' => '/admin/modules',
                        ];
                    }
                }
            }
        }
        return $this->page($r, 'search/index', compact('query', 'results'), 'Search');
    }
}
