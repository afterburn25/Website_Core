<?php
/** @var array $submissions @var array $forms @var int $formId @var string $status @var string $search @var int $total @var int $page @var int $pages @var array $counts @var \Core\Security\Csrf $csrf @var string $basePath */
$query = array_filter(['form' => $formId ?: null, 'status' => $status ?: null, 'q' => $search ?: null], static fn ($value) => $value !== null && $value !== '');
$exportUrl = rtrim($basePath, '/') . '/admin/forms/export' . ($query ? '?' . http_build_query($query) : '');
$returnFields = static function () use ($formId, $status, $search, $page): string {
    return '<input type="hidden" name="return_form" value="' . $formId . '">'
        . '<input type="hidden" name="return_status" value="' . e_attr($status) . '">'
        . '<input type="hidden" name="return_q" value="' . e_attr($search) . '">'
        . '<input type="hidden" name="return_page" value="' . $page . '">';
};
?>
<section class="status-strip submission-summary" aria-label="Submission summary">
  <?php $allFilter = array_filter(['form' => $formId ?: null], static fn ($v) => $v !== null); ?>
  <a class="status-chip <?= $status === '' ? 'active' : '' ?>" href="<?= e_attr($basePath) ?>/admin/forms/submissions<?= $allFilter ? '?' . e_attr(http_build_query($allFilter)) : '' ?>"><strong><?= array_sum($counts) ?></strong><span>All</span></a>
  <?php foreach (['new' => 'New', 'read' => 'Read', 'archived' => 'Archived'] as $key => $label):
    $filter = array_filter(['form' => $formId ?: null, 'status' => $key], static fn ($v) => $v !== null);
  ?><a class="status-chip <?= $status === $key ? 'active' : '' ?>" href="<?= e_attr($basePath) ?>/admin/forms/submissions?<?= e_attr(http_build_query($filter)) ?>"><strong><?= (int) ($counts[$key] ?? 0) ?></strong><span><?= e($label) ?></span></a><?php endforeach; ?>
</section>

