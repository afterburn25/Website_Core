<?php
declare(strict_types=1);

namespace Core\Leads;

use Core\Database\Connection;

final class QuoteService
{
    public const STATUSES = ['draft', 'sent', 'accepted', 'declined', 'expired', 'cancelled'];

    public function __construct(private Connection $db) {}

    public function all(string $ownerPackage): array
    {
        return $this->db->fetchAll('SELECT * FROM `' . $this->db->table('crm_quotes') . '` WHERE owner_package=? ORDER BY created_at DESC', [$ownerPackage]);
    }

    public function find(int $id, string $ownerPackage): ?array
    {
        $quote = $this->db->fetch('SELECT * FROM `' . $this->db->table('crm_quotes') . '` WHERE id=? AND owner_package=?', [$id, $ownerPackage]);
        if (!$quote) return null;
        $quote['items'] = $this->db->fetchAll('SELECT * FROM `' . $this->db->table('crm_quote_items') . '` WHERE quote_id=? ORDER BY sort_order,id', [$id]);
        return $quote;
    }

    /** @return array{0:?int,1:?string,2:?string} id,error,plain acceptance token */
    public function save(string $ownerPackage, array $input, array $items, ?int $id = null): array
    {
        $name = mb_substr(trim(strip_tags((string) ($input['customer_name'] ?? ''))), 0, 190);
        $email = strtolower(trim((string) ($input['customer_email'] ?? '')));
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'USD')));
        $status = (string) ($input['status'] ?? 'draft');
        if ($name === '') return [null, 'Enter the customer name.', null];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [null, 'Enter a valid customer email.', null];
        if (!preg_match('/^[A-Z]{3}$/', $currency)) return [null, 'Currency must be a three-letter ISO code.', null];
        if (!in_array($status, self::STATUSES, true)) return [null, 'Select a valid quote status.', null];
        $normalized = [];
        foreach ($items as $order => $item) {
            if (!is_array($item)) continue;
            $description = mb_substr(trim(strip_tags((string) ($item['description'] ?? ''))), 0, 500);
            $quantity = max(1, min(99999, (int) ($item['quantity'] ?? 1)));
            $price = trim((string) ($item['unit_price'] ?? ''));
            if ($description === '' && $price === '') continue;
            if ($description === '' || !preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $price)) return [null, 'Every quote item requires a description and valid price.', null];
            $unit = (int) round((float) $price * 100);
            $normalized[] = ['description'=>$description,'quantity'=>$quantity,'unit_price_minor'=>$unit,'line_total_minor'=>$unit*$quantity,'sort_order'=>$order];
        }
        if ($normalized === []) return [null, 'Add at least one quote item.', null];
        $subtotal = array_sum(array_column($normalized, 'line_total_minor'));
        $discount = $this->money($input['discount'] ?? '0');
        $tax = $this->money($input['tax'] ?? '0');
        if ($discount === null || $tax === null) return [null, 'Discount and tax must be valid non-negative amounts.', null];
        $total = max(0, $subtotal - $discount + $tax);
        $validUntil = trim((string) ($input['valid_until'] ?? ''));
        if ($validUntil !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil)) return [null, 'Valid-until date must use YYYY-MM-DD.', null];
        $token = null; $now = date('Y-m-d H:i:s');
        $existing = $id ? $this->find($id, $ownerPackage) : null;
        if ($id !== null && !$existing) return [null, 'Quote was not found for this module.', null];
        $inquiryId = !empty($input['inquiry_id']) ? (int) $input['inquiry_id'] : null;
        if ($inquiryId !== null && !(bool)$this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table('crm_inquiries') . '` WHERE id=? AND owner_package=?',[$inquiryId,$ownerPackage])) {
            return [null, 'The linked inquiry was not found for this module.', null];
        }
        $data = [
            'inquiry_id' => $inquiryId,
            'status'=>$status, 'customer_name'=>$name, 'customer_email'=>$email,
            'customer_phone'=>mb_substr(trim((string)($input['customer_phone']??'')),0,60) ?: null,
            'currency'=>$currency,'subtotal_minor'=>$subtotal,'discount_minor'=>$discount,'tax_minor'=>$tax,'total_minor'=>$total,
            'valid_until'=>$validUntil !== '' ? $validUntil : null,
            'customer_notes'=>mb_substr(trim(strip_tags((string)($input['customer_notes']??''))),0,5000),
            'internal_notes'=>mb_substr(trim(strip_tags((string)($input['internal_notes']??''))),0,5000),
            'updated_at'=>$now,
        ];
        if ($status === 'sent' && (!$existing || empty($existing['acceptance_token_hash']))) {
            $token = bin2hex(random_bytes(24)); $data['acceptance_token_hash'] = hash('sha256', $token);
        }
        $id = $this->db->transaction(function () use ($id, $existing, $ownerPackage, $data, $normalized, $now): int {
            if ($id && $existing) $this->db->update('crm_quotes', $data, 'id=? AND owner_package=?', [$id,$ownerPackage]);
            else {
                $number = 'Q-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                $id = (int)$this->db->insert('crm_quotes', $data + ['owner_package'=>$ownerPackage,'quote_number'=>$number,'acceptance_token_hash'=>null,'accepted_at'=>null,'created_at'=>$now]);
            }
            $this->db->run('DELETE FROM `' . $this->db->table('crm_quote_items') . '` WHERE quote_id=?', [$id]);
            foreach ($normalized as $item) $this->db->insert('crm_quote_items', ['quote_id'=>$id] + $item);
            return $id;
        });
        return [$id, null, $token];
    }

    public function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
        $row = $this->db->fetch('SELECT id,owner_package FROM `' . $this->db->table('crm_quotes') . '` WHERE acceptance_token_hash=? AND status=\'sent\'', [hash('sha256',$token)]);
        if (!$row) return null;
        $quote=$this->find((int)$row['id'], (string)$row['owner_package']);
        if ($quote && !empty($quote['valid_until']) && $quote['valid_until'] < date('Y-m-d')) {
            $this->db->update('crm_quotes',['status'=>'expired','acceptance_token_hash'=>null,'updated_at'=>date('Y-m-d H:i:s')],'id=?',[(int)$quote['id']]);
            return null;
        }
        return $quote;
    }

    public function regenerateResponseToken(int $id, string $ownerPackage): ?string
    {
        if (!$this->find($id, $ownerPackage)) return null;
        $token=bin2hex(random_bytes(24));
        $updated=$this->db->update('crm_quotes', [
            'status'=>'sent','acceptance_token_hash'=>hash('sha256',$token),
            'accepted_at'=>null,'updated_at'=>date('Y-m-d H:i:s'),
        ], 'id=? AND owner_package=?', [$id,$ownerPackage]);
        return $updated>0?$token:null;
    }

    public function respond(string $token, bool $accept): bool
    {
        $quote = $this->byToken($token); if (!$quote) return false;
        if (!empty($quote['valid_until']) && $quote['valid_until'] < date('Y-m-d')) {
            $this->db->update('crm_quotes', ['status'=>'expired','updated_at'=>date('Y-m-d H:i:s')], 'id=?', [(int)$quote['id']]);
            return false;
        }
        return $this->db->update('crm_quotes', [
            'status'=>$accept?'accepted':'declined', 'accepted_at'=>$accept?date('Y-m-d H:i:s'):null,
            'acceptance_token_hash'=>null, 'updated_at'=>date('Y-m-d H:i:s'),
        ], 'id=? AND status=\'sent\'', [(int)$quote['id']]) > 0;
    }

    private function money(mixed $value): ?int
    {
        $value = trim((string)$value);
        return preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $value) ? (int)round((float)$value*100) : null;
    }
}
