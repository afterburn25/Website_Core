<?php /** @var array $fields @var array $mailFields @var array $values @var string $cronToken @var bool $hasSmtpPassword @var array|null $licenseStatus @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/settings" data-busy-label="Saving settings…" data-protect-unsaved>
<?= $csrf->field() ?>
<div class="section-grid">
  <section class="card"><div class="card-heading"><div><h2>Website identity</h2><p class="card-subtitle">The business details Core shares with pages, themes, forms, and modules.</p></div></div>
    <?php foreach ($fields as $key => $meta): $name = str_replace('.', '_', $key); ?>
      <label for="<?= e_attr($name) ?>"><?= e($meta['label']) ?></label><input id="<?= e_attr($name) ?>" name="<?= e_attr($name) ?>" type="<?= e_attr($meta['type']) ?>" value="<?= e_attr($values[$key]) ?>">
    <?php endforeach; ?>
  </section>
  <section class="card"><div class="card-heading"><div><h2>Transactional email</h2><p class="card-subtitle">Used for account recovery, security alerts, and form notifications.</p></div></div>
    <label for="mail_transport">Mail transport</label><select id="mail_transport" name="mail_transport" data-toggle-target="[data-smtp-field]" data-toggle-value="smtp"><option value="none" <?= $values['mail.transport']==='none'?'selected':'' ?>>Disabled</option><option value="smtp" <?= $values['mail.transport']==='smtp'?'selected':'' ?>>SMTP server</option><option value="mail" <?= $values['mail.transport']==='mail'?'selected':'' ?>>PHP mail()</option></select>
    <?php foreach ($mailFields as $key => $meta): if ($key === 'mail.transport') continue; $name = str_replace('.', '_', $key); ?>
      <div <?= str_starts_with($key, 'smtp.') ? 'data-dependent-group' : '' ?>><?php if ($key === 'smtp.secure'): ?><label for="<?= e_attr($name) ?>"><?= e($meta['label']) ?></label><select id="<?= e_attr($name) ?>" name="<?= e_attr($name) ?>" data-smtp-field><option value="">None</option><option value="tls" <?= $values[$key]==='tls'?'selected':'' ?>>TLS / STARTTLS</option><option value="ssl" <?= $values[$key]==='ssl'?'selected':'' ?>>SSL</option></select>
      <?php else: ?><label for="<?= e_attr($name) ?>"><?= e($meta['label']) ?></label><input id="<?= e_attr($name) ?>" name="<?= e_attr($name) ?>" type="<?= e_attr($meta['type']) ?>" value="<?= e_attr($values[$key]) ?>" <?= str_starts_with($key, 'smtp.') ? 'data-smtp-field' : '' ?>><?php endif; ?></div>
    <?php endforeach; ?>
    <div data-dependent-group><label for="smtp_password">SMTP password <?= $hasSmtpPassword ? '<span class="muted">(stored — enter only to change)</span>' : '' ?></label><input id="smtp_password" name="smtp_password" type="password" autocomplete="new-password" data-smtp-field></div>
  </section>
</div>
<div class="action-bar"><span>Changes take effect immediately across Core and installed modules.</span><button class="btn" type="submit">Save settings</button></div>
</form>

<div class="section-grid">
  <section class="card"><div class="card-heading"><div><h2>Test email delivery</h2><p class="card-subtitle">Send one immediate message using the currently saved transport settings.</p></div></div>
    <form method="post" action="<?= e_attr($basePath) ?>/admin/settings" class="inline-fields" data-busy-label="Sending test…"><?= $csrf->field() ?><input type="hidden" name="action" value="test_mail"><div><label for="test-recipient">Recipient</label><input id="test-recipient" name="test_recipient" type="email" required placeholder="you@example.com"></div><button class="btn secondary" type="submit">Send test email</button></form>
  </section>
  <section class="card"><div class="card-heading"><div><h2>Scheduled tasks</h2><p class="card-subtitle">Use this private token with hosts that only support URL-based cron.</p></div></div><div class="copy-field"><input readonly value="<?= e_attr($cronToken !== '' ? $cronToken : '(generated on first save)') ?>" aria-label="Private cron token"><button type="button" class="btn secondary" data-copy-previous>Copy</button></div><p class="field-note">Run <code>cron.php?token=TOKEN</code> on your preferred schedule. Never publish this token.</p><form method="post" action="<?= e_attr($basePath) ?>/admin/settings"><?= $csrf->field() ?><input type="hidden" name="action" value="rotate_cron"><button class="btn small danger" data-confirm="Rotate the scheduled-task token? Existing cron URLs will stop working until updated.">Rotate token</button></form></section>
</div>

<?php if ($licenseStatus !== null): ?>
<section class="card"><div class="card-heading"><div><h2>License status</h2><p class="card-subtitle">Core-only test installations do not need a key. Commercial bundles report their activation here.</p></div><?php if ($licenseStatus['license_key'] !== null): ?><span class="badge <?= !empty($licenseStatus['license_valid']) && empty($licenseStatus['license_deactivated']) ? 'ok' : 'warn' ?>"><?= !empty($licenseStatus['license_deactivated']) ? 'Deactivated' : (!empty($licenseStatus['license_valid']) ? 'Active' : 'Needs validation') ?></span><?php endif; ?></div>
<?php if ($licenseStatus['license_key'] !== null): ?><dl class="status-list"><div><dt>License</dt><dd><code><?= e(substr($licenseStatus['license_key'], 0, 8)) ?>…</code></dd></div><div><dt>Domain</dt><dd><?= e($licenseStatus['license_domain'] ?? '—') ?></dd></div><div><dt>Last validated</dt><dd><?= $licenseStatus['license_validated_at'] ? e(date('M j, Y g:i a', strtotime($licenseStatus['license_validated_at']))) : 'Never' ?></dd></div><?php if (!empty($licenseStatus['license_error'])): ?><div><dt>Last error</dt><dd class="text-bad"><?= e($licenseStatus['license_error']) ?></dd></div><?php endif; ?></dl><form method="post" action="<?= e_attr($basePath) ?>/admin/settings/license-validate"><?= $csrf->field() ?><button class="btn secondary">Validate now</button></form><?php else: ?><div class="empty-state"><strong>No commercial license installed</strong><span>This is expected for a Core-only testing installation.</span></div><?php endif; ?>
</section>
<?php endif; ?>