<section class="card submission-toolbar" aria-labelledby="submission-inbox-title">
  <div class="card-heading"><div><span class="section-kicker">Website responses</span><h2 id="submission-inbox-title">Submission inbox</h2><p class="card-subtitle">Review enquiries, record progress, inspect consent evidence, and export the filtered view.</p></div><div class="button-row"><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/forms">Manage forms</a><a class="btn" href="<?= e_attr($exportUrl) ?>">Export <?= $total ?> to CSV</a></div></div>
  <form class="filter-bar submission-filters" method="get" action="<?= e_attr($basePath) ?>/admin/forms/submissions">
    <div><label for="submission-search">Search responses</label><input id="submission-search" type="search" name="q" maxlength="190" value="<?= e_attr($search) ?>" placeholder="Name, email, or response…"></div>
    <div><label for="submission-form">Form</label><select id="submission-form" name="form"><option value="0">All forms</option><?php foreach ($forms as $form): ?><option value="<?= (int) $form['id'] ?>" <?= $formId === (int) $form['id'] ? 'selected' : '' ?>><?= e($form['name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="submission-status">Status</label><select id="submission-status" name="status"><option value="">Any status</option><?php foreach (['new' => 'New', 'read' => 'Read', 'archived' => 'Archived'] as $key => $label): ?><option value="<?= e_attr($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="button-row align-end"><button class="btn">Apply filters</button><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/forms/submissions">Reset</a></div>
  </form>
</section>

<form method="post" action="<?= e_attr($basePath) ?>/admin/forms/submissions/bulk" class="submission-workspace" data-submission-bulk data-no-unsaved>
  <?= $csrf->field() ?><?= $returnFields() ?>
  <div class="bulk-action-bar">
    <label class="bulk-select-all"><input type="checkbox" data-select-all-submissions><span>Select this page</span></label>
    <span class="selection-count" data-submission-selection>0 selected</span>
    <label class="sr-only" for="submission-bulk-action">Bulk action</label>
    <select id="submission-bulk-action" name="bulk_action" data-submission-bulk-action><option value="">Choose bulk action…</option><option value="read">Mark read</option><option value="new">Mark new</option><option value="archived">Archive</option><option value="delete">Delete permanently</option></select>
    <button class="btn secondary" data-submission-bulk-submit disabled>Apply</button>
    <span class="bulk-hint"><?= $total ?> matching submission<?= $total === 1 ? '' : 's' ?></span>
  </div>

  <?php if ($submissions === []): ?>
    <div class="card empty-state submission-empty"><strong>No matching submissions</strong><span>New website responses will appear here. Change or reset the filters if you expected an existing entry.</span><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/forms/submissions">Clear filters</a></div>
  <?php else: ?>
    <div class="submission-list">
    <?php foreach ($submissions as $entry):
      $data = json_decode((string) $entry['data_json'], true) ?: [];
      $consent = json_decode((string) ($entry['consent_json'] ?? ''), true) ?: [];
      $summary = array_slice($data, 0, 2, true);
      $notification = (string) ($entry['notification_status'] ?? '');
    ?>
      <article class="submission-card<?= $entry['status'] === 'new' ? ' is-new' : '' ?>">
        <div class="submission-card-head">
          <label class="submission-check"><input type="checkbox" name="submission_ids[]" value="<?= (int) $entry['id'] ?>" data-submission-check><span class="sr-only">Select entry <?= (int) $entry['id'] ?></span></label>
          <div class="submission-identity"><span class="submission-form-name"><?= e($entry['form_name']) ?></span><strong>Entry #<?= (int) $entry['id'] ?></strong></div>
          <dl class="submission-summary-data"><?php foreach ($summary as $label => $value): ?><div><dt><?= e(ucwords(str_replace('_', ' ', (string) $label))) ?></dt><dd><?= e(mb_strimwidth((string) $value, 0, 90, '…')) ?></dd></div><?php endforeach; ?></dl>
          <time datetime="<?= e_attr(date('c', strtotime((string) $entry['created_at']))) ?>"><strong><?= e(date('M j, Y', strtotime((string) $entry['created_at']))) ?></strong><span><?= e(date('g:i a', strtotime((string) $entry['created_at']))) ?></span></time>
          <span class="badge <?= $entry['status'] === 'new' ? 'warn' : ($entry['status'] === 'archived' ? 'neutral' : 'ok') ?>"><?= e(ucfirst((string) $entry['status'])) ?></span>
          <details class="submission-details"><summary class="btn small secondary">View details</summary><div class="submission-detail-panel">
            <div class="submission-detail-heading"><div><span class="section-kicker">Entry #<?= (int) $entry['id'] ?></span><h3><?= e($entry['form_name']) ?></h3></div><span class="badge <?= $entry['status'] === 'new' ? 'warn' : ($entry['status'] === 'archived' ? 'neutral' : 'ok') ?>"><?= e(ucfirst((string) $entry['status'])) ?></span></div>
            <section><h4>Submitted information</h4><dl class="submission-data"><?php foreach ($data as $label => $value): ?><div><dt><?= e(ucwords(str_replace('_', ' ', (string) $label))) ?></dt><dd><?= nl2br(e((string) $value)) ?></dd></div><?php endforeach; ?></dl></section>
            <div class="submission-evidence-grid"><section><h4>Consent evidence</h4><?php if ($consent === []): ?><p class="muted">This form did not contain checkbox or consent fields.</p><?php else: ?><dl class="evidence-list"><?php foreach ($consent as $item): if (!is_array($item)) continue; ?><div><dt><?= e((string) ($item['label'] ?? 'Consent')) ?></dt><dd><span class="badge <?= !empty($item['accepted']) ? 'ok' : 'neutral' ?>"><?= !empty($item['accepted']) ? 'Accepted' : 'Not accepted' ?></span><?php if (!empty($item['recorded_at'])): ?><small><?= e(date('M j, Y g:i a', strtotime((string) $item['recorded_at']))) ?></small><?php endif; ?></dd></div><?php endforeach; ?></dl><?php endif; ?></section>
              <section><h4>Notification</h4><?php if (!$entry['notification_message_id']): ?><p class="muted">No recipient was configured when this entry arrived.</p><?php else: ?><p><span class="badge <?= $notification === 'sent' ? 'ok' : ($notification === 'failed' ? 'bad' : 'warn') ?>"><?= e(ucfirst($notification ?: 'Queued')) ?></span></p><?php if ($notification === 'failed'): ?><p class="field-note">Delivery failed. Check System Health and mail settings; the submission remains safely stored.</p><?php endif; ?><?php endif; ?></section></div>
            <div class="submission-actions" role="group" aria-label="Actions for entry <?= (int) $entry['id'] ?>">
              <?php foreach (['new' => 'Mark new', 'read' => 'Mark read', 'archived' => 'Archive'] as $next => $label): if ($entry['status'] === $next) continue; ?>
                <button type="submit" class="btn small <?= $next === 'archived' ? 'secondary' : '' ?>" formaction="<?= e_attr($basePath) ?>/admin/forms/submissions/<?= (int) $entry['id'] ?>/<?= e_attr($next) ?>" name="single_action" value="<?= e_attr($next) ?>"><?= e($label) ?></button>
              <?php endforeach; ?>
              <button type="submit" class="btn small danger" formaction="<?= e_attr($basePath) ?>/admin/forms/submissions/<?= (int) $entry['id'] ?>/delete" data-confirm="Permanently delete this submission? This removes its responses and consent evidence and cannot be undone.">Delete permanently</button>
            </div>
          </div></details>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</form>

<?php if ($pages > 1): ?><nav class="pagination" aria-label="Submission pages">
  <?php for ($n = 1; $n <= $pages; $n++): $pageQuery = $query + ['page' => $n]; ?><a class="<?= $n === $page ? 'active' : '' ?>" <?= $n === $page ? 'aria-current="page"' : '' ?> href="<?= e_attr($basePath) ?>/admin/forms/submissions?<?= e_attr(http_build_query($pageQuery)) ?>"><?= $n ?></a><?php endfor; ?>
</nav><?php endif; ?>
