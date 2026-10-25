<?php
declare(strict_types=1);

namespace Core\Forms;

use Core\Database\Connection;
use Core\Events\Dispatcher;
use Core\Mail\SystemMail;
use Core\Modules\Manifest;
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

    /** @var array<int,callable> */
    private array $validators = [];
    /** @var array<int,callable> */
    private array $submissionHandlers = [];
    /** @var array<int,string> forms registered by enabled packages this boot */
    private array $activePackageForms = [];

    public function __construct(private Connection $db, private ?Dispatcher $events = null) {}

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

    /**
     * Register a package-owned starter form without replacing later admin
     * edits. An optional adopt_id attaches a pre-existing Core form and all
     * of its submission history instead of creating a duplicate.
     *
     * @param list<array<string,mixed>> $fields
     * @param array<string,mixed> $options
     */
    public function registerStarter(
        string $packageId,
        string $key,
        string $label,
        array $fields,
        array $options = [],
        ?callable $validator = null,
        ?callable $onSubmitted = null,
    ): int {
        if (!Manifest::idOk($packageId)) {
            throw new \InvalidArgumentException('Starter forms require a valid package id.');
        }
        if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', $key)) {
            throw new \InvalidArgumentException('Starter form keys must be stable lowercase tokens.');
        }
        $allowed = [
            'recipient', 'subject', 'notification_template', 'success_message',
            'submit_label', 'active', 'adopt_id',
        ];
        foreach (array_keys($options) as $option) {
            if (!in_array((string) $option, $allowed, true)) {
                throw new \InvalidArgumentException('Unsupported starter form option: ' . $option);
            }
        }

        $existing = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('forms') . '` WHERE owner_package=? AND package_key=?',
            [$packageId, $key]
        );
        if ($existing !== null) {
            $id = (int) $existing['id'];
            $this->activePackageForms[$id] = $packageId;
            $this->registerCallbacks($id, $validator, $onSubmitted);
            return $id;
        }

        $adoptId = max(0, (int) ($options['adopt_id'] ?? 0));
        if ($adoptId > 0) {
            $adopt = $this->find($adoptId);
            if ($adopt === null) throw new \InvalidArgumentException('The form selected for adoption does not exist.');
            $owner = trim((string) ($adopt['owner_package'] ?? ''));
            if ($owner !== '' && $owner !== $packageId) {
                throw new \InvalidArgumentException('A package cannot adopt another package\'s form.');
            }
            $this->db->transaction(function () use ($adoptId, $packageId, $key): void {
                $this->db->update('forms', [
                    'owner_package' => $packageId,
                    'package_key' => $key,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id=?', [$adoptId]);
                $this->db->update('form_submissions', [
                    'owner_package' => $packageId,
                ], 'form_id=?', [$adoptId]);
            });
            $this->activePackageForms[$adoptId] = $packageId;
            $this->registerCallbacks($adoptId, $validator, $onSubmitted);
            return $adoptId;
        }

        [$id, $error] = $this->save(
            null,
            $label,
            json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            (string) ($options['recipient'] ?? ''),
            (string) ($options['subject'] ?? ''),
            (string) ($options['success_message'] ?? ''),
            !array_key_exists('active', $options) || (bool) $options['active'],
            0,
            (string) ($options['submit_label'] ?? 'Submit'),
            (string) ($options['notification_template'] ?? ''),
        );
        if ($error !== null || $id <= 0) {
            throw new \InvalidArgumentException($error ?? 'Unable to create starter form.');
        }
        try {
            $this->db->update('forms', [
                'owner_package' => $packageId,
                'package_key' => $key,
            ], 'id=?', [$id]);
        } catch (\Throwable $e) {
            $concurrent = $this->db->fetch(
                'SELECT id FROM `' . $this->db->table('forms') . '` WHERE owner_package=? AND package_key=?',
                [$packageId, $key]
            );
            if ($concurrent === null) throw $e;
            $this->db->run(
                'DELETE FROM `' . $this->db->table('forms') . '` WHERE id=? AND owner_package IS NULL',
                [$id]
            );
            $id = (int) $concurrent['id'];
        }
        $this->activePackageForms[$id] = $packageId;
        $this->registerCallbacks($id, $validator, $onSubmitted);
        return $id;
    }

    /** Return one package-owned submission without exposing another partition. */
    public function ownedSubmission(string $packageId, int $submissionId): ?array
    {
        if (!Manifest::idOk($packageId) || $submissionId <= 0) return null;
        $row = $this->db->fetch(
            'SELECT s.*, f.package_key, f.name form_name, m.status notification_status, '
            . 'm.last_error notification_error FROM `' . $this->db->table('form_submissions') . '` s JOIN `'
            . $this->db->table('forms') . '` f ON f.id=s.form_id LEFT JOIN `'
            . $this->db->table('system_messages') . '` m ON m.id=s.notification_message_id '
            . 'WHERE s.id=? AND s.owner_package=? AND f.owner_package=?',
            [$submissionId, $packageId, $packageId]
        );
        if ($row === null) return null;
        $row['data'] = json_decode((string) $row['data_json'], true) ?: [];
        $row['consent'] = json_decode((string) ($row['consent_json'] ?? ''), true) ?: [];
        $row['links'] = $this->db->fetchAll(
            'SELECT record_type,record_id,created_at FROM `' . $this->db->table('form_submission_links')
            . '` WHERE submission_id=? AND owner_package=? ORDER BY id',
            [$submissionId, $packageId]
        );
        unset($row['data_json'], $row['consent_json'], $row['ip_hash'], $row['dedupe_key']);
        return $row;
    }

    /** Link a package record to its protected Core submission idempotently. */
    public function linkSubmission(string $packageId, int $submissionId, string $recordType, string|int $recordId): bool
    {
        $recordType = trim($recordType);
        $recordId = trim((string) $recordId);
        if (!Manifest::idOk($packageId) || $submissionId <= 0
            || !preg_match('/^[a-z][a-z0-9_-]{1,79}$/', $recordType)
            || $recordId === '' || mb_strlen($recordId) > 190) {
            return false;
        }
        if (!(bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('form_submissions') . '` WHERE id=? AND owner_package=?',
            [$submissionId, $packageId]
        )) return false;
        if ((bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('form_submission_links')
            . '` WHERE submission_id=? AND owner_package=? AND record_type=? AND record_id=?',
            [$submissionId, $packageId, $recordType, $recordId]
        )) return true;
        $this->db->insert('form_submission_links', [
            'submission_id' => $submissionId,
            'owner_package' => $packageId,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
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
        string $notificationTemplate = '',
    ): array
    {
        $name = trim($name);
        $recipient = trim($recipient);
        $subject = trim($subject);
        $success = trim($success);
        $submitLabel = trim($submitLabel);
        $notificationTemplate = trim($notificationTemplate);
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
        if (mb_strlen($notificationTemplate) > 10000) return [0, 'Notification template must be 10,000 characters or fewer.'];
        $data = [
            'name' => $name, 'fields_json' => json_encode($fields, JSON_UNESCAPED_SLASHES),
            'recipient' => $recipient ?: null, 'subject' => mb_substr($subject, 0, 190),
            'success_message' => mb_substr($success ?: 'Thank you. Your message has been received.', 0, 500),
            'submit_label' => $submitLabel,
            'notification_template' => $notificationTemplate ?: null,
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
        $row = $this->db->fetch(
            'SELECT notification_message_id FROM `' . $this->db->table('form_submissions') . '` WHERE id=?',
            [$id]
        );
        if ($row === null) return false;
        return $this->db->transaction(function () use ($id, $row): bool {
            if ($this->db->tableExists('form_submission_links')) {
                $this->db->run(
                    'DELETE FROM `' . $this->db->table('form_submission_links') . '` WHERE submission_id=?',
                    [$id]
                );
            }
            $deleted = $this->db->run(
                'DELETE FROM `' . $this->db->table('form_submissions') . '` WHERE id=?',
                [$id]
            )->rowCount() > 0;
            $messageId = (int) ($row['notification_message_id'] ?? 0);
            if ($deleted && $messageId > 0) {
                $this->db->run(
                    'DELETE FROM `' . $this->db->table('system_messages') . '` WHERE id=?',
                    [$messageId]
                );
            }
            return $deleted;
        });
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

    /** @return array{0:?int,1:list<string>,2:array,3?:bool} */
    public function submit(int $formId, array $post, string $ipHash, ?string $dedupeToken = null): array
    {
        $form = $this->find($formId);
        if (!$form || !(bool) $form['active'] || !$this->packageFormAvailable($formId, $form)) {
            return [null, ['This form is unavailable.'], []];
        }
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
        if ($errors === [] && isset($this->validators[$formId])) {
            try {
                $custom = ($this->validators[$formId])($clean, $form);
                $errors = $this->normalizeCustomErrors($custom, $fields);
            } catch (\Throwable $e) {
                error_log('[website-core] form validator failed for form ' . $formId . ': ' . $e->getMessage());
                $errors[] = 'This request could not be validated. Please review it or try again.';
            }
        }
        if ($errors !== []) return [null, $errors, $clean];
        $dedupeKey = $dedupeToken === null || trim($dedupeToken) === ''
            ? null
            : hash('sha256', $formId . '|' . trim($dedupeToken));
        if ($dedupeKey !== null) {
            $duplicate = $this->db->fetch(
                'SELECT id,data_json FROM `' . $this->db->table('form_submissions') . '` WHERE dedupe_key=?',
                [$dedupeKey]
            );
            if ($duplicate !== null) {
                return [(int) $duplicate['id'], [], json_decode((string) $duplicate['data_json'], true) ?: [], true];
            }
        }
        $submission = [
            'form_id' => $formId, 'data_json' => json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'consent_json' => $consent === [] ? null : json_encode($consent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ip_hash' => $ipHash,
            'owner_package' => ($form['owner_package'] ?? null) ?: null,
            'dedupe_key' => $dedupeKey,
            'status' => 'new', 'created_at' => date('Y-m-d H:i:s'),
        ];
        try {
            $id = (int) $this->db->insert('form_submissions', $submission);
        } catch (\Throwable $e) {
            $duplicate = $dedupeKey === null ? null : $this->db->fetch(
                'SELECT id,data_json FROM `' . $this->db->table('form_submissions') . '` WHERE dedupe_key=?',
                [$dedupeKey]
            );
            if ($duplicate === null) throw $e;
            return [(int) $duplicate['id'], [], json_decode((string) $duplicate['data_json'], true) ?: [], true];
        }

        $eventPayload = [
            'id' => $id,
            'form_id' => $formId,
            'owner_package' => ($form['owner_package'] ?? null) ?: null,
            'form_key' => ($form['package_key'] ?? null) ?: null,
        ];
        foreach ($this->events?->dispatch('form.submitted', $eventPayload) ?? [] as $error) {
            error_log('[website-core] form submission event error ' . $error);
        }
        if (isset($this->submissionHandlers[$formId])) {
            try {
                ($this->submissionHandlers[$formId])($eventPayload + ['data' => $clean], $this);
            } catch (\Throwable $e) {
                error_log('[website-core] package form handler failed for submission ' . $id . ': ' . $e->getMessage());
            }
        }
        return [$id, [], $clean, false];
    }

    public function render(int $id, string $basePath, ?Csrf $csrf): string
    {
        $form = $this->find($id);
        if (!$form || !(bool) $form['active'] || !$this->packageFormAvailable($id, $form)) {
            return '<p class="wc-form-unavailable">Form unavailable.</p>';
        }
        $fields = json_decode((string) $form['fields_json'], true) ?: [];
        $html = '<form class="wc-public-form" method="post" action="' . e_attr(rtrim($basePath, '/') . '/index.php?p=/forms/' . $id . '/submit') . '">';
        if ($csrf) $html .= $csrf->field();
        $html .= '<input type="hidden" name="_form_token" value="' . e_attr(bin2hex(random_bytes(16))) . '">';
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

    /** Queue the Core-owned notification once and attach delivery evidence. */
    public function queueNotification(int $submissionId, SystemMail $mail): ?int
    {
        $row = $this->db->fetch(
            'SELECT s.*,f.name form_name,f.recipient,f.subject,f.notification_template FROM `'
            . $this->db->table('form_submissions') . '` s JOIN `' . $this->db->table('forms')
            . '` f ON f.id=s.form_id WHERE s.id=?',
            [$submissionId]
        );
        if ($row === null || trim((string) ($row['recipient'] ?? '')) === '') return null;
        if ((int) ($row['notification_message_id'] ?? 0) > 0) {
            return (int) $row['notification_message_id'];
        }
        $data = json_decode((string) $row['data_json'], true) ?: [];
        $tokens = $this->notificationTokens($row, $data);
        $body = $this->notificationBody($row, $data, $tokens);
        $subject = $this->renderNotificationTemplate(
            (string) ($row['subject'] ?: 'New website form submission'),
            $tokens,
        );
        $messageId = $mail->queue(
            (string) $row['recipient'],
            mb_substr($subject, 0, 190),
            $body,
            'form-submission:' . $submissionId,
        );
        if ($messageId > 0) {
            $this->attachNotification($submissionId, $messageId);
            return $messageId;
        }
        $existing = $this->db->fetchColumn(
            'SELECT notification_message_id FROM `' . $this->db->table('form_submissions') . '` WHERE id=?',
            [$submissionId]
        );
        return (int) $existing > 0 ? (int) $existing : null;
    }

    /** @param array<string,mixed> $data @param array<string,string> $tokens */
    private function notificationBody(array $formSubmission, array $data, array $tokens): string
    {
        $template = trim((string) ($formSubmission['notification_template'] ?? ''));
        if ($template === '') {
            $lines = [];
            foreach ($data as $name => $value) {
                $lines[] = ucwords(str_replace('_', ' ', (string) $name)) . ': ' . (string) $value;
            }
            return implode("\n", $lines);
        }
        return mb_substr($this->renderNotificationTemplate($template, $tokens), 0, 20000);
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    private function notificationTokens(array $formSubmission, array $data): array
    {
        $tokens = [
            'form_name' => (string) ($formSubmission['form_name'] ?? ''),
            'submission_id' => (string) ($formSubmission['id'] ?? ''),
            'submitted_at' => (string) ($formSubmission['created_at'] ?? ''),
        ];
        foreach ($data as $name => $value) {
            if (preg_match('/^[a-z][a-z0-9_]{0,79}$/', (string) $name)) {
                $tokens[(string) $name] = is_scalar($value) ? (string) $value : '';
            }
        }
        return $tokens;
    }

    /** @param array<string,string> $tokens */
    private function renderNotificationTemplate(string $template, array $tokens): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i', static function (array $match) use ($tokens): string {
            return $tokens[strtolower((string) $match[1])] ?? '';
        }, $template);
    }

    private function registerCallbacks(int $formId, ?callable $validator, ?callable $onSubmitted): void
    {
        if ($validator !== null) $this->validators[$formId] = $validator;
        if ($onSubmitted !== null) $this->submissionHandlers[$formId] = $onSubmitted;
    }

    private function packageFormAvailable(int $formId, array $form): bool
    {
        $owner = trim((string) ($form['owner_package'] ?? ''));
        return $owner === '' || ($this->activePackageForms[$formId] ?? null) === $owner;
    }

    /** @return list<string> */
    private function normalizeCustomErrors(mixed $result, array $fields): array
    {
        if ($result === null || $result === true || $result === []) return [];
        if ($result === false) return ['This request could not be accepted.'];
        if (is_string($result)) return [mb_substr(trim(strip_tags($result)), 0, 500)];
        if (!is_array($result)) return ['This request could not be validated.'];
        $labels = [];
        foreach ($fields as $field) $labels[(string) ($field['name'] ?? '')] = (string) ($field['label'] ?? 'Field');
        $errors = [];
        foreach ($result as $field => $message) {
            if (!is_scalar($message) || trim((string) $message) === '') continue;
            $safe = mb_substr(trim(strip_tags((string) $message)), 0, 500);
            if (is_string($field) && isset($labels[$field])) $safe = $labels[$field] . ': ' . $safe;
            $errors[] = $safe;
            if (count($errors) >= 50) break;
        }
        return $errors;
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
