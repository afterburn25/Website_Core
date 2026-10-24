<?php
/** @var array $submissions @var array $forms @var int $formId @var string $status @var string $search @var int $total @var int $page @var int $pages @var array $counts @var \Core\Security\Csrf $csrf @var string $basePath */
$query = array_filter(['form' => $formId ?: null, 'status' => $status ?: null, 'q' => $search ?: null], static fn ($value) => $value !== null && $value !== '');
$exportUrl = rtrim($basePath, '/') . '/admin/forms/export' . ($query ? '?' . http_build_query($query) : '');
?>
<section class="status-strip" aria-label="Submission summary">
  <a class="status-chip <?= $status === '' ? 'active' : '' ?>" href="<?= e_attr($basePath) ?>/admin/forms/submissions<?= $formId ? '?form=' . $formId : '' ?>"><strong><?= (int) $total ?></strong><span>Showing</span></a>
  <?php foreach (['new' => 'New', 'read' => 'Read', 'archived' => 'Archived'] as $key => $label):
    $filter = array_filter(['form' => $formId ?: null, 'status' => $key], static fn ($v) => $v !== null);
  ?><a class="status-chip <?= $status === $key ? 'active' : '' ?>" href="<?= e_attr($basePath) ?>/admin/forms/submissions?<?= e_attr(http_build_query($filter)) ?>"><strong><?= (int) ($counts[$key] ?? 0) ?></strong><span><?= e($label) ?></span></a><?php endforeach; ?>
</section>

<div class="card">
  <div class="card-heading">
    <div><h2>Submission inbox</h2><p class="card-subtitle">Review enquiries, track their status, and export the current filtered view.</p></div>
    <div class="button-row"><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/forms">Manage forms</a><a class="btn" href="<?= e_attr($exportUrl) ?>">Export CSV</a></div>
  </div>
  <form class="filter-bar" method="get" action="<?= e_attr($basePath) ?>/admin/forms/submissions">
    <div><label for="submission-search">Search entries</label><input id="submission-search" type="search" name="q" value="<?= e_attr($search) ?>" placeholder="Name, email, or response…"></div>
    <div><label for="submission-form">Form</label><select id="submission-form" name="form"><option value="0">All forms</option><?php foreach ($forms as $form): ?><option value="<?= (int) $form['id'] ?>" <?= $formId === (int) $form['id'] ? 'selected' : '' ?>><?= e($form['name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="submission-status">Status</label><select id="submission-status" name="status"><option value="">Any status</option><?php foreach (['new' => 'New', 'read' => 'Read', 'archived' => 'Archived'] as $key => $label): ?><option value="<?= e_attr($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="button-row align-end"><button class="btn">Apply filters</button><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/forms/submissions">Reset</a></div>
  </form>
</div>

<div class="card">
<?php if ($submissions === []): ?>
  <div class="empty-state"><strong>No matching submissions</strong><span>New enquiries will appear here, or you can change the filters above.</span></div>
<?php else: ?>
  <table class="submission-table"><thead><tr><th>Received</th><th>Form</th><th>Submitted details</th><th>Status and actions</th></tr></thead><tbody>
  <?php foreach ($submissions as $entry): $data = json_decode((string) $entry['data_json'], true) ?: []; ?>
    <tr>
      <td><strong><?= e(date('M j, Y', strtotime((string) $entry['created_at']))) ?></strong><br><span class="muted"><?= e(date('g:i a', strtotime((string) $entry['created_at']))) ?></span></td>
      <td><?= e($entry['form_name']) ?><br><span class="muted">Entry #<?= (int) $entry['id'] ?></span></td>
      <td><dl class="submission-data"><?php foreach ($data as $label => $value): ?><div><dt><?= e(ucwords(str_replace('_', ' ', (string) $label))) ?></dt><dd><?= nl2br(e((string) $value)) ?></dd></div><?php endforeach; ?></dl></td>
      <td><span class="badge <?= $entry['status'] === 'new' ? 'warn' : ($entry['status'] === 'archived' ? 'neutral' : 'ok') ?>"><?= e(ucfirst((string) $entry['status'])) ?></span>
        <div class="button-row compact">
        <?php foreach (['new' => 'Mark new', 'read' => 'Mark read', 'archived' => 'Archive'] as $next => $label): if ($entry['status'] === $next) continue; ?>
          <form method="post" action="<?= e_attr($basePath) ?>/admin/forms/submissions/<?= (int) $entry['id'] ?>/<?= e_attr($next) ?>"><?= $csrf->field() ?><input type="hidden" name="return_form" value="<?= (int)$formId ?>"><input type="hidden" name="return_status" value="<?= e_attr($status) ?>"><input type="hidden" name="return_q" value="<?= e_attr($search) ?>"><input type="hidden" name="return_page" value="<?= (int)$page ?>"><button class="btn small <?= $next === 'archived' ? 'secondary' : '' ?>"><?= e($label) ?></button></form>
        <?php endforeach; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?></tbody></table>
<?php endif; ?>

<?php if ($pages > 1): ?><nav class="pagination" aria-label="Submission pages">
  <?php for ($n = 1; $n <= $pages; $n++): $pageQuery = $query + ['page' => $n]; ?><a class="<?= $n === $page ? 'active' : '' ?>" href="<?= e_attr($basePath) ?>/admin/forms/submissions?<?= e_attr(http_build_query($pageQuery)) ?>"><?= $n ?></a><?php endfor; ?>
</nav><?php endif; ?>
</div>
