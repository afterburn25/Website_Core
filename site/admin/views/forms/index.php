<?php /** @var array $forms @var array|null $editing @var \Core\Security\Csrf $csrf @var string $basePath */
$editingFields = $editing ? (json_decode((string) $editing['fields_json'], true) ?: []) : [];
?>
<div class="card">
<div class="card-heading"><div><h2>Form library</h2><p class="muted">Build reusable forms once, then place them on any page with the visual composer’s Form block.</p></div><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/forms/submissions">Submissions</a></div>
<?php if ($forms === []): ?><p class="empty-state">No forms yet.</p><?php else: ?>
<table><tr><th>ID</th><th>Name</th><th>Status</th><th>Submissions</th><th></th></tr>
<?php foreach ($forms as $form): ?><tr><td><?= (int) $form['id'] ?></td><td><?= e($form['name']) ?></td><td><span class="badge <?= $form['active'] ? 'ok' : 'warn' ?>"><?= $form['active'] ? 'active' : 'archived' ?></span></td><td><?= (int) $form['submission_count'] ?></td><td><a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/forms?edit=<?= (int) $form['id'] ?>">Edit</a> <a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/forms/submissions?form=<?= (int) $form['id'] ?>">View entries</a><?php if ($form['active']): ?><form method="post" action="<?= e_attr($basePath) ?>/admin/forms/<?= (int) $form['id'] ?>/archive" style="display:inline"><?= $csrf->field() ?><button class="btn small danger">Archive</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</table><?php endif; ?>
</div>

<div class="card">
<h2><?= $editing ? 'Edit form #' . (int) $editing['id'] : 'Create form' ?></h2>
<form method="post" action="<?= e_attr($basePath) ?>/admin/forms/save">
<?= $csrf->field() ?><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>"><input type="hidden" id="fields-json" name="fields_json" value="<?= e_attr(json_encode($editingFields, JSON_UNESCAPED_SLASHES)) ?>">
<div class="inline-fields"><div><label>Name</label><input name="name" value="<?= e_attr($editing['name'] ?? '') ?>" required></div><div><label>Notification recipient</label><input type="email" name="recipient" value="<?= e_attr($editing['recipient'] ?? '') ?>"></div></div>
<label>Email subject</label><input name="subject" value="<?= e_attr($editing['subject'] ?? 'New website form submission') ?>">
<label>Success message</label><input name="success_message" value="<?= e_attr($editing['success_message'] ?? 'Thank you. Your message has been received.') ?>">
<label><input type="checkbox" name="active" value="1" style="width:auto" <?= !isset($editing['active']) || $editing['active'] ? 'checked' : '' ?>> Active</label>
<div class="builder-shell"><div class="card-heading"><h3>Fields</h3><button type="button" class="btn secondary" id="add-field">Add field</button></div><div id="form-fields"></div><p id="form-fields-empty" class="empty-state">Add the first field.</p></div>
<button class="btn">Save form</button>
</form>
</div>
<script>
(()=>{const input=document.getElementById('fields-json'),list=document.getElementById('form-fields'),empty=document.getElementById('form-fields-empty');let fields=[];try{fields=JSON.parse(input.value||'[]');if(!Array.isArray(fields))fields=[]}catch(_){}const types=['text','email','tel','number','date','textarea','select','checkbox'];const sync=()=>{input.value=JSON.stringify(fields);empty.hidden=fields.length>0};const render=()=>{list.innerHTML='';fields.forEach((f,i)=>{const row=document.createElement('div');row.className='builder-block';row.innerHTML='<div class="builder-fields"><label>Label<input class="label"></label><label>Field name<input class="name"></label><label>Type<select class="type"></select></label><label>Options<textarea class="options" rows="2" placeholder="One per line"></textarea></label><label><input class="required" type="checkbox" style="width:auto"> Required</label><div class="button-row"><button type="button" class="btn small secondary up">↑</button><button type="button" class="btn small secondary down">↓</button><button type="button" class="btn small danger remove">Remove</button></div></div>';types.forEach(t=>{const o=document.createElement('option');o.value=t;o.textContent=t;row.querySelector('.type').appendChild(o)});for(const k of ['label','name','type','options']){const el=row.querySelector('.'+k);el.value=Array.isArray(f[k])?f[k].join('\n'):(f[k]||'');el.oninput=()=>{f[k]=el.value;sync()}}row.querySelector('.required').checked=Boolean(f.required);row.querySelector('.required').onchange=e=>{f.required=e.target.checked;sync()};row.querySelector('.up').onclick=()=>{if(i){[fields[i-1],fields[i]]=[fields[i],fields[i-1]];render()}};row.querySelector('.down').onclick=()=>{if(i<fields.length-1){[fields[i+1],fields[i]]=[fields[i],fields[i+1]];render()}};row.querySelector('.remove').onclick=()=>{fields.splice(i,1);render()};list.appendChild(row)});sync()};document.getElementById('add-field').onclick=()=>{fields.push({label:'New field',name:'new_field_'+(fields.length+1),type:'text',required:false,options:''});render()};render()})();
</script>
