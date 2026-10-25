<?php
declare(strict_types=1);

namespace Core\Commerce;

use Core\Database\Connection;
use Core\Events\Dispatcher;

final class OrderService
{
    public function __construct(
        private Connection $db,
        private Dispatcher $events,
        private ?CheckoutValidation $checkoutValidation = null,
    ) {}

    /** @return array{0:?array,1:?string,2:array{outcome:string,errors:array,message:string,snapshot:array,elapsed_ms:int}} */
    public function place(array $checkout, array $cart, string $sourcePackage, array $allowedFulfillment, array $paymentMethods): array
    {
        $neutralValidation = ['outcome' => 'ok', 'errors' => [], 'message' => '', 'snapshot' => [], 'elapsed_ms' => 0];
        $name = trim(strip_tags((string) ($checkout['name'] ?? '')));
        $email = strtolower(trim((string) ($checkout['email'] ?? '')));
        $phone = trim(strip_tags((string) ($checkout['phone'] ?? '')));
        $fulfillment = (string) ($checkout['fulfillment'] ?? '');
        $payment = (string) ($checkout['payment_method'] ?? '');
        if ($name === '' || mb_strlen($name) > 190) return [null, 'Enter your name.', $neutralValidation];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) return [null, 'Enter a valid email address.', $neutralValidation];
        if (!in_array($fulfillment, $allowedFulfillment, true)) return [null, 'Select an available fulfillment method.', $neutralValidation];
        if (!isset($paymentMethods[$payment])) return [null, 'Select an available payment method.', $neutralValidation];
        if (empty($cart['items'])) return [null, 'Your cart is empty.', $neutralValidation];
        $sourcePackage = trim($sourcePackage);

