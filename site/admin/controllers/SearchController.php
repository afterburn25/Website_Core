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
            $registry = $this->c->get(ModuleRegistry::class);
            if ($registry->supportsFeature('commerce.catalog') && $this->auth()->has($u, 'commerce.manage')) {
                $owner = (string) ($registry->feature('commerce.catalog')['package'] ?? '');
                foreach ($this->db()->fetchAll('SELECT id,name,sku,active FROM `' . $this->db()->table('commerce_products') . '` WHERE owner_package=? AND (name LIKE ? OR sku LIKE ?) ORDER BY updated_at DESC LIMIT 20', [$owner,$like,$like]) as $row) {
                    $results[]=['type'=>'Product','title'=>$row['name'],'detail'=>$row['sku'].' · '.((int)$row['active']?'active':'hidden'),'url'=>'/admin/store/products/edit&id='.$row['id']];
                }
            }
            if ($registry->supportsFeature('commerce.orders') && $this->auth()->has($u, 'orders.manage')) {
                $owner = (string) ($registry->feature('commerce.orders')['package'] ?? '');
                foreach ($this->db()->fetchAll('SELECT id,order_number,customer_name,status FROM `' . $this->db()->table('commerce_orders') . '` WHERE source_package=? AND (order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?) ORDER BY placed_at DESC LIMIT 20', [$owner,$like,$like,$like]) as $row) {
                    $results[]=['type'=>'Order','title'=>$row['order_number'],'detail'=>$row['customer_name'].' · '.$row['status'],'url'=>'/admin/store/orders/view&id='.$row['id']];
                }
            }
            if ($registry->supportsFeature('accounts.customers') && $this->auth()->has($u, 'customers.manage')) {
                $owner = (string) ($registry->feature('accounts.customers')['package'] ?? '');
                foreach ($this->db()->fetchAll('SELECT id,name,email,status FROM `' . $this->db()->table('customer_accounts') . '` WHERE owner_package=? AND (name LIKE ? OR email LIKE ?) ORDER BY created_at DESC LIMIT 20', [$owner,$like,$like]) as $row) {
                    $results[]=['type'=>'Customer','title'=>$row['name'],'detail'=>$row['email'].' · '.$row['status'],'url'=>'/admin/customers'];
                }
            }
            foreach ($registry->search($query) as $result) {
                if (empty($result['capability']) || $this->auth()->has($u, (string) $result['capability'])) {
                    $results[] = [
                        'type' => substr((string) ($result['type'] ?? 'Package'), 0, 40),
                        'title' => substr((string) $result['title'], 0, 190),
                        'detail' => substr((string) ($result['detail'] ?? ''), 0, 300),
                        'url' => (string) $result['url'],
                    ];
                }
            }
        }
        return $this->page($r, 'search/index', compact('query', 'results'), 'Search');
    }
}
