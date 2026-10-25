<?php
declare(strict_types=1);

namespace Core\Commerce;

use Core\Database\Connection;

/** Package-isolated checkout rules with fail-closed results and immutable evidence. */
final class CheckoutValidation
{
    /** @var array<string,array{validator:callable,timeout:float}> */
    private array $validators = [];

    public function __construct(private Connection $db) {}

    public function register(string $packageId, callable $validator, array $options = []): void
    {
        if (isset($this->validators[$packageId])) throw new \InvalidArgumentException('Checkout validator is already registered by this package.');
        $timeout = (float) ($options['timeout_seconds'] ?? 2.0);
        if ($timeout < 0.1 || $timeout > 5.0) throw new \InvalidArgumentException('Checkout validator timeout must be between 0.1 and 5 seconds.');
        foreach (array_keys($options) as $key) {
            if ($key !== 'timeout_seconds') throw new \InvalidArgumentException('Unsupported checkout validator option: ' . $key);
        }
        $this->validators[$packageId] = ['validator' => $validator, 'timeout' => $timeout];
    }

    public function registered(string $packageId): bool
    {
        return isset($this->validators[$packageId]);
    }

    /** @return array{outcome:string,errors:array<string,string>,message:string,snapshot:array,elapsed_ms:int} */
    public function validate(string $packageId, array $context, bool $final): array
    {
        $registration = $this->validators[$packageId] ?? null;
        if ($registration === null) return $this->result('ok', [], '', [], 0);
        $payload = $this->payload($packageId, $context, $final);
        $start = microtime(true);
        try {
            $raw = ($registration['validator'])($payload);
        } catch (\Throwable $e) {
            error_log('[website-core] checkout validator failed for ' . $packageId . ': ' . $e->getMessage());
            return $this->result('errors', ['_form' => 'Checkout rules could not be verified. Please try again or request a quote.'], '', ['failure' => 'exception'], (int) round((microtime(true) - $start) * 1000));
        }
        $elapsed = (int) round((microtime(true) - $start) * 1000);
        if ($elapsed > (int) round($registration['timeout'] * 1000)) {
            return $this->result('errors', ['_form' => 'Checkout verification timed out. Please try again or request a quote.'], '', ['failure' => 'timeout'], $elapsed);
        }
        if ($raw === null || $raw === true || $raw === []) return $this->result('ok', [], '', [], $elapsed);
        if (!is_array($raw)) {
            return $this->result('errors', ['_form' => $this->message((string) $raw)], '', [], $elapsed);
        }
        $outcome = (string) ($raw['outcome'] ?? $raw['status'] ?? 'ok');
        if (!in_array($outcome, ['ok', 'errors', 'manual_review', 'quote_only'], true)) $outcome = 'errors';
        $errors = [];
        foreach (($raw['errors'] ?? []) as $field => $message) {
            $field = (string) $field;
            if (!preg_match('/^(?:_form|[a-z][a-z0-9_]{0,79})$/', $field)) continue;
            $message = $this->message((string) $message);
            if ($message !== '') $errors[$field] = $message;
        }
        $message = $this->message((string) ($raw['message'] ?? ''));
        if ($outcome !== 'ok' && $errors === [] && $message === '') {
            $message = $outcome === 'quote_only'
                ? 'This order requires a quotation before checkout.'
                : ($outcome === 'manual_review'
                    ? 'This order requires staff review before checkout.'
                    : 'Checkout requirements were not met.');
        }
        $snapshot = $this->bounded($raw['snapshot'] ?? []);
        return $this->result($outcome, $errors, $message, $snapshot, $elapsed);
    }