        $validationContext = ['checkout' => $checkout, 'cart' => $cart];
        $validation = $this->checkoutValidation?->validate($sourcePackage, $validationContext, false)
            ?? $neutralValidation;
        if ($validation['outcome'] !== 'ok') {
            $this->checkoutValidation?->record($sourcePackage, $validationContext, $validation);
            return [null, $validation['message'] ?: (string) ($validation['errors']['_form'] ?? 'Checkout requirements were not met.'), $validation];
        }
        try {
            $order = $this->db->transaction(function () use ($checkout, $cart, $sourcePackage, $name, $email, $phone, $fulfillment, $payment, $validationContext, $neutralValidation, &$validation): array {
                // Module rules are rerun inside the order transaction. Client
                // results and the earlier preflight are never trusted.
                $validation = $this->checkoutValidation?->validate($sourcePackage, $validationContext, true)
                    ?? $neutralValidation;
                $validationId = $this->checkoutValidation?->record($sourcePackage, $validationContext, $validation);
                if ($validation['outcome'] !== 'ok') {
                    throw new \DomainException($validation['message'] ?: (string) ($validation['errors']['_form'] ?? 'Checkout requirements were not met.'));
                }
                foreach ($cart['items'] as $line) {
                    $p = $line['product'];
                    if ((int) $p['track_inventory'] === 1) {
                        $fresh = $this->db->fetch('SELECT stock_quantity FROM `' . $this->db->table('commerce_products') . '` WHERE id = ?', [(int) $p['id']]);
                        if (!$fresh || (int) $fresh['stock_quantity'] < (int) $line['quantity']) {
                            throw new \DomainException($p['name'] . ' no longer has enough stock.');
                        }
                    }
                }
                $number = 'ORD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $now = date('Y-m-d H:i:s');
                $customerId = !empty($checkout['customer_id']) ? (int) $checkout['customer_id'] : null;
                if ($customerId !== null) {
                    $belongs = $this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table('customer_accounts') . '` WHERE id=? AND owner_package=? AND email=? AND status=?', [$customerId,$sourcePackage,$email,'active']);
                    if (!(bool)$belongs) $customerId = null;
                }
                $address = [
                    'line1' => trim(strip_tags((string) ($checkout['address_line1'] ?? ''))),
                    'line2' => trim(strip_tags((string) ($checkout['address_line2'] ?? ''))),
                    'city' => trim(strip_tags((string) ($checkout['city'] ?? ''))),
                    'region' => trim(strip_tags((string) ($checkout['region'] ?? ''))),
                    'postal_code' => trim(strip_tags((string) ($checkout['postal_code'] ?? ''))),
                ];
                if (in_array($fulfillment, ['delivery', 'shipping'], true)
                    && ($address['line1'] === '' || $address['city'] === '' || $address['region'] === '' || $address['postal_code'] === '')) {
                    throw new \DomainException('Enter the complete delivery or shipping address.');
                }
                $id = (int) $this->db->insert('commerce_orders', [
                    'order_number' => $number, 'source_package' => $sourcePackage,
                    'customer_id' => $customerId, 'status' => 'new',
                    'payment_status' => 'pending', 'payment_method' => $payment, 'payment_reference' => null,
                    'fulfillment_method' => $fulfillment, 'customer_name' => $name, 'customer_email' => $email,
                    'customer_phone' => mb_substr($phone, 0, 60), 'address_json' => json_encode($address, JSON_UNESCAPED_SLASHES),
                    'customer_notes' => mb_substr(trim(strip_tags((string) ($checkout['notes'] ?? ''))), 0, 2000),
                    'internal_notes' => null, 'inventory_restored' => 0, 'currency' => $cart['currency'], 'subtotal_minor' => $cart['subtotal'],
                    'shipping_minor' => 0, 'tax_minor' => 0, 'total_minor' => $cart['subtotal'],
                    'placed_at' => $now, 'updated_at' => $now, 'completed_at' => null,
                ]);
                foreach ($cart['items'] as $line) {
                    $p = $line['product'];
                    $this->db->insert('commerce_order_items', [
                        'order_id' => $id, 'product_id' => (int) $p['id'], 'sku' => $p['sku'], 'name' => $p['name'],
                        'unit_price_minor' => (int) $p['price_minor'], 'quantity' => (int) $line['quantity'],
                        'line_total_minor' => (int) $line['line_total_minor'],
                        'product_snapshot_json' => json_encode(['owner_package' => $p['owner_package'], 'slug' => $p['slug']], JSON_UNESCAPED_SLASHES),
                    ]);
                    if ((int) $p['track_inventory'] === 1) {
                        $updated = $this->db->run('UPDATE `' . $this->db->table('commerce_products') . '` SET stock_quantity = stock_quantity - ?, updated_at = ? WHERE id = ? AND stock_quantity >= ?', [(int) $line['quantity'], $now, (int) $p['id'], (int) $line['quantity']])->rowCount();
                        if ($updated !== 1) throw new \DomainException($p['name'] . ' no longer has enough stock.');
                    }
                }
                if ($validationId !== null) $this->checkoutValidation?->attachToOrder($validationId, $id, $sourcePackage);
                return ['id' => $id, 'order_number' => $number, 'payment_method' => $payment, 'total_minor' => $cart['subtotal'], 'currency' => $cart['currency']];
            });
        } catch (\DomainException $e) {
            if ($validation['outcome'] !== 'ok') {
                $this->checkoutValidation?->record($sourcePackage, $validationContext, $validation);
            }
            return [null, $e->getMessage(), $validation];
        }
        $this->events->dispatch('commerce.order.placed', $order + [
            'validation_outcome' => $validation['outcome'],
        ]);
        return [$order, null, $validation];
    }

    public function all(string $sourcePackage, string $status = ''): array
    {
        $sql = 'SELECT * FROM `' . $this->db->table('commerce_orders') . '` WHERE source_package = ?';
        $params = [$sourcePackage];
        if ($status !== '') { $sql .= ' AND status = ?'; $params[] = $status; }
        return $this->db->fetchAll($sql . ' ORDER BY placed_at DESC, id DESC', $params);
    }

    public function find(int $id, string $sourcePackage): ?array
    {
        $order = $this->db->fetch('SELECT * FROM `' . $this->db->table('commerce_orders') . '` WHERE id = ? AND source_package = ?', [$id, $sourcePackage]);
        if (!$order) return null;
        $order['items'] = $this->db->fetchAll('SELECT * FROM `' . $this->db->table('commerce_order_items') . '` WHERE order_id = ? ORDER BY id', [$id]);
        return $order;
    }

    public function update(int $id, string $sourcePackage, array $input): bool
    {
        $status = (string) ($input['status'] ?? '');
        $payment = (string) ($input['payment_status'] ?? '');
        if (!in_array($status, ['new', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'], true)
            || !in_array($payment, ['pending', 'paid', 'refunded', 'void'], true)) return false;
        $current = $this->find($id, $sourcePackage);
        if (!$current || ($current['status'] === 'cancelled' && $status !== 'cancelled')) return false;
        return $this->db->transaction(function () use ($id,$sourcePackage,$input,$status,$payment,$current): bool {
            $restore = $status === 'cancelled' && !(int)$current['inventory_restored'];
            if ($restore) {
                foreach ($current['items'] as $item) {
                    if (empty($item['product_id'])) continue;
                    $this->db->run('UPDATE `' . $this->db->table('commerce_products') . '` SET stock_quantity=stock_quantity+?,updated_at=? WHERE id=? AND track_inventory=1', [(int)$item['quantity'],date('Y-m-d H:i:s'),(int)$item['product_id']]);
                }
            }
            return $this->db->update('commerce_orders', [
                'status' => $status, 'payment_status' => $payment,
                'payment_reference' => mb_substr(trim(strip_tags((string) ($input['payment_reference'] ?? ''))), 0, 190),
                'internal_notes' => mb_substr(trim(strip_tags((string) ($input['internal_notes'] ?? ''))), 0, 3000),
                'inventory_restored' => $restore ? 1 : (int)$current['inventory_restored'],
                'updated_at' => date('Y-m-d H:i:s'),
                'completed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
            ], 'id = ? AND source_package = ?', [$id, $sourcePackage]) > 0;
        });
    }

    public function forCustomer(string $sourcePackage, int $customerId, string $email): array
    {
        return $this->db->fetchAll('SELECT * FROM `' . $this->db->table('commerce_orders')
            . '` WHERE source_package=? AND customer_id=? ORDER BY placed_at DESC',
            [$sourcePackage, $customerId]);
    }
}
