<?php /** @var array $fields @var array $mailFields @var array $values @var string $cronToken
  * @var bool $hasSmtpPassword @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<form method="post" action="<?= e_attr($basePath) ?>/admin/settings">
<?= $csrf->field() ?>
<?php foreach ($fields as $key => $meta): ?>
<?php $name = str_replace('.', '_', $key); ?>
<label for="<?= e_attr($name) ?>"><?= e($meta['label']) ?></label>
<input id="<?= e_attr($name) ?>" name="<?= e_attr($name) ?>" type="<?= e_attr($meta['type']) ?>"
       value="<?= e_attr($values[$key]) ?>">
<?php endforeach; ?>

<h2>System mail</h2>
<p class="muted">Transactional messages only (recovery/security notices). Without SMTP,
messages queue until a transport is configured — the site keeps working.</p>
<?php foreach ($mailFields as $key => $meta): ?>
<?php $name = str_replace('.', '_', $key); ?>
<label for="<?= e_attr($name) ?>"><?= e($meta['label']) ?></label>
<input id="<?= e_attr($name) ?>" name="<?= e_attr($name) ?>" type="<?= e_attr($meta['type']) ?>"
       value="<?= e_attr($values[$key]) ?>">
<?php endforeach; ?>
<label for="smtp_password">SMTP password <?= $hasSmtpPassword ? '<span class="muted">(set — enter to change)</span>' : '' ?></label>
<input id="smtp_password" name="smtp_password" type="password" value="" autocomplete="new-password">
<div style="margin-top:1rem"><button class="btn" type="submit">Save settings</button></div>
</form>
</div>
<?php if ($licenseStatus !== null): ?>
<div class="card">
<h2 style="margin-top:0">License</h2>
<?php if ($licenseStatus['license_key'] !== null): ?>
<dl>
<dt><strong>License key</strong></dt>
<dd><code><?= e(substr($licenseStatus['license_key'], 0, 8)) ?>…</code></dd>
<dt><strong>Domain</strong></dt>
<dd><code><?= e($licenseStatus['license_domain'] ?? '—') ?></code></dd>
<?php if ($licenseStatus['license_valid'] !== null): ?>
<dt><strong>Status</strong></dt>
<dd>
<?php if (!empty($licenseStatus['license_deactivated'])): ?>
<span style="color:red">Deactivated</span>
<?php elseif ($licenseStatus['license_valid']): ?>
<span style="color:green">Active</span>
<?php else: ?>
<span style="color:red">Inactive</span>
<?php endif; ?>
</dd>
<?php endif; ?>
<?php if (!empty($licenseStatus['license_error'])): ?>
<dt><strong>Last error</strong></dt>
<dd><code><?= e($licenseStatus['license_error']) ?></code></dd>
<?php endif; ?>
<?php if ($licenseStatus['license_validated_at'] !== null): ?>
<dt><strong>Last validated</strong></dt>
<dd><?= e(date('Y-m-d H:i', strtotime($licenseStatus['license_validated_at']))) ?></dd>
<?php endif; ?>
</dl>
<form method="post" action="<?= e_attr($basePath) ?>/admin/settings/license-validate" style="margin-top:.5rem">
<?= $csrf->field() ?><button type="submit">Validate now</button>
<span class="muted">Re-checks the license immediately — use after fixing a domain registration.</span>
</form>
<?php else: ?>
<p class="muted">Not licensed. Install a module bundle with an embedded license key.</p>
<?php endif; ?>
</div>
<?php endif; ?>
<div class="card">
<h2 style="margin-top:0">System</h2>
<p class="muted">Cron runner token (for <code>cron.php?token=…</code> on hosts without CLI cron):</p>
<p><code><?= e($cronToken !== '' ? $cronToken : '(generated on first save)') ?></code></p>
</div>
