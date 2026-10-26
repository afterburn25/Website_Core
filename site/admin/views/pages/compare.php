<?php
/** @var array $page @var array $left @var array $right @var array $leftState @var array $rightState @var string $basePath */
$fields = [
    'slug' => 'URL slug', 'title' => 'Title', 'body' => 'Body', 'blocks_json' => 'Visual blocks',
    'meta_title' => 'Meta title', 'meta_description' => 'Meta description',
    'canonical' => 'Canonical URL', 'og_media_id' => 'Social image ID',
    'schema_json' => 'Schema JSON', 'noindex' => 'Hidden from search', 'parent_id' => 'Parent page ID',
];
$display = static function (string $field, mixed $value): string {
    if ($field === 'noindex') return !empty($value) ? 'Yes' : 'No';
    if (in_array($field, ['blocks_json', 'schema_json'], true) && is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        if ($decoded !== null) return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return $value === null || $value === '' ? '—' : (string) $value;
};
?>
<div class="card">
<div class="card-heading"><div><h2>Revision comparison</h2><p class="muted"><?= e($page['title']) ?> · every editable field is included</p></div><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/pages/edit/<?= (int) $page['id'] ?>">Back to editor</a></div>
</div>
<div class="revision-comparison">
<div class="revision-heading"><strong>Field</strong><strong>Older · <?= e($left['created_at']) ?></strong><strong>Newer · <?= e($right['created_at']) ?></strong></div>
<?php foreach ($fields as $key => $label):
    $leftValue = $display($key, $leftState[$key] ?? null);
    $rightValue = $display($key, $rightState[$key] ?? null);
    $changed = $leftValue !== $rightValue;
?>
<section class="revision-row<?= $changed ? ' is-changed' : '' ?>">
<h3><?= e($label) ?><?php if ($changed): ?> <span class="badge warn">Changed</span><?php endif; ?></h3>
<pre><?= e($leftValue) ?></pre><pre><?= e($rightValue) ?></pre>
</section>
<?php endforeach; ?>
</div>
