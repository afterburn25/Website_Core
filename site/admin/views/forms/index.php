<?php
/** @var array $forms @var array|null $editing @var \Core\Security\Csrf $csrf @var string $basePath */
$editingFields = $editing ? (json_decode((string) ($editing['fields_json'] ?? '[]'), true) ?: []) : [];
$editingId = (int) ($editing['id'] ?? 0);
$isEditing = $editingId > 0;
$activeCount = count(array_filter($forms, static fn (array $form): bool => (bool) $form['active']));
$submissionCount = array_sum(array_map(static fn (array $form): int => (int) $form['submission_count'], $forms));
$newCount = array_sum(array_map(static fn (array $form): int => (int) ($form['new_count'] ?? 0), $forms));
?>
<section class="status-strip forms-summary" aria-label="Forms summary">
  <div class="status-chip"><strong><?= count($forms) ?></strong><span>Total forms</span></div>
  <div class="status-chip"><strong><?= $activeCount ?></strong><span>Accepting entries</span></div>
  <a class="status-chip<?= $newCount > 0 ? ' has-attention' : '' ?>" href="<?= e_attr($basePath) ?>/admin/forms/submissions?status=new"><strong><?= $newCount ?></strong><span>New</span></a>
  <a class="status-chip" href="<?= e_attr($basePath) ?>/admin/forms/submissions"><strong><?= $submissionCount ?></strong><span>All submissions</span></a>
</section>

<div class="forms-library-grid">
  <section class="card forms-library" aria-labelledby="forms-library-title">
    <div class="card-heading">
      <div><span class="section-kicker">Website intake</span><h2 id="forms-library-title">Form library</h2><p class="card-subtitle">Create focused website forms and review every response from one inbox.</p></div>
      <div class="button-row"><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/forms#form-editor">New form</a><a class="btn" href="<?= e_attr($basePath) ?>/admin/forms/submissions">Open inbox</a></div>
    </div>
    <?php if ($forms === []): ?>
      <div class="empty-state forms-empty"><strong>No forms yet</strong><span>Create a contact, enquiry, or quote-request form, then place it on a page with the Form block.</span><a class="btn" href="#form-editor">Create the first form</a></div>
    <?php else: ?>
      <div class="responsive-table"><table class="forms-table"><thead><tr><th>Form</th><th>Availability</th><th>Inbox</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
      <?php foreach ($forms as $form): ?>
        <tr<?= $editingId === (int) $form['id'] ? ' class="is-current"' : '' ?>>
          <td><strong><?= e($form['name']) ?></strong><span class="form-id">Form #<?= (int) $form['id'] ?></span></td>
          <td><span class="badge <?= $form['active'] ? 'ok' : 'neutral' ?>"><?= $form['active'] ? 'Accepting entries' : 'Paused' ?></span></td>
          <td><a class="inbox-count" href="<?= e_attr($basePath) ?>/admin/forms/submissions?form=<?= (int) $form['id'] ?>"><strong><?= (int) $form['submission_count'] ?></strong><span>total</span><?php if ((int) ($form['new_count'] ?? 0) > 0): ?><em><?= (int) $form['new_count'] ?> new</em><?php endif; ?></a></td>
          <td><div class="row-actions">
            <a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/forms?edit=<?= (int) $form['id'] ?>#form-editor">Edit</a>
            <a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/forms/submissions?form=<?= (int) $form['id'] ?>">Entries</a>
            <form method="post" action="<?= e_attr($basePath) ?>/admin/forms/<?= (int) $form['id'] ?>/<?= $form['active'] ? 'archive' : 'activate' ?>">
              <?= $csrf->field() ?>
              <button class="btn small <?= $form['active'] ? 'danger' : '' ?>" data-confirm="<?= $form['active'] ? 'Pause this form? It will stop displaying publicly, but all submissions will remain.' : 'Activate this form and make it available to page Form blocks?' ?>"><?= $form['active'] ? 'Pause' : 'Activate' ?></button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>

  <aside class="forms-guide">
    <span class="section-kicker">Three-step workflow</span>
    <h2>From form to follow-up</h2>
    <ol><li><strong>Build</strong><span>Add only the information your team needs.</span></li><li><strong>Place</strong><span>Choose the form from a page’s Form block.</span></li><li><strong>Respond</strong><span>Use the inbox, status tools, and exports to process enquiries.</span></li></ol>
    <p>Pausing a form removes it from public pages without deleting its settings or submission history.</p>
  </aside>
</div>

