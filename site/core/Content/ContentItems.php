<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;
use Core\Modules\ModuleRegistry;

/**
 * Storage + validation for package-registered content types. A package
 * declares a type via ctx->contentType($type, $label, $fields); items are
 * stored here and the declared field set is enforced at save time —
 * undeclared keys are dropped, values are coerced by field type, and
 * 'html' fields pass the same HTMLPurifier boundary as page bodies.
 *
 * Packages read their items via $ctx->service(ContentItems::class).
 *
 * Field definition shape (list of arrays):
 *   ['name'=>'question','label'=>'Question','type'=>'text',
 *    'required'=>true,'options'=>[...] (select only)]
 * Supported types: text, textarea, html, number, url, select, checkbox,
 * media, media_list.
 */
final class ContentItems
{
    private const FIELD_TYPES = [
        'text', 'textarea', 'html', 'number', 'url', 'select', 'checkbox',
        'media', 'media_list',
    ];

    public function __construct(private Connection $db, private ModuleRegistry $registry) {}

    /**
     * All registered content types as
     * ['package/type' => ['package'=>..,'type'=>..,'label'=>..,'fields'=>[…]]].
     */
    public function types(): array
    {
        $out = [];
        foreach ($this->registry->contentTypes() as $package => $types) {
            foreach ($types as $type => $def) {
                $out[$package . '/' . $type] = [
                    'package' => $package, 'type' => $type,
                    'label' => $def['label'], 'fields' => $this->fields($def['fields']),
                ];
            }
        }
        return $out;
    }

    public function definition(string $package, string $type): ?array
    {
        return $this->types()[$package . '/' . $type] ?? null;
    }

    /** @return list<array> items with decoded `fields` */
    public function items(string $package, string $type, bool $publishedOnly = false): array
    {
        $sql = 'SELECT * FROM `' . $this->db->table('content_items')
            . '` WHERE package = ? AND type = ?'
            . ($publishedOnly ? " AND status = 'published'" : '')
            . ' ORDER BY sort, id';
        return array_map([$this, 'decode'], $this->db->fetchAll($sql, [$package, $type]));
    }