    public function record(string $packageId, array $context, array $result, ?int $orderId = null): int
    {
        $inputHash = hash('sha256', json_encode($this->payload($packageId, $context, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return (int) $this->db->insert('commerce_validation_snapshots', [
            'owner_package' => $packageId, 'order_id' => $orderId,
            'input_hash' => $inputHash, 'outcome' => (string) $result['outcome'],
            'errors_json' => json_encode($result['errors'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'snapshot_json' => json_encode($result['snapshot'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'final_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function attachToOrder(int $validationId, int $orderId, string $packageId): void
    {
        $this->db->update('commerce_validation_snapshots', ['order_id' => $orderId], 'id=? AND owner_package=? AND order_id IS NULL', [$validationId, $packageId]);
    }

    private function payload(string $packageId, array $context, bool $final): array
    {
        $checkout = is_array($context['checkout'] ?? null) ? $context['checkout'] : [];
        $cart = is_array($context['cart'] ?? null) ? $context['cart'] : [];
        $lines = [];
        foreach (array_slice((array) ($cart['items'] ?? []), 0, 100) as $line) {
            $product = is_array($line['product'] ?? null) ? $line['product'] : [];
            $lines[] = [
                'product_id' => (int) ($product['id'] ?? 0), 'sku' => mb_substr((string) ($product['sku'] ?? ''), 0, 120),
                'quantity' => max(0, (int) ($line['quantity'] ?? 0)),
                'unit_price_minor' => (int) ($product['price_minor'] ?? 0),
                'line_total_minor' => (int) ($line['line_total_minor'] ?? 0),
                'owner_package' => (string) ($product['owner_package'] ?? ''),
            ];
        }
        return [
            'package' => $packageId, 'final' => $final,
            'requested_date' => mb_substr(trim((string) ($checkout['requested_date'] ?? '')), 0, 20),
            'requested_time' => mb_substr(trim((string) ($checkout['requested_time'] ?? '')), 0, 20),
            'fulfillment' => mb_substr(trim((string) ($checkout['fulfillment'] ?? '')), 0, 30),
            'address' => [
                'line1' => mb_substr(trim((string) ($checkout['address_line1'] ?? '')), 0, 190),
                'line2' => mb_substr(trim((string) ($checkout['address_line2'] ?? '')), 0, 190),
                'city' => mb_substr(trim((string) ($checkout['city'] ?? '')), 0, 120),
                'region' => mb_substr(trim((string) ($checkout['region'] ?? '')), 0, 120),
                'postal_code' => mb_substr(trim((string) ($checkout['postal_code'] ?? '')), 0, 30),
                'country' => mb_substr(trim((string) ($checkout['country'] ?? '')), 0, 120),
            ],
            'customer' => [
                'id' => isset($checkout['customer_id']) ? (int) $checkout['customer_id'] : null,
                'authenticated' => !empty($checkout['customer_id']),
            ],
            'payment_method' => mb_substr(trim((string) ($checkout['payment_method'] ?? '')), 0, 40),
            'cart' => ['items' => $lines, 'subtotal_minor' => (int) ($cart['subtotal'] ?? 0), 'currency' => (string) ($cart['currency'] ?? 'USD')],
        ];
    }

    private function result(string $outcome, array $errors, string $message, array $snapshot, int $elapsed): array
    {
        return compact('outcome', 'errors', 'message', 'snapshot') + ['elapsed_ms' => $elapsed];
    }

    private function message(string $value): string
    {
        return mb_substr(trim(strip_tags($value)), 0, 500);
    }

    private function bounded(mixed $value, int $depth = 0): array
    {
        if (!is_array($value) || $depth > 4) return [];
        $out = [];
        foreach (array_slice($value, 0, 50, true) as $key => $item) {
            $key = mb_substr((string) $key, 0, 80);
            $normalizedKey = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $key));
            if (preg_match('/(?:^|_)(?:email|phone|address|customer|name|contact|recipient|notes?|line1|line2|city|region|postal_code)(?:_|$)/', $normalizedKey)) {
                continue;
            }
            if (is_array($item)) $out[$key] = $this->bounded($item, $depth + 1);
            elseif (is_bool($item) || is_int($item) || is_float($item) || $item === null) $out[$key] = $item;
            elseif (is_string($item)) {
                $item = mb_substr(trim(strip_tags($item)), 0, 500);
                if ($item !== '' && !filter_var($item, FILTER_VALIDATE_EMAIL)) $out[$key] = $item;
            }
        }
        return $out;
    }
}
