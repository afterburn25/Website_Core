<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Commerce\CommerceFeatures;
use Core\Commerce\OrderService;
use Core\Commerce\PaymentMethods;
use Core\Commerce\ProductRepository;
use Core\Http\Request;
use Core\Http\Response;

final class CommerceController extends Controller
{
    public function products(Request $r): Response
    {
        if (!$this->available('catalog')) return $this->unavailable();
        if (!$this->allowed('commerce.manage')) return $this->forbidden();
        $owner = $this->features()->provider('catalog');
        if ($r->isPost()) {
            $id = (int) $r->input('id', 0);
            $this->productsRepo()->setActive($id, (string) $owner, $r->string('action') === 'activate');
            $this->audit('commerce.product_status', ['id' => $id, 'active' => $r->string('action') === 'activate']);
            $this->flash('ok', 'Product availability updated.');
            return $this->redirect($r, '/admin/store/products');
        }
        return $this->page($r, 'commerce/products', [
            'products' => $this->productsRepo()->all($owner), 'currency' => $this->features()->currency(),
        ], 'Products');
    }

    public function productEdit(Request $r): Response
    {
        if (!$this->available('catalog')) return $this->unavailable();
        if (!$this->allowed('commerce.manage')) return $this->forbidden();
        $owner = (string) $this->features()->provider('catalog');
        $id = (int) $r->input('id', 0);
        $product = $id ? $this->productsRepo()->find($id) : null;
        if ($product && $product['owner_package'] !== $owner) return $this->unavailable();
        $error = null;
        if ($r->isPost()) {
            [$savedId, $error] = $this->productsRepo()->save($r->post, $owner, $id ?: null);
            if ($error === null) {
                $this->audit('commerce.product_saved', ['id' => $savedId, 'package' => $owner]);
                $this->flash('ok', $id ? 'Product updated.' : 'Product created.');
                return $this->redirect($r, '/admin/store/products');
            }
            $product = $r->post + ['id' => $id];
        }
        return $this->page($r, 'commerce/product-edit', [
            'product' => $product, 'error' => $error, 'currency' => $this->features()->currency(),
        ], 'Product editor');
    }

    public function orders(Request $r): Response
    {
        if (!$this->available('orders')) return $this->unavailable();
        if (!$this->allowed('orders.manage')) return $this->forbidden();
        $status = $r->string('status');
        if (!in_array($status, ['', 'new', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'], true)) $status = '';
        return $this->page($r, 'commerce/orders', [
            'orders' => $this->ordersService()->all((string) $this->features()->provider('orders'), $status),
            'status' => $status,
        ], 'Orders');
    }

    public function exportOrders(Request $r): Response
    {
        if (!$this->available('orders')) return $this->unavailable();
        if (!$this->allowed('orders.manage')) return $this->forbidden();
        $rows=$this->ordersService()->all((string)$this->features()->provider('orders'));
        $stream=fopen('php://temp','w+');
        fputcsv($stream,['Order','Placed','Customer','Email','Phone','Fulfillment','Status','Payment method','Payment status','Currency','Subtotal','Shipping','Tax','Total']);
        foreach($rows as $o) fputcsv($stream,[$o['order_number'],$o['placed_at'],$o['customer_name'],$o['customer_email'],$o['customer_phone'],$o['fulfillment_method'],$o['status'],$o['payment_method'],$o['payment_status'],$o['currency'],number_format((int)$o['subtotal_minor']/100,2,'.',''),number_format((int)$o['shipping_minor']/100,2,'.',''),number_format((int)$o['tax_minor']/100,2,'.',''),number_format((int)$o['total_minor']/100,2,'.','')]);
        rewind($stream); $csv=(string)stream_get_contents($stream); fclose($stream);
        $this->audit('commerce.orders_exported',['count'=>count($rows)]);
        return Response::text($csv)->header('Content-Type','text/csv; charset=utf-8')
            ->header('Content-Disposition','attachment; filename="orders-'.date('Y-m-d').'.csv"');
    }

    public function orderView(Request $r): Response
    {
        if (!$this->available('orders')) return $this->unavailable();
        if (!$this->allowed('orders.manage')) return $this->forbidden();
        $id = (int) $r->input('id', 0);
        $owner = (string) $this->features()->provider('orders');
        $order = $this->ordersService()->find($id, $owner);
        if (!$order) return $this->unavailable();
        if ($r->isPost()) {
            if ($this->ordersService()->update($id, $owner, $r->post)) {
                $this->audit('commerce.order_updated', ['id' => $id, 'number' => $order['order_number']]);
                $this->flash('ok', 'Order updated.');
                return $this->redirect($r, '/admin/store/orders/view?id=' . $id);
            }
            $this->flash('error', 'Choose valid order and payment statuses.');
        }
        return $this->page($r, 'commerce/order-view', ['order' => $order], 'Order details');
    }

    public function settings(Request $r): Response
    {
        if (!$this->available('checkout')) return $this->unavailable();
        if (!$this->allowed('commerce.manage')) return $this->forbidden();
        $payments = $this->c->get(PaymentMethods::class);
        if ($r->isPost()) {
            $input = $r->input('payments', []);
            $payments->save(is_array($input) ? $input : []);
            $this->audit('commerce.payment_methods_updated');
            $this->flash('ok', 'Checkout payment methods updated.');
            return $this->redirect($r, '/admin/store/settings');
        }
        $saved = $this->settings()->get('commerce.payment_methods', []);
        return $this->page($r, 'commerce/settings', [
            'catalog' => PaymentMethods::CATALOG, 'saved' => is_array($saved) ? $saved : [],
            'fulfillment' => $this->features()->fulfillment(),
        ], 'Store settings');
    }

    private function available(string $surface): bool { return $this->features()->enabled($surface); }
    private function allowed(string $cap): bool { return $this->user() !== null && $this->auth()->has($this->user(), $cap); }
    private function features(): CommerceFeatures { return $this->c->get(CommerceFeatures::class); }
    private function productsRepo(): ProductRepository { return $this->c->get(ProductRepository::class); }
    private function ordersService(): OrderService { return $this->c->get(OrderService::class); }
    private function unavailable(): Response
    {
        $r=Request::capture();
        return Response::html(\Core\Branding\StatusPage::render(404,'Store feature not available','The active industry module does not provide this part of the store.',$r->routeBase(),(string)$this->settings()->get('site.name','Website')),404);
    }
}
