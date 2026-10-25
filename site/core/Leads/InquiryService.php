<?php
declare(strict_types=1);

namespace Core\Leads;

use Core\Database\Connection;

final class InquiryService
{
    public const STATUSES = ['new', 'contacted', 'qualified', 'quoted', 'won', 'lost', 'archived'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(private Connection $db) {}

    /** @return array{0:?int,1:?string} */
    public function submit(string $ownerPackage, array $input, ?int $sourceSubmissionId = null): array
    {
        $name = mb_substr(trim(strip_tags((string) ($input['name'] ?? ''))), 0, 190);
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = mb_substr(trim((string) ($input['phone'] ?? '')), 0, 60);
        $subject = mb_substr(trim(strip_tags((string) ($input['subject'] ?? 'General inquiry'))), 0, 190);
        $message = mb_substr(trim(strip_tags((string) ($input['message'] ?? ''))), 0, 12000);
        if ($name === '') return [null, 'Enter the customer name.'];
        if ($email === '' && $phone === '') return [null, 'Enter an email address or phone number.'];
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return [null, 'Enter a valid email address.'];
        if ($phone !== '' && !preg_match('/^[+0-9().,; x-]{3,60}$/i', $phone)) return [null, 'Enter a valid phone number.'];
        if ($message === '') return [null, 'Enter the inquiry details.'];
        $now = date('Y-m-d H:i:s');
        try {
            $id = (int) $this->db->insert('crm_inquiries', [
                'owner_package' => $ownerPackage, 'source_submission_id' => $sourceSubmissionId,
                'status' => 'new', 'priority' => 'normal', 'customer_name' => $name,
                'customer_email' => $email !== '' ? $email : null,
                'customer_phone' => $phone !== '' ? $phone : null,
                'subject' => $subject !== '' ? $subject : 'General inquiry', 'message' => $message,
                'assigned_admin_id' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            return [$id, null];
        } catch (\PDOException $e) {
            if ($sourceSubmissionId !== null && (str_contains(strtolower($e->getMessage()), 'unique')
                || str_contains(strtolower($e->getMessage()), 'duplicate'))) {
                $id = $this->db->fetchColumn('SELECT id FROM `' . $this->db->table('crm_inquiries')
                    . '` WHERE owner_package = ? AND source_submission_id = ?', [$ownerPackage, $sourceSubmissionId]);
                return [$id ? (int) $id : null, $id ? null : 'The inquiry could not be linked to that submission.'];
            }
            return [null, 'The inquiry could not be saved.'];
        }
    }

    public function all(string $ownerPackage, string $status = '', string $search = '', int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = $this->where($ownerPackage, $status, $search);
        $offset = (max(1, $page) - 1) * max(1, min(100, $perPage));
        return $this->db->fetchAll('SELECT * FROM `' . $this->db->table('crm_inquiries') . '` WHERE ' . $where
            . ' ORDER BY CASE priority WHEN \'urgent\' THEN 0 WHEN \'high\' THEN 1 WHEN \'normal\' THEN 2 ELSE 3 END, created_at DESC LIMIT '
            . max(1, min(100, $perPage)) . ' OFFSET ' . $offset, $params);
    }

    public function count(string $ownerPackage, string $status = '', string $search = ''): int
    {
        [$where, $params] = $this->where($ownerPackage, $status, $search);
        return (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table('crm_inquiries') . '` WHERE ' . $where, $params);
    }

    public function find(int $id, string $ownerPackage): ?array
    {
        $row = $this->db->fetch('SELECT * FROM `' . $this->db->table('crm_inquiries') . '` WHERE id = ? AND owner_package = ?', [$id, $ownerPackage]);
        if (!$row) return null;
        $row['notes'] = $this->db->fetchAll('SELECT n.*, a.name AS admin_name FROM `' . $this->db->table('crm_inquiry_notes') . '` n LEFT JOIN `'
            . $this->db->table('admins') . '` a ON a.id=n.admin_id WHERE n.inquiry_id=? ORDER BY n.created_at DESC', [$id]);
        return $row;
    }

    public function update(int $id, string $ownerPackage, string $status, string $priority, ?int $assignedAdminId): bool
    {
        if (!in_array($status, self::STATUSES, true) || !in_array($priority, self::PRIORITIES, true)) return false;
        return $this->db->update('crm_inquiries', [
            'status' => $status, 'priority' => $priority, 'assigned_admin_id' => $assignedAdminId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND owner_package = ?', [$id, $ownerPackage]) > 0;
    }

    public function addNote(int $id, string $ownerPackage, int $adminId, string $body): ?string
    {
        $body = mb_substr(trim(strip_tags($body)), 0, 5000);
        if ($body === '') return 'Enter a note.';
        if (!$this->find($id, $ownerPackage)) return 'Inquiry was not found.';
        $this->db->insert('crm_inquiry_notes', ['inquiry_id' => $id, 'admin_id' => $adminId, 'body' => $body, 'created_at' => date('Y-m-d H:i:s')]);
        return null;
    }

    private function where(string $ownerPackage, string $status, string $search): array
    {
        $where = ['owner_package = ?']; $params = [$ownerPackage];
        if ($status !== '' && in_array($status, self::STATUSES, true)) { $where[] = 'status = ?'; $params[] = $status; }
        $search = trim($search);
        if ($search !== '') {
            $where[] = '(customer_name LIKE ? OR customer_email LIKE ? OR subject LIKE ?)';
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            array_push($params, $term, $term, $term);
        }
        return [implode(' AND ', $where), $params];
    }
}
