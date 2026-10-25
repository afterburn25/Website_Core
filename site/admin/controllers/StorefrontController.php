<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Branding\StatusPage;
use Core\Commerce\CartService;
use Core\Commerce\CommerceFeatures;
use Core\Commerce\OrderService;
use Core\Commerce\PaymentMethods;
use Core\Commerce\ProductRepository;
use Core\Accounts\CustomerAuth;
use Core\Http\Request;
use Core\Http\Response;
use Core\View\View;

final class StorefrontController extends Controller
{
    public function store(Request $r): Response
    {
        if (!$this->features()->enabled('catalog')) return $this->missing($r);
        return $this->render($r, 'commerce/store', [
            'products' => $this->c->get(ProductRepository::class)->all($this->features()->provider('catalog'), true),
            'storeName' => $this->features()->storeName(), 'cartCount' => $this->cartService()->count(),
        ]);
    }

    public function product(Request $r): Response
    {
        if (!$this->features()->enabled('catalog')) return $this->missing($r);
        $product=$this->c->get(ProductRepository::class)->findActiveBySlug((string)($r->attributes['slug']??''),(string)$this->features()->provider('catalog'));
        if (!$product) return $this->missing($r);
        return $this->render($r,'commerce/product',['product'=>$product,'storeName'=>$this->features()->storeName(),'cartCount'=>$this->cartService()->count()]);
    }

    public function add(Request $r): Response
    {
        if (!$this->features()->enabled('cart') || !$this->features()->enabled('catalog')) return $this->missing($r);
        $error = $this->cartService()->add((int) $r->input('product_id', 0), (int) $r->input('quantity', 1), $this->features()->provider('catalog'));
        $_SESSION['_wc_store_flash'] = $error ?: 'Added to your cart.';
        return Response::redirect($r->routeUrl($error ? '/store' : '/cart'));
    }

    public function cart(Request $r): Response
    {
        if (!$this->features()->enabled('cart') || !$this->features()->enabled('catalog')) return $this->missing($r);
        if ($r->isPost()) {
            $quantities = $r->input('quantity', []);
            $this->cartService()->update(is_array($quantities) ? $quantities : [], $this->features()->provider('catalog'));
            $_SESSION['_wc_store_flash'] = 'Cart updated.';
            return Response::redirect($r->routeUrl('/cart'));
        }
        return $this->render($r, 'commerce/cart', ['cart' => $this->cartService()->summary($this->features()->provider('catalog')), 'cartCount' => $this->cartService()->count()]);
    }

