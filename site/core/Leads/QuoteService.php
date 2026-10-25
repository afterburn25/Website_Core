<?php
declare(strict_types=1);

namespace Core\Leads;

use Core\Content\Sanitizer;
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
        $quote['attachments'] = $this->db->tableExists('crm_quote_attachments')
            ? $this->db->fetchAll('SELECT * FROM `' . $this->db->table('crm_quote_attachments') . '` WHERE quote_id=? AND owner_package=? ORDER BY id', [$id, $ownerPackage])
            : [];
        $quote['reminders'] = $this->db->tableExists('crm_quote_reminders')
            ? $this->db->fetchAll('SELECT * FROM `' . $this->db->table('crm_quote_reminders') . '` WHERE quote_id=? AND owner_package=? ORDER BY due_at,id', [$id, $ownerPackage])
            : [];
        $quote['documents'] = $this->db->tableExists('crm_quote_documents')
            ? $this->db->fetchAll('SELECT id,quote_id,owner_package,kind,title,sha256,created_by,created_at FROM `' . $this->db->table('crm_quote_documents') . '` WHERE quote_id=? AND owner_package=? ORDER BY id DESC', [$id, $ownerPackage])
            : [];
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

    /** Link a protected Core Media record without copying file bytes. */
    public function attach(int $quoteId, string $ownerPackage, int $mediaId, string $label, ?int $adminId = null): array
    {
        if (!$this->find($quoteId, $ownerPackage)) return [null, 'Quote was not found for this module.'];
        $media = $this->db->fetch(
            'SELECT id,original_name FROM `' . $this->db->table('media') . '` WHERE id=? AND is_private=1',
            [$mediaId],
        );
        if (!$media) return [null, 'Choose an available private Media record.'];
        $label = mb_substr(trim(strip_tags($label)), 0, 190);
        if ($label === '') $label = mb_substr((string) $media['original_name'], 0, 190);
        $existing = $this->db->fetch(
            'SELECT id FROM `' . $this->db->table('crm_quote_attachments') . '` WHERE quote_id=? AND media_id=?',
            [$quoteId, $mediaId],
        );
        if ($existing) {
            $this->trackAttachmentUsage((int) $existing['id'], $mediaId);
            return [(int) $existing['id'], null];
        }
        $id = (int) $this->db->insert('crm_quote_attachments', [
            'quote_id' => $quoteId, 'owner_package' => $ownerPackage,
            'media_id' => $mediaId, 'label' => $label, 'added_by' => $adminId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->trackAttachmentUsage($id, $mediaId);
        return [$id, null];
    }

    public function removeAttachment(int $id, string $ownerPackage): bool
    {
        return $this->db->transaction(function () use ($id, $ownerPackage): bool {
            if (!(bool) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $this->db->table('crm_quote_attachments') . '` WHERE id=? AND owner_package=?',
                [$id, $ownerPackage],
            )) return false;
            $this->db->run(
                'DELETE FROM `' . $this->db->table('media_usage') . '` WHERE context=? AND context_id=?',
                ['crm.quote.attachment', $id],
            );
            return $this->db->run(
                'DELETE FROM `' . $this->db->table('crm_quote_attachments') . '` WHERE id=? AND owner_package=?',
                [$id, $ownerPackage],
            )->rowCount() > 0;
        });
    }

    /** Return one package-owned private attachment and its protected Media metadata. */
    public function attachment(int $id, string $ownerPackage): ?array
    {
        return $this->db->fetch(
            'SELECT a.*,m.filename,m.original_name,m.mime,m.size_bytes,m.is_private FROM `'
            . $this->db->table('crm_quote_attachments') . '` a JOIN `'
            . $this->db->table('media') . '` m ON m.id=a.media_id'
            . ' WHERE a.id=? AND a.owner_package=? AND m.is_private=1',
            [$id, $ownerPackage],
        ) ?: null;
    }

    /** Queue an idempotent reminder record; delivery remains in Core's mail/job services. */
    public function scheduleReminder(
        int $quoteId,
        string $ownerPackage,
        string $dueAt,
        string $recipient,
        string $message,
        string $channel = 'email',
    ): array {
        if (!$this->find($quoteId, $ownerPackage)) return [null, 'Quote was not found for this module.'];
        if (!in_array($channel, ['email', 'internal'], true)) return [null, 'Reminder channel is not supported.'];
        if ($channel === 'email' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [null, 'Reminder recipient must be a valid email address.'];
        }
        $timestamp = strtotime($dueAt);
        if ($timestamp === false) return [null, 'Reminder date and time are invalid.'];
        $dueAt = date('Y-m-d H:i:s', $timestamp);
        $message = mb_substr(trim(strip_tags($message)), 0, 5000);
        if ($message === '') return [null, 'Enter a reminder message.'];
        $recipient = mb_substr(strtolower(trim($recipient)), 0, 190);
        $dedupe = hash('sha256', implode("\0", [$ownerPackage, (string) $quoteId, $dueAt, $channel, $recipient, $message]));
        $existing = $this->db->fetch(
            'SELECT id FROM `' . $this->db->table('crm_quote_reminders') . '` WHERE dedupe_key=?',
            [$dedupe],
        );
        if ($existing) return [(int) $existing['id'], null];
        $id = (int) $this->db->insert('crm_quote_reminders', [
            'quote_id' => $quoteId, 'owner_package' => $ownerPackage, 'due_at' => $dueAt,
            'channel' => $channel, 'recipient' => $recipient, 'message' => $message,
            'status' => 'pending', 'dedupe_key' => $dedupe, 'sent_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [$id, null];
    }

    /** @return list<array<string,mixed>> */
    public function dueReminders(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->fetchAll(
            'SELECT * FROM `' . $this->db->table('crm_quote_reminders')
            . '` WHERE status=? AND due_at<=? ORDER BY due_at,id LIMIT ' . $limit,
            ['pending', date('Y-m-d H:i:s')],
        );
    }

    public function completeReminder(int $id, string $ownerPackage, bool $sent): bool
    {
        return $this->db->update('crm_quote_reminders', [
            'status' => $sent ? 'sent' : 'cancelled',
            'sent_at' => $sent ? date('Y-m-d H:i:s') : null,
        ], 'id=? AND owner_package=? AND status=?', [$id, $ownerPackage, 'pending']) > 0;
    }

    /**
     * Persist an immutable, sanitized document snapshot rendered by a package.
     * Core owns evidence and integrity; package code owns the wording/template.
     */
    public function snapshotDocument(
        int $quoteId,
        string $ownerPackage,
        string $kind,
        string $title,
        string $html,
        ?int $adminId = null,
    ): array {
        if (!$this->find($quoteId, $ownerPackage)) return [null, 'Quote was not found for this module.'];
        if (!in_array($kind, ['proposal', 'contract', 'summary'], true)) {
            return [null, 'Document kind is not supported.'];
        }
        $title = mb_substr(trim(strip_tags($title)), 0, 190);
        $html = (new Sanitizer())->html($html);
        if ($title === '' || trim(strip_tags($html)) === '') return [null, 'Document title and content are required.'];
        if (strlen($html) > 500000) return [null, 'Document content is too large.'];
        $id = (int) $this->db->insert('crm_quote_documents', [
            'quote_id' => $quoteId, 'owner_package' => $ownerPackage,
            'kind' => $kind, 'title' => $title, 'html_snapshot' => $html,
            'sha256' => hash('sha256', $html), 'created_by' => $adminId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [$id, null];
    }

    public function document(int $id, string $ownerPackage): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('crm_quote_documents') . '` WHERE id=? AND owner_package=?',
            [$id, $ownerPackage],
        );
        if (!$row || !hash_equals((string) $row['sha256'], hash('sha256', (string) $row['html_snapshot']))) {
            return null;
        }
        return $row;
    }

    private function trackAttachmentUsage(int $attachmentId, int $mediaId): void
    {
        if ((bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('media_usage') . '` WHERE context=? AND context_id=? AND media_id=?',
            ['crm.quote.attachment', $attachmentId, $mediaId],
        )) return;
        $this->db->insert('media_usage', [
            'media_id' => $mediaId, 'context' => 'crm.quote.attachment',
            'context_id' => $attachmentId, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function money(mixed $value): ?int
    {
        $value = trim((string)$value);
        return preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $value) ? (int)round((float)$value*100) : null;
    }
}
