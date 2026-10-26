<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Accounts\CustomerAuth;
use Core\Commerce\CartService;
use Core\Commerce\OrderService;
use Core\Commerce\PaymentMethods;
use Core\Commerce\ProductRepository;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Events\Dispatcher;
use Core\Mail\SystemMail;
use Core\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class CommerceWorkflowTest extends TestCase
{
    private Connection $db;
    private SettingsRepository $settings;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $this->db = Connection::sqlite(':memory:', 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->settings = new SettingsRepository($this->db);
        $_SESSION = [];
    }

    public function testProductCartAndManualPaymentOrderFlow(): void
    {
        $products = new ProductRepository($this->db);
        [$id,$error] = $products->save([
            'name'=>'Chicken and Andouille Gumbo','sku'=>'GUMBO-32','price'=>'24.50','currency'=>'USD',
            'track_inventory'=>'1','stock_quantity'=>'4','active'=>'1','description'=>'Quart serving',
        ], 'cajunwebpro/catering');
        self::assertNull($error); self::assertNotNull($id);

        $cart = new CartService($products);
        self::assertNull($cart->add((int)$id, 2, 'cajunwebpro/catering'));
        self::assertSame(4900, $cart->summary('cajunwebpro/catering')['subtotal']);

        $payments = new PaymentMethods($this->settings);
        $payments->save(['venmo'=>['enabled'=>'1','instructions'=>'@CateringCo']]);
        self::assertSame(['venmo'], array_keys($payments->configured()));
        self::assertArrayNotHasKey('cash', PaymentMethods::CATALOG);

        [$order,$orderError] = (new OrderService($this->db,new Dispatcher()))->place([
            'name'=>'Test Customer','email'=>'customer@example.test','fulfillment'=>'pickup','payment_method'=>'venmo',
        ], $cart->summary('cajunwebpro/catering'), 'cajunwebpro/catering', ['pickup'], $payments->configured());
        self::assertNull($orderError); self::assertNotNull($order);
        self::assertSame('pending', $this->db->fetchColumn('SELECT payment_status FROM `wc_commerce_orders` WHERE id=?', [$order['id']]));
        self::assertSame(2, (int)$this->db->fetchColumn('SELECT stock_quantity FROM `wc_commerce_products` WHERE id=?', [$id]));
    }

    public function testCustomerAccountIsPackageScoped(): void
    {
        $auth = new CustomerAuth($this->db, new SystemMail($this->db, $this->settings));
        [$user,$error] = $auth->register('cajunwebpro/catering', [
            'name'=>'Jane Customer','email'=>'Jane@example.test','phone'=>'555-0100','password'=>'StrongPassword7',
        ]);
        self::assertNull($error); self::assertSame('jane@example.test',$user['email']);
        self::assertNotNull($auth->user('cajunwebpro/catering'));
        self::assertNull($auth->user('cajunwebpro/landscaping'));
        $auth->logout();
        self::assertNull($auth->login('cajunwebpro/catering','jane@example.test','StrongPassword7'));
    }
}
