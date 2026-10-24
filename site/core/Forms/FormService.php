<?php
declare(strict_types=1);

namespace Core\Forms;

use Core\Database\Connection;
use Core\Security\Csrf;

/** Cross-industry form definitions, safe rendering and submission storage. */
final class FormService
{
    private const TYPES = ['text','email','tel','number','date','textarea','select','checkbox'];

    public function __construct(private Connection $db) {}

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT f.*, (SELECT COUNT(*) FROM `' . $this->db->table('form_submissions')
            . '` s WHERE s.form_id=f.id) submission_count FROM `' . $this->db->table('forms') . '` f ORDER BY f.name'
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM `' . $this->db->table('forms') . '` WHERE id=?', [$id]);
    }

    /** @return array{0:int,1:?string} */
    public function save(?int $id, string $name, string $fieldsJson, string $recipient, string $subject, string $success, bool $active, int $userId): array
    {
        if (trim($name) === '') return [0, 'Form name is required.'];
        [$fields, $error] = $this->normalizeFields($fieldsJson);
        if ($error !== null) return [0, $error];
        if ($recipient !== '' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) return [0, 'Notification recipient must be a valid email address.'];
        $data = [
            'name' => mb_substr(trim($name), 0, 190), 'fields_json' => json_encode($fields, JSON_UNESCAPED_SLASHES),
            'recipient' => $recipient ?: null, 'subject' => mb_substr($subject, 0, 190),
            'success_message' => mb_substr($success ?: 'Thank you. Your message has been received.', 0, 500),
            'active' => $active ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id && $this->find($id)) {
            $this->db->update('forms', $data, 'id=?', [$id]);
            return [$id, null];
        }
        $data['created_by'] = $userId;
        $data['created_at'] = date('Y-m-d H:i:s');
        return [(int) $this->db->insert('forms', $data), null];
    }

    public function archive(int $id): void
    {
        $this->db->update('forms', ['active' => 0, 'updated_at' => date('Y-m-d H:i:s')], 'id=?', [$id]);
    }

    public function submissions(
        ?int $formId = null,
        int $limit = 200,
        string $status = '',
        string $search = '',
        int $offset = 0,
    ): array
    {
        $sql = 'SELECT s.*, f.name form_name FROM `' . $this->db->table('form_submissions') . '` s JOIN `'
            . $this->db->table('forms') . '` f ON f.id=s.form_id';
        [$where, $params] = $this->submissionFilters($formId, $status, $search);
        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        return $this->db->fetchAll(
            $sql . ' ORDER BY s.id DESC LIMIT ' . max(1, min(1000, $limit)) . ' OFFSET ' . max(0, $offset),
            $params,
        );
    }

    public function submissionCount(?int $formId = null, string $status = '', string $search = ''): int
    {
        $sql = 'SELECT COUNT(*) FROM `' . $this->db->table('form_submissions') . '` s JOIN `'
            . $this->db->table('forms') . '` f ON f.id=s.form_id';
        [$where, $params] = $this->submissionFilters($formId, $status, $search);
        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        return (int) $this->db->fetchColumn($sql, $params);
    }

    public function markRead(int $id): void
    {
        $this->setSubmissionStatus($id, 'read');
    }

    public function setSubmissionStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['new', 'read', 'archived'], true)) return false;
        if (!(bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('form_submissions') . '` WHERE id=?', [$id]
        )) return false;
        $this->db->update('form_submissions', ['status' => $status], 'id=?', [$id]);
        return true;
    }

    /** @return array{0:?int,1:list<string>,2:array} */
    public function submit(int $formId, array $post, string $ipHash): array
    {
        $form = $this->find($formId);
        if (!$form || !(bool) $form['active']) return [null, ['This form is unavailable.'], []];
        $fields = json_decode((string) $form['fields_json'], true) ?: [];
        $clean = []; $errors = []; $consent = [];
        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $type = (string) $field['type'];
            $value = $post[$name] ?? '';
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($type === 'checkbox') $value = $value !== '' ? 'Yes' : '';
            if (!empty($field['required']) && $value === '') $errors[] = $field['label'] . ' is required.';
            if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[] = $field['label'] . ' must be a valid email.';
            if ($type === 'number' && $value !== '' && !is_numeric($value)) $errors[] = $field['label'] . ' must be a number.';
            $clean[$name] = mb_substr(strip_tags($value), 0, $type === 'textarea' ? 10000 : 1000);
            if ($type === 'checkbox') $consent[$name] = ['label' => $field['label'], 'accepted' => $value !== ''];
        }
        if ($errors !== []) return [null, $errors, $clean];
        $id = (int) $this->db->insert('form_submissions', [
            'form_id' => $formId, 'data_json' => json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'consent_json' => $consent === [] ? null : json_encode($consent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ip_hash' => $ipHash, 'status' => 'new', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [$id, [], $clean];
    }

    public function render(int $id, string $basePath, ?Csrf $csrf): string
    {
        $form = $this->find($id);
        if (!$form || !(bool) $form['active']) return '<p class="wc-form-unavailable">Form unavailable.</p>';
        $fields = json_decode((string) $form['fields_json'], true) ?: [];
        $html = '<form class="wc-public-form" method="post" action="' . e_attr(rtrim($basePath, '/') . '/index.php?p=/forms/' . $id . '/submit') . '">';
        if ($csrf) $html .= $csrf->field();
        $html .= '<div class="wc-honeypot" aria-hidden="true"><label>Company website<input name="company_url" tabindex="-1" autocomplete="off"></label></div>';
        foreach ($fields as $field) {
            $name = (string) $field['name']; $type = (string) $field['type']; $required = !empty($field['required']);
            $rid = 'wc-form-' . $id . '-' . $name;
            $html .= '<label for="' . e_attr($rid) . '">' . e((string) $field['label']) . ($required ? ' <span aria-hidden="true">*</span>' : '') . '</label>';
            $req = $required ? ' required' : '';
            if ($type === 'textarea') $html .= '<textarea id="' . e_attr($rid) . '" name="' . e_attr($name) . '"' . $req . '></textarea>';
            elseif ($type === 'select') {
                $html .= '<select id="' . e_attr($rid) . '" name="' . e_attr($name) . '"' . $req . '><option value="">Choose…</option>';
                foreach ($field['options'] ?? [] as $option) $html .= '<option value="' . e_attr((string) $option) . '">' . e((string) $option) . '</option>';
                $html .= '</select>';
            } elseif ($type === 'checkbox') $html .= '<input id="' . e_attr($rid) . '" type="checkbox" name="' . e_attr($name) . '" value="1"' . $req . '>';
            else $html .= '<input id="' . e_attr($rid) . '" type="' . e_attr($type) . '" name="' . e_attr($name) . '"' . $req . '>';
        }
        return $html . '<button class="wc-button" type="submit">Submit</button></form>';
    }

    /** @return array{0:list<array>,1:?string} */
    private function normalizeFields(string $json): array
    {
        $rows = json_decode($json, true);
        if (!is_array($rows) || $rows === []) return [[], 'Add at least one form field.'];
        $out = []; $seen = [];
        foreach (array_slice(array_values($rows), 0, 50) as $row) {
            if (!is_array($row)) continue;
            $name = trim(strtolower((string) ($row['name'] ?? '')));
            $name = trim((string) preg_replace('/[^a-z0-9_]+/', '_', $name), '_');
            $label = trim(strip_tags((string) ($row['label'] ?? '')));
            $type = (string) ($row['type'] ?? 'text');
            if ($name === '' || $label === '' || isset($seen[$name]) || !in_array($type, self::TYPES, true)) continue;
            $seen[$name] = true;
            $options = array_values(array_filter(array_map('trim', preg_split('/\R|,/', (string) ($row['options'] ?? '')) ?: [])));
            $out[] = ['name' => mb_substr($name, 0, 80), 'label' => mb_substr($label, 0, 190), 'type' => $type,
                'required' => !empty($row['required']), 'options' => array_slice($options, 0, 100)];
        }
        return $out === [] ? [[], 'No valid form fields were provided.'] : [$out, null];
    }

    /** @return array{0:list<string>,1:list<mixed>} */
    private function submissionFilters(?int $formId, string $status, string $search): array
    {
        $where = []; $params = [];
        if ($formId) { $where[] = 's.form_id=?'; $params[] = $formId; }
        if (in_array($status, ['new', 'read', 'archived'], true)) {
            $where[] = 's.status=?'; $params[] = $status;
        }
        $search = trim($search);
        if ($search !== '') {
            $where[] = '(f.name LIKE ? OR s.data_json LIKE ?)';
            $needle = '%' . $search . '%';
            $params[] = $needle; $params[] = $needle;
        }
        return [$where, $params];
    }
}
