<?php
declare(strict_types=1);

namespace Core\Commerce;

final class CartService
{
    private const SESSION_KEY = '_wc_commerce_cart';

    public function __construct(private ProductRepository $products) {}

    public function add(int $productId, int $quantity = 1, ?string $ownerPackage = null): ?string
    {
        $product = $this->products->findActive($productId, $ownerPackage);
        if (!$product) return 'This product is not available.';
        $quantity = max(1, min(99, $quantity));
        $cart = $this->raw();
        $next = min(99, ($cart[$productId] ?? 0) + $quantity);
        if ((int) $product['track_inventory'] === 1 && $next > (int) $product['stock_quantity']) {
            return 'Only ' . (int) $product['stock_quantity'] . ' are currently available.';
        }
        $cart[$productId] = $next;
        $_SESSION[self::SESSION_KEY] = $cart;
        return null;
    }

    public function update(array $quantities, ?string $ownerPackage = null): void
    {
        $cart = [];
        foreach ($quantities as $id => $quantity) {
            $id = (int) $id; $quantity = max(0, min(99, (int) $quantity));
            $product = $this->products->findActive($id, $ownerPackage);
            if (!$product || $quantity < 1) continue;
            if ((int) $product['track_inventory'] === 1) $quantity = min($quantity, (int) $product['stock_quantity']);
            if ($quantity > 0) $cart[$id] = $quantity;
        }
        $_SESSION[self::SESSION_KEY] = $cart;
    }

    public function clear(): void { unset($_SESSION[self::SESSION_KEY]); }
    public function count(): int { return array_sum($this->raw()); }

    public function summary(?string $ownerPackage = null): array
    {
        $items = []; $subtotal = 0; $currency = 'USD';
        foreach ($this->raw() as $id => $quantity) {
            $product = $this->products->findActive((int) $id, $ownerPackage);
            if (!$product) continue;
            $currency = (string) $product['currency'];
            $line = (int) $product['price_minor'] * $quantity;
            $subtotal += $line;
            $items[] = ['product' => $product, 'quantity' => $quantity, 'line_total_minor' => $line];
        }
        return compact('items', 'subtotal', 'currency') + ['count' => array_sum(array_column($items, 'quantity'))];
    }

    private function raw(): array
    {
        $cart = $_SESSION[self::SESSION_KEY] ?? [];
        return is_array($cart) ? array_map('intval', $cart) : [];
    }
}