<section class="card form-workspace" id="form-editor" data-form-builder>
  <div class="card-heading form-workspace-heading">
    <div><span class="section-kicker"><?= $isEditing ? 'Form #' . $editingId : 'New form' ?></span><h2><?= $isEditing ? 'Edit “' . e((string) $editing['name']) . '”' : 'Build a form' ?></h2><p class="card-subtitle">Configure the visitor experience, internal notification, and response fields.</p></div>
    <?php if ($isEditing): ?><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/forms#form-editor">Create another</a><?php endif; ?>
  </div>
  <form method="post" action="<?= e_attr($basePath) ?>/admin/forms/save" data-busy-label="Saving form…" data-protect-unsaved>
    <?= $csrf->field() ?>
    <input type="hidden" name="id" value="<?= $editingId ?>">
    <input type="hidden" data-form-fields-json name="fields_json" value="<?= e_attr(json_encode($editingFields, JSON_UNESCAPED_SLASHES)) ?>">

    <div class="form-config-grid">
      <section class="form-settings-panel" aria-labelledby="form-settings-title">
        <div class="panel-heading"><span class="step-number">01</span><div><h3 id="form-settings-title">Form settings</h3><p>Name the form and control its public availability.</p></div></div>
        <label for="form-name">Internal form name</label>
        <input id="form-name" name="name" maxlength="190" value="<?= e_attr($editing['name'] ?? '') ?>" placeholder="Request a quote" required>
        <p class="field-note">Administrators see this name; visitors do not.</p>
        <label for="form-submit-label">Submit button label</label>
        <input id="form-submit-label" name="submit_label" maxlength="80" value="<?= e_attr($editing['submit_label'] ?? 'Submit') ?>" placeholder="Send request" required>
        <input type="hidden" name="active" value="0">
        <label class="toggle-control"><span><strong>Accept submissions</strong><small>Turn this off to keep the form and its history without showing it publicly.</small></span><input type="checkbox" name="active" value="1" <?= !isset($editing['active']) || $editing['active'] ? 'checked' : '' ?>><span class="toggle-track" aria-hidden="true"></span></label>
      </section>

      <section class="form-settings-panel" aria-labelledby="form-notification-title">
        <div class="panel-heading"><span class="step-number">02</span><div><h3 id="form-notification-title">Notification</h3><p>Optionally queue an email when a response arrives.</p></div></div>
        <label for="form-recipient">Notification recipient</label>
        <input id="form-recipient" type="email" maxlength="190" name="recipient" value="<?= e_attr($editing['recipient'] ?? '') ?>" placeholder="hello@example.com">
        <p class="field-note">Leave blank to store responses without email alerts.</p>
        <label for="form-subject">Email subject</label>
        <input id="form-subject" maxlength="190" name="subject" value="<?= e_attr($editing['subject'] ?? 'New website form submission') ?>">
      </section>

      <section class="form-settings-panel" aria-labelledby="form-response-title">
        <div class="panel-heading"><span class="step-number">03</span><div><h3 id="form-response-title">Visitor response</h3><p>Confirm that the submission was received.</p></div></div>
        <label for="form-success">Success message</label>
        <textarea id="form-success" maxlength="500" rows="4" name="success_message"><?= e($editing['success_message'] ?? 'Thank you. Your message has been received.') ?></textarea>
      </section>
    </div>

    <section class="form-builder-panel" aria-labelledby="form-fields-title">
      <div class="card-heading">
        <div><span class="section-kicker">Visitor fields</span><h3 id="form-fields-title">Build the response</h3><p class="card-subtitle">Drag fields into order or use the arrow buttons. Public preview updates as you work.</p></div>
        <div class="field-add-control"><label class="sr-only" for="new-field-type">New field type</label><select id="new-field-type" data-new-field-type><option value="text">Short text</option><option value="email">Email address</option><option value="tel">Phone number</option><option value="number">Number</option><option value="date">Date</option><option value="textarea">Long message</option><option value="select">Dropdown choice</option><option value="checkbox">Checkbox / consent</option></select><button type="button" class="btn" data-add-form-field>Add field</button></div>
      </div>
      <div class="form-builder-layout">
        <div>
          <div data-form-fields class="form-fields-list" aria-live="polite"></div>
          <div data-form-fields-empty class="empty-state"><strong>No fields added</strong><span>Choose a field type and add the first question visitors should answer.</span></div>
        </div>
        <aside class="form-preview-panel" aria-labelledby="form-preview-title"><div class="preview-browser-bar"><i></i><i></i><i></i><span>Public preview</span></div><div class="form-preview-body"><h4 id="form-preview-title">Your form</h4><p>Representative preview—your active theme supplies the final colors and spacing.</p><div data-form-preview></div></div></aside>
      </div>
      <p class="sr-only" data-form-builder-status aria-live="polite"></p>
    </section>

    <div class="action-bar form-save-bar"><span><strong data-form-field-count>0 fields</strong><small>Field names become column headings in exports and notification emails.</small></span><div class="button-row"><?php if ($isEditing): ?><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/forms">Discard changes</a><?php endif; ?><button class="btn">Save form</button></div></div>
  </form>
</section>
