<div class="page-head">
  <div>
    <p class="section-kicker">Module workspace</p>
    <h1><?= e($resource['label']) ?></h1>
    <p>Manage module records with Core’s consistent validation, filtering, pagination, and accessible controls.</p>
  </div>
  <a class="btn" href="<?= e_attr($basePath . $resource['route']) ?>&id=0">Add record</a>
</div>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="workspace-grid resource-workspace">
  <section class="card">
    <form method="get" action="<?= e_attr($basePath . $resource['route']) ?>" class="filter-bar">
      <label>Search<input name="q" value="<?= e_attr($search) ?>"></label>
      <button class="btn secondary">Search</button>
    </form>
    <?php if (!$rows): ?>
      <div class="empty-state"><strong>No records found</strong><span>Add the first record or adjust the search.</span></div>
    <?php else: ?>
      <?php $canBulk=is_callable($resource['bulk']??null) && !empty($resource['bulk_actions']); $canReorder=is_callable($resource['reorder']??null); ?>
      <form method="post" action="<?= e_attr($basePath . $resource['route']) ?>" data-resource-list data-no-unsaved>
        <?= $csrf->field() ?>
        <input type="hidden" name="q" value="<?= e_attr($search) ?>"><input type="hidden" name="page" value="<?= $page ?>">
        <?php if ($canBulk): ?>
          <div class="resource-actions">
            <label>Bulk action
              <select name="bulk_action">
                <option value="">Select action</option>
                <?php foreach($resource['bulk_actions'] as $action=>$actionLabel): ?>
                  <option value="<?= e_attr((string)$action) ?>"><?= e((string)$actionLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button class="btn secondary" name="action" value="bulk" data-confirm="Apply this action to the selected records?">Apply</button>
          </div>
        <?php endif; ?>
        <div class="table-wrap"><table data-resource-table>
          <thead><tr>
            <?php if ($canBulk): ?><th class="resource-select"><input type="checkbox" data-resource-select-all aria-label="Select all visible records"></th><?php endif; ?>
            <?php if ($canReorder): ?><th class="resource-order">Order</th><?php endif; ?>
            <?php foreach($resource['columns'] as $column): ?><th><?= e($column['label']) ?></th><?php endforeach; ?>
            <th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach($rows as $row): $rowId=(int)($row['id']??0); ?>
            <tr data-resource-row>
              <?php if ($canBulk): ?><td class="resource-select"><?php if($rowId>0): ?><input type="checkbox" name="selected_ids[]" value="<?= $rowId ?>" data-resource-select aria-label="Select record <?= $rowId ?>"><?php endif; ?></td><?php endif; ?>
              <?php if ($canReorder): ?><td class="resource-order"><?php if($rowId>0): ?><input type="hidden" name="ordered_ids[]" value="<?= $rowId ?>"><span class="resource-order-buttons"><button type="button" class="btn small secondary" data-resource-up aria-label="Move record <?= $rowId ?> up">↑</button><button type="button" class="btn small secondary" data-resource-down aria-label="Move record <?= $rowId ?> down">↓</button></span><?php endif; ?></td><?php endif; ?>
              <?php foreach($resource['columns'] as $column): ?><td><?= e((string)($row[$column['name']]??'—')) ?></td><?php endforeach; ?>
              <td><?php if($rowId>0): ?><a class="btn small secondary" href="<?= e_attr($basePath . $resource['route']) ?>&id=<?= $rowId ?>">Edit</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php if ($canReorder): ?><div class="resource-order-save"><button class="btn secondary" name="action" value="reorder">Save visible order</button><span>Only the records on this page are submitted, in the order shown.</span></div><?php endif; ?>
      </form>
      <?php $pages=max(1,(int)ceil($total/$perPage)); if($pages>1): ?><nav class="pagination" aria-label="Resource pages"><?php for($p=1;$p<=$pages;$p++): ?><a class="<?= $p===$page?'active':'' ?>" href="<?= e_attr($basePath . $resource['route']) ?>&q=<?= rawurlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a><?php endfor; ?></nav><?php endif; ?>
    <?php endif; ?>
  </section>
  <section class="card">
    <div class="card-heading"><div><h2><?= $record?'Edit record':'Add record' ?></h2><p class="card-subtitle">Fields and persistence are defined by <?= e($resource['package']) ?>; Core supplies the workflow.</p></div></div>
    <form method="post" action="<?= e_attr($basePath . $resource['route']) ?>">
      <?= $csrf->field() ?><input type="hidden" name="id" value="<?= (int)($record['id']??0) ?>">
      <?php foreach($resource['fields'] as $field): $name=$field['name'];$type=$field['type']??'text';$value=$record[$name]??''; ?>
        <label><?= e($field['label']) ?>
          <?php if($type==='textarea'): ?><textarea name="<?= e_attr($name) ?>" <?= !empty($field['required'])?'required':'' ?>><?= e((string)$value) ?></textarea>
          <?php elseif($type==='select'): ?><select name="<?= e_attr($name) ?>" <?= !empty($field['required'])?'required':'' ?>><?php foreach((array)($field['options']??[]) as $key=>$label): ?><option value="<?= e_attr((string)$key) ?>" <?= (string)$value===(string)$key?'selected':'' ?>><?= e((string)$label) ?></option><?php endforeach; ?></select>
          <?php elseif($type==='checkbox'): ?><span class="switch-control"><input type="checkbox" name="<?= e_attr($name) ?>" value="1" <?= !empty($value)?'checked':'' ?>><span><?= e($field['label']) ?></span></span>
          <?php else: ?><input type="<?= e_attr($type) ?>" name="<?= e_attr($name) ?>" value="<?= e_attr((string)$value) ?>" <?= !empty($field['required'])?'required':'' ?>><?php endif; ?>
        </label>
      <?php endforeach; ?>
      <div class="button-row"><button class="btn" name="action" value="save">Save record</button><?php if($record && is_callable($resource['delete'])): ?><button class="btn danger" name="action" value="delete" data-confirm="Delete this record? This cannot be undone.">Delete</button><?php endif; ?></div>
    </form>
  </section>
</div>