    public function checkout(Request $r): Response
    {
        if (!$this->features()->enabled('catalog') || !$this->features()->enabled('cart')
            || !$this->features()->enabled('checkout') || !$this->features()->enabled('orders')) return $this->missing($r);
        $cart = $this->cartService()->summary($this->features()->provider('catalog'));
        if (!$cart['items']) return Response::redirect($r->routeUrl('/cart'));
        $methods = $this->c->get(PaymentMethods::class)->configured();
        $error = null;
        if (empty($_SESSION['_wc_checkout_nonce'])) $_SESSION['_wc_checkout_nonce'] = bin2hex(random_bytes(24));
        $checkoutNonce = (string) $_SESSION['_wc_checkout_nonce'];
        $customer = $this->customer();
        if ($r->isPost()) {
            $checkout = $r->post;
            if ($customer) $checkout['customer_id'] = (int) $customer['id'];
            if (!is_string($r->input('checkout_nonce')) || !hash_equals($checkoutNonce, (string)$r->input('checkout_nonce'))) {
                $error = 'This checkout was already submitted or expired. Review your cart and try again.';
            } elseif (!$this->c->get(\Core\Security\RateLimiter::class)->attempt('checkout:' . $r->ip() . ':' . strtolower($r->string('email')), 8, 3600)) {
                $error = 'Too many checkout attempts. Please wait and try again.';
            } else [$order, $error] = $this->c->get(OrderService::class)->place(
                $checkout, $cart, (string) $this->features()->provider('orders'),
                $this->features()->fulfillment(), $methods
            );
            if (!empty($order)) {
                unset($_SESSION['_wc_checkout_nonce']);
                $this->cartService()->clear();
                $_SESSION['_wc_last_order'] = $order;
                $selectedMethod = $methods[$order['payment_method']] ?? ['label'=>ucfirst($order['payment_method']),'instructions'=>''];
                $body = "Order {$order['order_number']} was received.\n\nTotal: {$order['currency']} " . number_format($order['total_minor'] / 100, 2)
                    . "\nPayment method: " . $selectedMethod['label']
                    . ($selectedMethod['instructions']!=='' ? "\nPayment instructions: " . $selectedMethod['instructions'] : '')
                    . "\nPayment is pending until confirmed by the business.";
                $mail = $this->c->get(\Core\Mail\SystemMail::class);
                $mail->queue(strtolower($r->string('email')), 'We received order ' . $order['order_number'], $body, 'order-customer:' . $order['order_number']);
                $contact = (string) $this->settings()->get('site.contact_email', '');
                if (filter_var($contact, FILTER_VALIDATE_EMAIL)) $mail->queue($contact, 'New order ' . $order['order_number'], $body, 'order-owner:' . $order['order_number']);
                return Response::redirect($r->routeUrl('/checkout/complete'));
            }
        }
        $input = $r->post;
        if (!$r->isPost() && $customer) $input += ['name'=>$customer['name'],'email'=>$customer['email'],'phone'=>$customer['phone']];
        return $this->render($r, 'commerce/checkout', [
            'cart' => $cart, 'methods' => $methods, 'fulfillment' => $this->features()->fulfillment(),
            'error' => $error, 'input' => $input, 'checkoutNonce'=>$checkoutNonce, 'cartCount' => $this->cartService()->count(),
        ]);
    }

    public function complete(Request $r): Response
    {
        if (!$this->features()->enabled('checkout')) return $this->missing($r);
        $order = $_SESSION['_wc_last_order'] ?? null;
        unset($_SESSION['_wc_last_order']);
        if (!is_array($order)) return Response::redirect($r->routeUrl('/store'));
        $methods = $this->c->get(PaymentMethods::class)->configured();
        return $this->render($r, 'commerce/complete', [
            'order' => $order, 'method' => $methods[$order['payment_method']] ?? ['label' => 'Manual payment', 'instructions' => ''],
            'cartCount' => 0,
        ]);
    }

    private function render(Request $r, string $view, array $data): Response
    {
        $flash = $_SESSION['_wc_store_flash'] ?? null; unset($_SESSION['_wc_store_flash']);
        $accountAvailable = $this->c->get(\Core\Modules\ModuleRegistry::class)->supportsFeature('accounts.customers');
        $data += ['basePath' => $r->routeBase(), 'siteBasePath' => rtrim($r->basePath, '/'),
            'siteName' => (string) $this->settings()->get('site.name', 'Website'), 'csrf' => $this->csrf(), 'flash' => $flash,
            'commerceAvailable' => true, 'accountAvailable' => $accountAvailable, 'customer' => $accountAvailable ? $this->customer() : null];
        return Response::html($this->c->get(View::class)->renderInLayout('admin:' . $view, 'admin:commerce/store-layout', $data));
    }

    private function features(): CommerceFeatures { return $this->c->get(CommerceFeatures::class); }
    private function cartService(): CartService { return $this->c->get(CartService::class); }
    private function customer(): ?array
    {
        $feature = $this->c->get(\Core\Modules\ModuleRegistry::class)->feature('accounts.customers');
        return $feature ? $this->c->get(CustomerAuth::class)->user((string)$feature['package']) : null;
    }
    private function missing(Request $r): Response
    {
        return Response::html(StatusPage::render(404, 'Store not available', 'This website does not currently offer online ordering.', $r->routeBase(), (string) $this->settings()->get('site.name', 'Website')), 404);
    }
}
