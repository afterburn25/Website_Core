<?php /** @var array|null $page @var string|null $error @var array $revisions @var array $blockDefinitions
  * @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<?php if ($error): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<?= $csrf->field() ?>
<label>Title</label>
<input name="title" value="<?= e_attr($page['title'] ?? '') ?>" required>
<label>Slug <span class="muted">(auto from title if blank)</span></label>
<input name="slug" value="<?= e_attr($page['slug'] ?? '') ?>">
<label>Body <span class="muted">(rich HTML; scripts/embeds are stripped on save)</span></label>
<textarea name="body"><?= e($page['body'] ?? '') ?></textarea>
<section class="builder-shell" aria-labelledby="builder-title">
<div class="card-heading"><div><h2 id="builder-title">Visual page composer</h2><p class="muted">Blocks replace the legacy body when at least one is present. Drag blocks to reorder them.</p></div>
<div class="button-row"><select id="block-type" aria-label="Block type">
<?php foreach ($blockDefinitions as $type => $definition): ?><option value="<?= e_attr($type) ?>"><?= e($definition['label']) ?><?= ($definition['package'] ?? 'core') !== 'core' ? ' · ' . e($definition['package']) : '' ?></option><?php endforeach; ?>
</select><button class="btn secondary" type="button" id="add-block">Add block</button></div></div>
<input type="hidden" name="blocks_json" id="blocks-json" value="<?= e_attr((string) ($page['blocks_json'] ?? '[]')) ?>">
<div id="blocks-list" class="blocks-list"></div>
<div id="blocks-empty" class="empty-state">No blocks yet. The legacy body above will be displayed.</div>
</section>
<label>Meta title</label>
<input name="meta_title" value="<?= e_attr($page['meta_title'] ?? '') ?>">
<label>Meta description</label>
<input name="meta_description" value="<?= e_attr($page['meta_description'] ?? '') ?>">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem;max-width:40rem">
<div>
<label>Canonical URL <span class="muted">(optional absolute URL)</span></label>
<input name="canonical" value="<?= e_attr($page['canonical'] ?? '') ?>">
</div>
<div>
<label>OG image media id <span class="muted">(from Media library)</span></label>
<input name="og_media_id" type="number" min="0" value="<?= e_attr((string) ($page['og_media_id'] ?? '')) ?>">
</div>
</div>

<label style="display:block;margin-top:.5rem">
<input type="checkbox" name="noindex" value="1" <?= !empty($page['noindex']) ? 'checked' : '' ?>>
Exclude this page from indexing (noindex, nofollow)
</label>
<div style="margin-top:1rem;display:flex;gap:.5rem">
<button class="btn" type="submit">Save draft</button>
</form>
<?php if ($page): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/preview">
<?= $csrf->field() ?><button class="btn secondary" type="submit">Preview link</button>
</form>
<?php endif; ?>
</div>
</div>

<script>
(() => {
  const definitions = <?= json_encode(array_map(fn(array $d): array => [
    'label' => $d['label'], 'fields' => $d['fields'], 'package' => $d['package'] ?? 'core'
  ], $blockDefinitions), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const input = document.getElementById('blocks-json');
  const list = document.getElementById('blocks-list');
  const empty = document.getElementById('blocks-empty');
  let blocks = [];
  try { blocks = JSON.parse(input.value || '[]'); if (!Array.isArray(blocks)) blocks = []; } catch (_) {}
  const sync = () => { input.value = JSON.stringify(blocks); empty.hidden = blocks.length > 0; };
  const makeId = () => 'b_' + Math.random().toString(36).slice(2,10);
  const move = (from,to) => { if(to<0||to>=blocks.length)return; const [b]=blocks.splice(from,1);blocks.splice(to,0,b);render(); };
  const render = () => {
    list.innerHTML='';
    blocks.forEach((block,index) => {
      const def=definitions[block.type]; if(!def)return;
      block.data=block.data||{};
      const card=document.createElement('article'); card.className='builder-block'; card.draggable=true;
      card.innerHTML='<div class="builder-block-head"><span class="drag-handle" title="Drag to reorder">⋮⋮</span><strong></strong><span class="badge"></span><div class="button-row"><button type="button" class="btn small secondary up">↑</button><button type="button" class="btn small secondary down">↓</button><button type="button" class="btn small secondary hide"></button><button type="button" class="btn small danger remove">Remove</button></div></div><div class="builder-fields"></div>';
      card.querySelector('strong').textContent=def.label;
      card.querySelector('.badge').textContent=def.package==='core'?'Core':def.package;
      card.querySelector('.hide').textContent=block.hidden?'Show':'Hide';
      card.classList.toggle('is-hidden',Boolean(block.hidden));
      const fields=card.querySelector('.builder-fields');
      Object.entries(def.fields||{}).forEach(([key,field]) => {
        const wrap=document.createElement('label'); wrap.textContent=field.label;
        if(['richtext','lines'].includes(field.type)) wrap.className='field-wide';
        let control;
        if(field.type==='select') { control=document.createElement('select'); (field.options||[]).forEach(v=>{const o=document.createElement('option');o.value=v;o.textContent=v;control.appendChild(o);}); }
        else if(['richtext','lines'].includes(field.type)) { control=document.createElement('textarea'); control.rows=field.type==='richtext'?5:4; }
        else { control=document.createElement('input'); control.type=field.type==='media'?'number':'text'; if(field.type==='media')control.min='0'; }
        control.value=block.data[key]||''; control.addEventListener('input',()=>{block.data[key]=control.value;sync();});
        wrap.appendChild(control); fields.appendChild(wrap);
      });
      card.querySelector('.up').onclick=()=>move(index,index-1); card.querySelector('.down').onclick=()=>move(index,index+1);
      card.querySelector('.hide').onclick=()=>{block.hidden=!block.hidden;render();};
      card.querySelector('.remove').onclick=()=>{blocks.splice(index,1);render();};
      card.addEventListener('dragstart',e=>e.dataTransfer.setData('text/plain',String(index)));
      card.addEventListener('dragover',e=>e.preventDefault());
      card.addEventListener('drop',e=>{e.preventDefault();move(Number(e.dataTransfer.getData('text/plain')),index);});
      list.appendChild(card);
    }); sync();
  };
  document.getElementById('add-block').onclick=()=>{const type=document.getElementById('block-type').value;blocks.push({id:makeId(),type,hidden:false,data:{}});render();};
  render();
})();
</script>

<?php if ($page): ?>
<div class="card">
<h2 style="margin-top:0">Publishing schedule</h2>
<p class="muted">Times use the site/server timezone. Leave either field blank when no automatic transition is needed.</p>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/schedule">
<?= $csrf->field() ?>
<div class="inline-fields">
<div><label>Publish at</label><input type="datetime-local" name="publish_at" value="<?= e_attr(!empty($page['publish_at']) ? date('Y-m-d\TH:i', strtotime($page['publish_at'])) : '') ?>"></div>
<div><label>Unpublish at</label><input type="datetime-local" name="unpublish_at" value="<?= e_attr(!empty($page['unpublish_at']) ? date('Y-m-d\TH:i', strtotime($page['unpublish_at'])) : '') ?>"></div>
<button class="btn" type="submit" style="align-self:end">Save schedule</button>
</div>
</form>
</div>
<?php endif; ?>

<?php if ($revisions !== []): ?>
<div class="card">
<h2 style="margin-top:0">Recent revisions</h2>
<table>
<tr><th>Saved</th><th>By</th><th>Title</th><th></th></tr>
<?php foreach ($revisions as $rev): ?>
<tr>
<td><?= e($rev['created_at']) ?></td><td><?= e((string) $rev['saved_by']) ?></td><td><?= e($rev['title']) ?></td>
<td>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/restore/<?= (int) $rev['id'] ?>">
<?= $csrf->field() ?><button class="btn small secondary">Restore as draft</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