    public function get(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('content_items') . '` WHERE id = ?', [$id]
        );
        return $row ? $this->decode($row) : null;
    }

    /**
     * Seed a declared type only while its package partition is empty. The
     * caller owns the durable seed marker so a later administrator deletion
     * remains final instead of silently restoring defaults.
     *
     * @param list<array<string,mixed>> $items
     */
    public function seed(string $package, string $type, array $items, ?array $registeredDefinition = null): int
    {
        $definition = $registeredDefinition === null ? $this->definition($package, $type) : [
            'package' => $package,
            'type' => $type,
            'label' => (string) ($registeredDefinition['label'] ?? $type),
            'fields' => $this->fields((array) ($registeredDefinition['fields'] ?? [])),
        ];
        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown content type {$package}/{$type}.");
        }
        if ($this->items($package, $type) !== []) {
            return 0;
        }
        return $this->db->transaction(function () use ($package, $type, $items, $definition): int {
            $created = 0;
            foreach (array_slice(array_values($items), 0, 250) as $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException('Seed content records must be field maps.');
                }
                [$ok, $error] = $this->save(null, $package, $type, $item, null, $definition);
                if (!$ok) {
                    throw new \InvalidArgumentException((string) $error);
                }
                $created++;
            }
            return $created;
        });
    }

    /** @return list<array{id:int,label:string,mime:string,focal_x:float,focal_y:float}> */
    public function mediaOptions(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, original_name, alt_text, mime, focal_x, focal_y FROM `'
            . $this->db->table('media') . '` WHERE is_private = 0 ORDER BY id DESC LIMIT 500'
        );
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'label' => trim((string) ($row['alt_text'] ?? '')) ?: (string) $row['original_name'],
            'mime' => (string) $row['mime'],
            'focal_x' => (float) ($row['focal_x'] ?? 50),
            'focal_y' => (float) ($row['focal_y'] ?? 50),
        ], $rows);
    }

    /**
     * Create or update an item. $input is the raw field map plus optional
     * 'title'/'status'/'sort' keys; undeclared field keys are dropped.
     * @return array{0:bool,1:?string,2:?int} [ok, error, id]
     */
    public function save(
        ?int $id,
        string $package,
        string $type,
        array $input,
        ?int $userId,
        ?array $registeredDefinition = null,
    ): array
    {
        $def = $registeredDefinition ?? $this->definition($package, $type);
        if ($def === null) {
            return [false, "Unknown content type {$package}/{$type}.", null];
        }
        $fields = [];
        $errors = [];
        $sanitizer = new Sanitizer();
        foreach ($def['fields'] as $f) {
            $raw = $input[$f['name']] ?? null;
            if ($f['type'] === 'checkbox') {
                $fields[$f['name']] = in_array($raw, ['1', 'on', true, 1], true) ? '1' : '0';
                continue;
            }
            if ($f['type'] === 'media_list') {
                $values = is_array($raw) ? $raw : explode(',', (string) $raw);
                $normalized = $this->mediaIds($values, $f['label'], $errors);
                if ($normalized === '' && !empty($f['required'])) {
                    $errors[] = $f['label'] . ' is required.';
                }
                $fields[$f['name']] = $normalized;
                continue;
            }
            $raw = is_string($raw) ? trim($raw) : '';
            if ($raw === '') {
                if (!empty($f['required'])) {
                    $errors[] = $f['label'] . ' is required.';
                }
                $fields[$f['name']] = '';
                continue;
            }
            $fields[$f['name']] = $this->coerce($f, $raw, $sanitizer, $errors);
        }
        if ($errors !== []) {
            return [false, implode(' ', $errors), null];
        }

        $title = (string) ($input['title'] ?? '');
        if ($title === '') {
            foreach ($def['fields'] as $f) {
                if ($fields[$f['name']] !== '' && in_array($f['type'], ['text', 'select'], true)) {
                    $title = $fields[$f['name']];
                    break;
                }
            }
        }
        $status = ($input['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        $row = [
            'package' => $package, 'type' => $type,
            'title' => mb_substr((new Sanitizer())->text($title), 0, 190),
            'status' => $status,
            'data' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sort' => (int) ($input['sort'] ?? 0),
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id !== null) {
            $existing = $this->get($id);
            if (!$existing || $existing['package'] !== $package || $existing['type'] !== $type) {
                return [false, 'Item not found.', null];
            }
            $this->db->update('content_items', $row, 'id = ?', [$id]);
        } else {
            $row['created_by'] = $userId;
            $id = (int) $this->db->insert('content_items', $row);
        }
        $this->syncMediaUsage($id, $package, $type, $def['fields'], $fields);
        return [true, null, $id];
    }

    public function delete(int $id, ?string $package = null, ?string $type = null): bool
    {
        $existing = $this->get($id);
        if (!$existing
            || ($package !== null && $existing['package'] !== $package)
            || ($type !== null && $existing['type'] !== $type)
        ) {
            return false;
        }
        $this->db->run(
            'DELETE FROM `' . $this->db->table('media_usage') . '` WHERE context = ? AND context_id = ?',
            [$this->usageContext((string) $existing['package'], (string) $existing['type']), $id]
        );
        $this->db->run('DELETE FROM `' . $this->db->table('content_items') . '` WHERE id = ?', [$id]);
        return true;
    }

    private function coerce(array $f, string $raw, Sanitizer $sanitizer, array &$errors): string
    {
        switch ($f['type']) {
            case 'html':
                return $sanitizer->html($raw);
            case 'number':
                if (!is_numeric($raw)) {
                    $errors[] = $f['label'] . ' must be a number.';
                    return '';
                }
                return (string) ($raw + 0);
            case 'url':
                return $this->urlValue($f, $raw, $errors);
            case 'select':
                if (!array_key_exists($raw, $f['options'])) {
                    $errors[] = $f['label'] . ' has an invalid choice.';
                    return '';
                }
                return $raw;
            case 'media':
                if (!ctype_digit($raw) || !$this->publicMediaExists((int) $raw)) {
                    $errors[] = $f['label'] . ' must select an available Media item.';
                    return '';
                }
                return (string) (int) $raw;
            default: // text, textarea
                return $sanitizer->text($raw);
        }
    }

    private function urlValue(array $f, string $raw, array &$errors): string
    {
        $scheme = strtolower((string) parse_url($raw, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https', 'mailto'], true)) {
            $errors[] = $f['label'] . ' must be an http(s) or mailto URL.';
            return '';
        }
        return $raw;
    }

    /** @param array<int,mixed> $values */
    private function mediaIds(array $values, string $label, array &$errors): string
    {
        $ids = [];
        foreach (array_slice($values, 0, 40) as $value) {
            $value = trim((string) $value);
            if ($value === '') continue;
            if (!ctype_digit($value) || !$this->publicMediaExists((int) $value)) {
                $errors[] = $label . ' contains an unavailable Media item.';
                continue;
            }
            $ids[(int) $value] = (int) $value;
        }
        return implode(',', array_values($ids));
    }

    private function publicMediaExists(int $id): bool
    {
        return $id > 0 && (bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('media') . '` WHERE id = ? AND is_private = 0',
            [$id]
        );
    }

    private function syncMediaUsage(int $id, string $package, string $type, array $definitions, array $fields): void
    {
        $context = $this->usageContext($package, $type);
        $this->db->run(
            'DELETE FROM `' . $this->db->table('media_usage') . '` WHERE context = ? AND context_id = ?',
            [$context, $id]
        );
        $mediaIds = [];
        foreach ($definitions as $definition) {
            $name = (string) $definition['name'];
            if (($definition['type'] ?? '') === 'media') {
                $mediaId = (int) ($fields[$name] ?? 0);
                if ($mediaId > 0) $mediaIds[$mediaId] = $mediaId;
            } elseif (($definition['type'] ?? '') === 'media_list') {
                foreach (explode(',', (string) ($fields[$name] ?? '')) as $value) {
                    $mediaId = (int) trim($value);
                    if ($mediaId > 0) $mediaIds[$mediaId] = $mediaId;
                }
            }
        }
        foreach ($mediaIds as $mediaId) {
            $this->db->insert('media_usage', [
                'media_id' => $mediaId,
                'context' => $context,
                'context_id' => $id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function usageContext(string $package, string $type): string
    {
        return 'module.content.' . substr(hash('sha256', $package . '/' . $type), 0, 16);
    }

    /** Normalize a field-set declaration to the canonical shape. */
    private function fields(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            if (!is_array($f)) {
                continue;
            }
            $name = (string) ($f['name'] ?? '');
            if ($name === '' || !preg_match('/^[a-z0-9_]+$/', $name)) {
                continue;
            }
            $type = (string) ($f['type'] ?? 'text');
            $out[] = [
                'name' => $name,
                'label' => (string) ($f['label'] ?? $name),
                'type' => in_array($type, self::FIELD_TYPES, true) ? $type : 'text',
                'required' => !empty($f['required']),
                'options' => isset($f['options']) && is_array($f['options']) ? $f['options'] : [],
            ];
        }
        return $out;
    }

    private function decode(array $row): array
    {
        $row['fields'] = json_decode((string) ($row['data'] ?? ''), true) ?: [];
        unset($row['data']);
        return $row;
    }
}
