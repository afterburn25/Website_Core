<?php
declare(strict_types=1);

namespace Core\Forms;

use Core\Database\Connection;
use Core\Security\Csrf;

/** Cross-industry form definitions, safe rendering and submission storage. */
final class FormService
{
    private const TYPES = ['text', 'email', 'tel', 'number', 'date', 'textarea', 'select', 'checkbox'];
    private const STATUSES = ['new', 'read', 'archived'];
    private const AUTOCOMPLETE = [
        '', 'name', 'given-name', 'family-name', 'organization', 'email', 'tel',
        'street-address', 'address-level2', 'address-level1', 'postal-code', 'country-name',
    ];

    public function __construct(private Connection $db) {}

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT f.*, (SELECT COUNT(*) FROM `' . $this->db->table('form_submissions')
            . '` s WHERE s.form_id=f.id) submission_count, (SELECT COUNT(*) FROM `'
            . $this->db->table('form_submissions') . "` n WHERE n.form_id=f.id AND n.status='new') new_count FROM `"
            . $this->db->table('forms') . '` f ORDER BY f.name'
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM `' . $this->db->table('forms') . '` WHERE id=?', [$id]);
    }

    /** @return array{0:int,1:?string} */
    public function save(
        ?int $id,
        string $name,
        string $fieldsJson,
        string $recipient,
        string $subject,
        string $success,
        bool $active,
        int $userId,
        string $submitLabel = 'Submit',
    ): array
    {
        $name = trim($name);
        $recipient = trim($recipient);
        $subject = trim($subject);
        $success = trim($success);
        $submitLabel = trim($submitLabel);
        if ($name === '') return [0, 'Form name is required.'];
        if (mb_strlen($name) > 190) return [0, 'Form name must be 190 characters or fewer.'];
        if ($id !== null && !$this->find($id)) return [0, 'That form no longer exists.'];
        [$fields, $error] = $this->normalizeFields($fieldsJson);
        if ($error !== null) return [0, $error];
        if ($recipient !== '' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) return [0, 'Notification recipient must be a valid email address.'];
        if (mb_strlen($recipient) > 190) return [0, 'Notification recipient must be 190 characters or fewer.'];
        if (mb_strlen($subject) > 190) return [0, 'Email subject must be 190 characters or fewer.'];
        if (mb_strlen($success) > 500) return [0, 'Success message must be 500 characters or fewer.'];
        if ($submitLabel === '') return [0, 'Submit button label is required.'];
        if (mb_strlen($submitLabel) > 80) return [0, 'Submit button label must be 80 characters or fewer.'];
        $data = [
            'name' => $name, 'fields_json' => json_encode($fields, JSON_UNESCAPED_SLASHES),
            'recipient' => $recipient ?: null, 'subject' => mb_substr($subject, 0, 190),
            'success_message' => mb_substr($success ?: 'Thank you. Your message has been received.', 0, 500),
            'submit_label' => $submitLabel,
            'active' => $active ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id !== null) {
            $this->db->update('forms', $data, 'id=?', [$id]);
            return [$id, null];
        }
        $data['created_by'] = $userId;
        $data['created_at'] = date('Y-m-d H:i:s');
        return [(int) $this->db->insert('forms', $data), null];
    }

    public function archive(int $id): bool
    {
        return $this->setActive($id, false);
    }

    public function setActive(int $id, bool $active): bool
    {
        if (!$this->find($id)) return false;
        $this->db->update('forms', [
            'active' => $active ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id=?', [$id]);
        return true;
    }

    public function submissions(
        ?int $formId = null,
        int $limit = 200,
        string $status = '',
        string $search = '',
        int $offset = 0,
    ): array
    {
        $sql = 'SELECT s.*, f.name form_name, m.status notification_status, m.last_error notification_error '
            . 'FROM `' . $this->db->table('form_submissions') . '` s JOIN `'
            . $this->db->table('forms') . '` f ON f.id=s.form_id LEFT JOIN `'
            . $this->db->table('system_messages') . '` m ON m.id=s.notification_message_id';
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
        if (!in_array($status, self::STATUSES, true)) return false;
        if (!(bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('form_submissions') . '` WHERE id=?', [$id]
        )) return false;
        $this->db->update('form_submissions', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id=?', [$id]);
        return true;
    }

    public function deleteSubmission(int $id): bool
    {
        $table = $this->db->table('form_submissions');
        return $this->db->run("DELETE FROM `{$table}` WHERE id=?", [$id])->rowCount() > 0;
    }

    /** @return array{updated:int,missing:int} */
    public function bulkSubmissionAction(array $ids, string $action): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        $ids = array_slice($ids, 0, 200);
        if ($ids === [] || !in_array($action, [...self::STATUSES, 'delete'], true)) {
            return ['updated' => 0, 'missing' => count($ids)];
        }
        $updated = 0;
        foreach ($ids as $id) {
            $updated += ($action === 'delete' ? $this->deleteSubmission($id) : $this->setSubmissionStatus($id, $action)) ? 1 : 0;
        }
        return ['updated' => $updated, 'missing' => count($ids) - $updated];
    }

    public function attachNotification(int $submissionId, int $messageId): void
    {
        if ($messageId <= 0) return;
        $this->db->update('form_submissions', [
            'notification_message_id' => $messageId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id=?', [$submissionId]);
    }

    /** @return array{0:?int,1:list<string>,2:array} */
    public function submit(int $formId, array $post, string $ipHash): array
    {
        $form = $this->find($formId);
        if (!$form || !(bool) $form['active']) return [null, ['This form is unavailable.'], []];
        $fields = json_decode((string) $form['fields_json'], true) ?: [];
        $clean = []; $errors = []; $consent = [];
        $acceptedAt = date('c');
        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $type = (string) $field['type'];
            $value = $post[$name] ?? '';
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($type === 'checkbox') $value = $value !== '' ? 'Yes' : '';
            if (!empty($field['required']) && $value === '') $errors[] = $field['label'] . ' is required.';
            if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[] = $field['label'] . ' must be a valid email.';
            if ($type === 'number' && $value !== '' && !is_numeric($value)) $errors[] = $field['label'] . ' must be a number.';
            if ($type === 'date' && $value !== '' && !$this->validDate($value)) $errors[] = $field['label'] . ' must be a valid date.';
            if ($type === 'select' && $value !== '' && !in_array($value, $field['options'] ?? [], true)) $errors[] = $field['label'] . ' contains an unavailable choice.';
            $clean[$name] = mb_substr(strip_tags($value), 0, $type === 'textarea' ? 10000 : 1000);
            if ($type === 'checkbox') {
                $consent[$name] = [
                    'label' => $field['label'],
                    'accepted' => $value !== '',
                    'recorded_at' => $acceptedAt,
                ];
            }
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
            $help = trim((string) ($field['help'] ?? ''));
            $helpId = $help === '' ? '' : $rid . '-help';
            if ($type !== 'checkbox') {
                $html .= '<label for="' . e_attr($rid) . '">' . e((string) $field['label']) . ($required ? ' <span aria-hidden="true">*</span>' : '') . '</label>';
            }
            $req = $required ? ' required' : '';
            $described = $helpId === '' ? '' : ' aria-describedby="' . e_attr($helpId) . '"';
            $placeholder = trim((string) ($field['placeholder'] ?? ''));
            $placeholderAttr = $placeholder === '' ? '' : ' placeholder="' . e_attr($placeholder) . '"';
            $autocomplete = in_array((string) ($field['autocomplete'] ?? ''), self::AUTOCOMPLETE, true)
                ? (string) ($field['autocomplete'] ?? '') : '';
            $autocompleteAttr = $autocomplete === '' ? '' : ' autocomplete="' . e_attr($autocomplete) . '"';
            if ($type === 'textarea') $html .= '<textarea id="' . e_attr($rid) . '" name="' . e_attr($name) . '"' . $req . $described . $placeholderAttr . '></textarea>';
            elseif ($type === 'select') {
                $html .= '<select id="' . e_attr($rid) . '" name="' . e_attr($name) . '"' . $req . $described . '><option value="">Choose…</option>';
                foreach ($field['options'] ?? [] as $option) $html .= '<option value="' . e_attr((string) $option) . '">' . e((string) $option) . '</option>';
                $html .= '</select>';
            } elseif ($type === 'checkbox') {
                $html .= '<label class="wc-form-consent" for="' . e_attr($rid) . '"><input id="' . e_attr($rid) . '" type="checkbox" name="' . e_attr($name) . '" value="1"' . $req . $described . '><span>' . e((string) $field['label']) . ($required ? ' <span aria-hidden="true">*</span>' : '') . '</span></label>';
            } else {
                $html .= '<input id="' . e_attr($rid) . '" type="' . e_attr($type) . '" name="' . e_attr($name) . '"' . $req . $described . $placeholderAttr . $autocompleteAttr . '>';
            }
            if ($help !== '') $html .= '<small class="wc-form-help" id="' . e_attr($helpId) . '">' . e($help) . '</small>';
        }
        return $html . '<button class="wc-button" type="submit">' . e((string) ($form['submit_label'] ?? 'Submit')) . '</button></form>';
    }

    /** @return array{0:list<array>,1:?string} */
    private function normalizeFields(string $json): array
    {
        $rows = json_decode($json, true);
        if (!is_array($rows) || $rows === []) return [[], 'Add at least one form field.'];
        if (count($rows) > 50) return [[], 'A form may contain no more than 50 fields.'];
        $out = []; $seen = [];
        foreach (array_values($rows) as $index => $row) {
            $position = $index + 1;
            if (!is_array($row)) return [[], "Field {$position} is invalid."];
            $name = trim(strtolower((string) ($row['name'] ?? '')));
            $name = trim((string) preg_replace('/[^a-z0-9_]+/', '_', $name), '_');
            $label = trim(strip_tags((string) ($row['label'] ?? '')));
            $type = (string) ($row['type'] ?? 'text');
            if ($label === '') return [[], "Field {$position} needs a label."];
            if (mb_strlen($label) > 190) return [[], "Field {$position} label must be 190 characters or fewer."];
            if ($name === '') return [[], "Field {$position} needs a field name."];
            if (mb_strlen($name) > 80) return [[], "Field {$position} name must be 80 characters or fewer."];
            if (isset($seen[$name])) return [[], 'Each field name must be unique; duplicate: ' . $name . '.'];
            if (!in_array($type, self::TYPES, true)) return [[], "Field {$position} has an unsupported type."];
            $seen[$name] = true;
            $optionsValue = $row['options'] ?? '';
            $options = is_array($optionsValue)
                ? array_map('strval', $optionsValue)
                : (preg_split('/\R|,/', (string) $optionsValue) ?: []);
            foreach ($options as $option) {
                if (mb_strlen(trim(strip_tags($option))) > 190) {
                    return [[], "Field {$position} dropdown choices must be 190 characters or fewer."];
                }
            }
            $options = array_values(array_unique(array_filter(array_map(
                static fn (string $value): string => mb_substr(trim(strip_tags($value)), 0, 190),
                $options
            ))));
            if ($type === 'select' && $options === []) return [[], "Field {$position} needs at least one dropdown option."];
            if (count($options) > 100) return [[], "Field {$position} may contain no more than 100 dropdown options."];
            $autocomplete = (string) ($row['autocomplete'] ?? '');
            if (!in_array($autocomplete, self::AUTOCOMPLETE, true)) $autocomplete = '';
            $placeholder = trim(strip_tags((string) ($row['placeholder'] ?? '')));
            $help = trim(strip_tags((string) ($row['help'] ?? '')));
            if (mb_strlen($placeholder) > 190) return [[], "Field {$position} placeholder must be 190 characters or fewer."];
            if (mb_strlen($help) > 300) return [[], "Field {$position} help text must be 300 characters or fewer."];
            $out[] = [
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'required' => !empty($row['required']),
                'options' => $options,
                'placeholder' => $placeholder,
                'help' => $help,
                'autocomplete' => $autocomplete,
            ];
        }
        return [$out, null];
    }

    /** @return array{0:list<string>,1:list<mixed>} */
    private function submissionFilters(?int $formId, string $status, string $search): array
    {
        $where = []; $params = [];
        if ($formId) { $where[] = 's.form_id=?'; $params[] = $formId; }
        if (in_array($status, self::STATUSES, true)) {
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

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
