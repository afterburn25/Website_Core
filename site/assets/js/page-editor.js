(() => {
  'use strict';

  const form = document.getElementById('page-editor-form');
  if (!form) return;

  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('change', () => { dirty = true; });
  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', (event) => {
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });

  const titleInput = document.getElementById('page-title');
  const slugInput = document.getElementById('page-slug');
  const originalSlug = slugInput?.value || '';
  let slugEdited = originalSlug !== '';
  const slugify = (value) => value.toLowerCase().trim()
    .normalize('NFKD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 190);
  slugInput?.addEventListener('input', () => { slugEdited = slugInput.value !== ''; });
  titleInput?.addEventListener('input', () => {
    if (!slugEdited && slugInput) {
      slugInput.value = slugify(titleInput.value);
      slugInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  document.querySelectorAll('[data-count-for]').forEach((counter) => {
    const input = document.getElementById(counter.dataset.countFor);
    if (!input) return;
    const update = () => {
      const maximum = Number(input.getAttribute('maxlength') || 0);
      counter.textContent = maximum ? `${input.value.length} / ${maximum}` : '';
    };
    input.addEventListener('input', update);
    update();
  });

  const metaTitle = document.getElementById('meta-title');
  const metaDescription = document.getElementById('meta-description');
  const previewTitle = document.getElementById('seo-preview-title');
  const previewDescription = document.getElementById('seo-preview-description');
  const previewUrl = document.querySelector('.search-preview-url');
  const updatePreview = () => {
    if (previewTitle) previewTitle.textContent = metaTitle?.value || titleInput?.value || 'Page title';
    if (previewDescription) previewDescription.textContent = metaDescription?.value || 'Add a meta description to control this preview.';
    if (previewUrl) previewUrl.textContent = '/' + (slugInput?.value || 'page-url');
  };
  [metaTitle, metaDescription, titleInput, slugInput].forEach((input) => input?.addEventListener('input', updatePreview));

  const definitionsNode = document.getElementById('block-definitions');
  const input = document.getElementById('blocks-json');
  const list = document.getElementById('blocks-list');
  const empty = document.getElementById('blocks-empty');
  const status = document.getElementById('blocks-status');
  const typePicker = document.getElementById('block-type');
  const addButton = document.getElementById('add-block');
  if (!definitionsNode || !input || !list || !empty || !typePicker || !addButton) return;

  let definitions = {};
  let blocks = [];
  try { definitions = JSON.parse(definitionsNode.textContent || '{}'); } catch (_) {}
  try {
    blocks = JSON.parse(input.value || '[]');
    if (!Array.isArray(blocks)) blocks = [];
  } catch (_) { blocks = []; }

  const announce = (message) => { if (status) status.textContent = message; };
  const sync = () => {
    input.value = JSON.stringify(blocks);
    empty.hidden = blocks.length > 0;
    dirty = true;
  };
  const makeId = () => 'b_' + (crypto.randomUUID?.() || Math.random().toString(36).slice(2, 12));
  const move = (from, to) => {
    if (to < 0 || to >= blocks.length || from === to) return;
    const [block] = blocks.splice(from, 1);
    blocks.splice(to, 0, block);
    render();
    announce(`${definitions[block.type]?.label || 'Block'} moved to position ${to + 1}.`);
  };
  const fieldControl = (field, value) => {
    let control;
    if (field.type === 'select') {
      control = document.createElement('select');
      (field.options || []).forEach((optionValue) => {
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = optionValue;
        control.appendChild(option);
      });
    } else if (['richtext', 'lines'].includes(field.type)) {
      control = document.createElement('textarea');
      control.rows = field.type === 'richtext' ? 6 : 4;
    } else {
      control = document.createElement('input');
      control.type = ['media', 'number'].includes(field.type) ? 'number' : 'text';
      if (['media', 'number'].includes(field.type)) control.min = '0';
    }
    control.value = value ?? '';
    return control;
  };

  const render = () => {
    list.replaceChildren();
    blocks.forEach((block, index) => {
      const definition = definitions[block.type];
      if (!definition) return;
      block.data = block.data || {};
      const card = document.createElement('article');
      card.className = 'builder-block';
      card.draggable = true;
      card.classList.toggle('is-hidden', Boolean(block.hidden));

      const head = document.createElement('div');
      head.className = 'builder-block-head';
      const handle = document.createElement('span');
      handle.className = 'drag-handle';
      handle.setAttribute('aria-hidden', 'true');
      handle.textContent = '⋮⋮';
      const name = document.createElement('strong');
      name.textContent = definition.label;
      const packageBadge = document.createElement('span');
      packageBadge.className = 'badge';
      packageBadge.textContent = definition.package === 'core' ? 'Core' : definition.package;
      const actions = document.createElement('div');
      actions.className = 'button-row';
      const button = (label, className, action, disabled = false) => {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = className;
        element.textContent = label;
        element.disabled = disabled;
        element.addEventListener('click', action);
        return element;
      };
      actions.append(
        button('Move up', 'btn small secondary', () => move(index, index - 1), index === 0),
        button('Move down', 'btn small secondary', () => move(index, index + 1), index === blocks.length - 1),
        button(block.hidden ? 'Show' : 'Hide', 'btn small secondary', () => { block.hidden = !block.hidden; render(); announce(`${definition.label} ${block.hidden ? 'hidden' : 'shown'}.`); }),
        button('Remove', 'btn small danger', () => {
          if (!window.confirm(`Remove the ${definition.label} block?`)) return;
          blocks.splice(index, 1);
          render();
          announce(`${definition.label} removed.`);
        })
      );
      head.append(handle, name, packageBadge, actions);

      const fields = document.createElement('div');
      fields.className = 'builder-fields';
      Object.entries(definition.fields || {}).forEach(([key, field]) => {
        const label = document.createElement('label');
        label.textContent = field.label;
        if (['richtext', 'lines'].includes(field.type)) label.className = 'field-wide';
        const control = fieldControl(field, block.data[key]);
        control.addEventListener('input', () => { block.data[key] = control.value; sync(); });
        label.appendChild(control);
        fields.appendChild(label);
      });
      card.append(head, fields);
      card.addEventListener('dragstart', (event) => event.dataTransfer?.setData('text/plain', String(index)));
      card.addEventListener('dragover', (event) => event.preventDefault());
      card.addEventListener('drop', (event) => {
        event.preventDefault();
        move(Number(event.dataTransfer?.getData('text/plain')), index);
      });
      list.appendChild(card);
    });
    input.value = JSON.stringify(blocks);
    empty.hidden = blocks.length > 0;
  };

  addButton.addEventListener('click', () => {
    const type = typePicker.value;
    if (!definitions[type]) return;
    blocks.push({ id: makeId(), type, hidden: false, data: {} });
    render();
    dirty = true;
    announce(`${definitions[type].label} added at position ${blocks.length}.`);
    list.lastElementChild?.querySelector('input, textarea, select, button')?.focus();
  });
  render();
  dirty = false;
})();
