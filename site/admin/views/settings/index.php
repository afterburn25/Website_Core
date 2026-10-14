<?php /** @var array $fields @var array $values @var string $cronToken @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<form method="post" action="<?= e_attr($basePath) ?>/admin/settings">
<?= $csrf->field() ?>
<?php foreach ($fields as $key => $meta): ?>
<?php $name = str_replace('.', '_', $key); ?>
<label for="<?= e_attr($name) ?>"><?= e($meta['label']) ?></label>
<input id="<?= e_attr($name) ?>" name="<?= e_attr($name) ?>" type="<?= e_attr($meta['type']) ?>"
       value="<?= e_attr($values[$key]) ?>">
<?php endforeach; ?>
<div style="margin-top:1rem"><button class="btn" type="submit">Save settings</button></div>
</form>
</div>
<div class="card">
<h2 style="margin-top:0">System</h2>
<p class="muted">Cron runner token (for <code>cron.php?token=…</code> on hosts without CLI cron):</p>
<p><code><?= e($cronToken !== '' ? $cronToken : '(generated on first save)') ?></code></p>
</div>
