<?php
/** @var array $sites @var string $localCoreVersion @var \Core\Security\Csrf $csrf */
$active = count(array_filter($sites, static fn(array $site): bool => (bool) ($site['active'] ?? true)));
$healthy = count(array_filter($sites, static fn(array $site): bool => ($site['status'] ?? '') === 'healthy'));
$attention = count(array_filter($sites, static fn(array $site): bool => ($site['status'] ?? '') === 'error'));
$outdated = count(array_filter($sites, static fn(array $site): bool => !empty($site['core_version']) && version_compare((string) $site['core_version'], $localCoreVersion, '<')));
?>
<div class="metric-grid">
  <div class="metric"><span>Managed sites</span><strong><?= count($sites) ?></strong><small><?= $active ?> active connection<?= $active === 1 ? '' : 's' ?></small></div>
  <div class="metric"><span>Healthy</span><strong class="text-ok"><?= $healthy ?></strong><small>Latest authenticated check</small></div>
  <div class="metric"><span>Needs attention</span><strong class="<?= $attention ? 'text-bad' : 'text-ok' ?>"><?= $attention ?></strong><small>Connection or remote error</small></div>
  <div class="metric"><span>Older core</span><strong class="<?= $outdated ? 'text-warn' : 'text-ok' ?>"><?= $outdated ?></strong><small>Compared with this site · <?= e($localCoreVersion) ?></small></div>
</div>

<div class="card">
  <div class="card-heading"><div><h2>Client sites</h2><p class="muted">Monitor Website Core installations through a least-privilege <code>health.read</code> key. Credentials are encrypted with this installation’s app key and are never displayed.</p></div></div>
  <?php if (!$sites): ?>
    <div class="empty-state"><strong>No managed sites yet</strong><br>Add the first client connection below, then run its authenticated health check.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Site</th><th>Remote identity</th><th>Versions</th><th>Status</th><th>Last checked</th><th>Actions</th></tr></thead>
      <tbody><?php foreach ($sites as $site):
        $industries = json_decode((string) ($site['industry_versions_json'] ?? ''), true) ?: [];
        $isActive = (bool) ($site['active'] ?? true);
        $status = (string) ($site['status'] ?? 'unknown');
        $isOlder = !empty($site['core_version']) && version_compare((string) $site['core_version'], $localCoreVersion, '<');
      ?><tr>
        <td><strong><?= e($site['label']) ?></strong><br><a href="<?= e_url($site['base_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($site['base_url']) ?> ↗</a></td>
        <td><?= e($site['remote_site'] ?: 'Awaiting first check') ?></td>
        <td><strong>Core <?= e($site['core_version'] ?: '—') ?></strong><?php if ($isOlder): ?> <span class="badge warn">older</span><?php endif; ?><?php foreach ($industries as $industry): ?><br><small><?= e($industry['name'] ?? $industry['id'] ?? 'Industry') ?> <?= e($industry['version'] ?? '') ?></small><?php endforeach; ?></td>
        <td><span class="badge <?= $status === 'healthy' ? 'ok' : ($status === 'error' ? 'bad' : 'warn') ?>"><?= e($status) ?></span><?php if (!empty($site['last_latency_ms'])): ?><br><small><?= (int) $site['last_latency_ms'] ?> ms</small><?php endif; ?><?php if ((int) ($site['consecutive_failures'] ?? 0) > 0): ?><br><small class="err"><?= (int) $site['consecutive_failures'] ?> consecutive failure<?= (int) $site['consecutive_failures'] === 1 ? '' : 's' ?></small><?php endif; ?><?php if ($site['last_error']): ?><br><small class="err"><?= e($site['last_error']) ?></small><?php endif; ?></td>
        <td><?= e($site['last_checked_at'] ?: 'Never') ?><?php if (!empty($site['token_updated_at'])): ?><br><small>Key saved <?= e($site['token_updated_at']) ?></small><?php endif; ?></td>
        <td>
          <?php if ($isActive): ?><form method="post" style="display:inline"><?= $csrf->field() ?><input type="hidden" name="action" value="check"><input type="hidden" name="id" value="<?= (int) $site['id'] ?>"><button class="btn small">Check now</button></form><?php endif; ?>
          <details class="action-menu"><summary class="btn small secondary">Manage</summary><div class="action-popover">
            <form method="post"><?= $csrf->field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $site['id'] ?>"><label>Label<input name="label" required maxlength="120" value="<?= e_attr($site['label']) ?>"></label><label>HTTPS site URL<input name="base_url" type="url" required maxlength="500" value="<?= e_attr($site['base_url']) ?>"></label><label>New API key <small>(leave blank to keep current)</small><input name="api_token" type="password" autocomplete="new-password" placeholder="wcp_…"></label><button class="btn small">Save connection</button></form>
            <form method="post"><?= $csrf->field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $site['id'] ?>"><input type="hidden" name="enable" value="<?= $isActive ? '0' : '1' ?>"><button class="btn small secondary"><?= $isActive ? 'Pause monitoring' : 'Enable monitoring' ?></button></form>
            <form method="post" onsubmit="return confirm('Permanently remove this site and its encrypted credential?')"><?= $csrf->field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int) $site['id'] ?>"><button class="btn small danger">Remove connection</button></form>
          </div></details>
        </td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-heading"><div><h2>Add a managed site</h2><p class="muted">On the remote site, create an API key under Integrations with only the <code>health.read</code> scope. Use its canonical public HTTPS URL.</p></div><span class="badge ok">Encrypted at rest</span></div>
  <form method="post" class="agency-create-form"><?= $csrf->field() ?><input type="hidden" name="action" value="add"><div class="agency-create-grid"><div><label for="agency-site-label">Site label</label><input id="agency-site-label" name="label" required maxlength="120" placeholder="Acme Catering"></div><div><label for="agency-site-url">HTTPS site URL</label><input id="agency-site-url" name="base_url" type="url" required maxlength="500" placeholder="https://client.example.com"></div><div><label for="agency-api-token">Remote API key</label><input id="agency-api-token" name="api_token" type="password" required autocomplete="new-password" placeholder="wcp_…"></div></div><div class="agency-create-actions"><span class="field-note">The key is encrypted immediately and cannot be displayed again.</span><button class="btn" type="submit" data-busy-label="Adding connection…">Add connection</button></div></form>
</div>
