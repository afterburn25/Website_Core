(() => {
  'use strict';

  const body = document.body;
  const sidebar = document.getElementById('admin-sidebar');
  const openButton = document.querySelector('[data-admin-nav-open]');
  const closeButtons = document.querySelectorAll('[data-admin-nav-close]');
  const stateKey = 'wc-admin-nav-state-v1';
  const scrollKey = 'wc-admin-nav-scroll-v1';
  const uiKey = 'wc-admin-ui-v2';
  let saved = {};
  let ui = {};

  try {
    saved = JSON.parse(localStorage.getItem(stateKey) || '{}') || {};
  } catch (_) {
    saved = {};
  }
  try { ui = JSON.parse(localStorage.getItem(uiKey) || '{}') || {}; } catch (_) { ui = {}; }

  document.querySelectorAll('[data-nav-group]').forEach((group) => {
    const key = group.dataset.navGroup;
    if (Object.prototype.hasOwnProperty.call(saved, key)) {
      group.open = Boolean(saved[key]);
    }
    group.addEventListener('toggle', () => {
      if (document.documentElement.classList.contains('admin-sidebar-collapsed')) return;
      saved[key] = group.open;
      try { localStorage.setItem(stateKey, JSON.stringify(saved)); } catch (_) {}
    });
  });

  if (sidebar) {
    try { sidebar.scrollTop = Number(localStorage.getItem(scrollKey) || 0); } catch (_) {}
    let timer;
    sidebar.addEventListener('scroll', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        try { localStorage.setItem(scrollKey, String(sidebar.scrollTop)); } catch (_) {}
      }, 80);
    }, { passive: true });
  }

  const setNavigation = (open) => {
    body.classList.toggle('admin-nav-open', open);
    openButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      sidebar?.querySelector('a, button, summary')?.focus({ preventScroll: true });
    } else {
      openButton?.focus({ preventScroll: true });
    }
  };

  openButton?.addEventListener('click', () => setNavigation(true));
  closeButtons.forEach((button) => button.addEventListener('click', () => setNavigation(false)));

  const saveUi = () => { try { localStorage.setItem(uiKey, JSON.stringify(ui)); } catch (_) {} };
  const collapseButton = document.querySelector('[data-sidebar-collapse]');
  const compactButton = document.querySelector('[data-ui-compact]');
  const syncUi = () => {
    document.documentElement.classList.toggle('admin-sidebar-collapsed', Boolean(ui.sidebarCollapsed));
    document.documentElement.classList.toggle('admin-compact', Boolean(ui.compact));
    document.querySelectorAll('[data-nav-group]').forEach((group) => {
      const key = group.dataset.navGroup;
      group.open = ui.sidebarCollapsed ? true : !Object.prototype.hasOwnProperty.call(saved, key) || Boolean(saved[key]);
    });
    collapseButton?.setAttribute('aria-pressed', ui.sidebarCollapsed ? 'true' : 'false');
    compactButton?.setAttribute('aria-pressed', ui.compact ? 'true' : 'false');
  };
  collapseButton?.addEventListener('click', () => { ui.sidebarCollapsed = !ui.sidebarCollapsed; saveUi(); syncUi(); });
  compactButton?.addEventListener('click', () => { ui.compact = !ui.compact; saveUi(); syncUi(); });
  syncUi();
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && body.classList.contains('admin-nav-open')) {
      setNavigation(false);
    }
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      const search = document.querySelector('.header-search input');
      if (search) {
        event.preventDefault();
        search.focus();
      }
    }
  });

  document.querySelectorAll('[data-dismiss-alert]').forEach((button) => {
    button.addEventListener('click', () => button.closest('[role="status"], [role="alert"]')?.remove());
  });

  const confirmDialog = document.querySelector('[data-confirm-dialog]');
  const confirmMessage = confirmDialog?.querySelector('[data-confirm-message]');
  let pendingConfirmation = null;
  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const prompt = event.submitter?.dataset?.confirm || form.dataset.confirm;
      if (!prompt || form.dataset.confirmed === 'true') {
        delete form.dataset.confirmed;
        return;
      }
      event.preventDefault();
      if (typeof HTMLDialogElement === 'undefined' || !(confirmDialog instanceof HTMLDialogElement) || typeof confirmDialog.showModal !== 'function') {
        if (window.confirm(prompt)) { form.dataset.confirmed = 'true'; form.requestSubmit(event.submitter); }
        else if (form._autoToggleControl) { form._autoToggleControl.checked = !form._autoToggleControl.checked; delete form.dataset.dirty; }
        form._autoToggleControl = null;
        return;
      }
      pendingConfirmation = { form, submitter: event.submitter, autoControl: form._autoToggleControl || null };
      if (confirmMessage) confirmMessage.textContent = prompt;
      confirmDialog.returnValue = 'cancel';
      confirmDialog.showModal();
    });
  });
  confirmDialog?.addEventListener('close', () => {
    if (confirmDialog.returnValue === 'confirm' && pendingConfirmation) {
      const { form, submitter } = pendingConfirmation;
      form.dataset.confirmed = 'true';
      form._autoToggleControl = null;
      form.requestSubmit(submitter || undefined);
    } else if (pendingConfirmation?.autoControl) {
      pendingConfirmation.autoControl.checked = !pendingConfirmation.autoControl.checked;
      delete pendingConfirmation.form.dataset.dirty;
      pendingConfirmation.form._autoToggleControl = null;
    }
    pendingConfirmation = null;
  });

  document.querySelectorAll('table').forEach((table) => {
    if (table.parentElement?.classList.contains('responsive-table') || table.closest('.table-wrap')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'responsive-table';
    table.parentNode?.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });

  document.querySelectorAll('form[method="post"]').forEach((form) => {
    const editable = form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]), select, textarea');
    const protect = form.hasAttribute('data-protect-unsaved') || editable.length >= 3 || Boolean(form.querySelector('textarea'));
    if (protect && !form.hasAttribute('data-no-unsaved')) {
      form.addEventListener('input', () => { form.dataset.dirty = 'true'; }, { once: true });
      form.addEventListener('change', () => { form.dataset.dirty = 'true'; }, { once: true });
    }
    form.addEventListener('submit', (event) => {
      if (event.defaultPrevented || !form.checkValidity()) return;
      delete form.dataset.dirty;
      const button = event.submitter instanceof HTMLButtonElement ? event.submitter : form.querySelector('button[type="submit"],button:not([type])');
      if (!button) return;
      button.classList.add('is-busy');
      button.setAttribute('aria-busy', 'true');
      const busyLabel = button.dataset.busyLabel || form.dataset.busyLabel;
      if (busyLabel) button.textContent = busyLabel;
      window.setTimeout(() => { button.disabled = true; }, 0);
    });
  });

  window.addEventListener('beforeunload', (event) => {
    if (!document.querySelector('form[data-dirty="true"]')) return;
    event.preventDefault();
    event.returnValue = '';
  });

  document.querySelectorAll('input[maxlength], textarea[maxlength]').forEach((field) => {
    const counter = document.createElement('span');
    counter.className = 'field-counter';
    const refresh = () => { counter.textContent = `${field.value.length} / ${field.maxLength}`; };
    field.insertAdjacentElement('afterend', counter);
    field.addEventListener('input', refresh);
    refresh();
  });

  document.querySelectorAll('input[type="password"]:not([data-no-visibility-toggle])').forEach((input) => {
    const wrapper = document.createElement('span');
    wrapper.className = 'password-field';
    input.parentNode?.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-visibility';
    button.textContent = 'Show';
    button.setAttribute('aria-label', 'Show password');
    button.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'Hide' : 'Show';
      button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} password`);
    });
    wrapper.appendChild(button);
  });

  document.querySelectorAll('[data-toggle-target]').forEach((controller) => {
    const selector = controller.dataset.toggleTarget;
    const enabledValue = controller.dataset.toggleValue;
    const notValue = controller.dataset.toggleNotValue;
    const targets = selector ? [...document.querySelectorAll(selector)] : [];
    const refresh = () => {
      const enabled = controller.type === 'checkbox'
        ? controller.checked
        : (notValue !== undefined ? controller.value !== notValue : controller.value === enabledValue);
      targets.forEach((target) => { target.disabled = !enabled; target.closest('[data-dependent-group]')?.classList.toggle('is-disabled', !enabled); });
    };
    controller.addEventListener('change', refresh);
    refresh();
  });

  document.querySelectorAll('[data-auto-submit]').forEach((control) => {
    control.addEventListener('change', () => {
      const form = control.closest('form');
      if (form) { form._autoToggleControl = control; form.requestSubmit(); }
    });
  });

  document.querySelectorAll('input[type="file"][data-image-preview]').forEach((input) => {
    input.addEventListener('change', () => {
      const file = input.files?.[0];
      const card = input.closest('.brand-asset-card') || input.parentElement;
      const preview = card?.querySelector(input.dataset.imagePreview);
      if (!file || !preview || !file.type.startsWith('image/')) return;
      const previousUrl = preview.dataset.previewUrl;
      if (previousUrl) URL.revokeObjectURL(previousUrl);
      const url = URL.createObjectURL(file);
      preview.dataset.previewUrl = url;
      preview.innerHTML = '';
      const image = document.createElement('img');
      image.src = url;
      image.alt = `Preview of ${file.name}`;
      preview.appendChild(image);
    });
  });

  let tableFilterNumber = 0;
  document.querySelectorAll('.responsive-table table').forEach((table) => {
    const rows = [...table.querySelectorAll('tbody tr')];
    if (rows.length < 8 || table.closest('[data-no-client-filter]')) return;
    tableFilterNumber += 1;
    const id = `table-filter-${tableFilterNumber}`;
    const tools = document.createElement('div');
    tools.className = 'table-tools';
    tools.innerHTML = `<label for="${id}">Filter visible rows</label><input id="${id}" type="search" placeholder="Type to filter this table"><span aria-live="polite"></span>`;
    table.closest('.responsive-table')?.insertAdjacentElement('beforebegin', tools);
    const input = tools.querySelector('input');
    const status = tools.querySelector('span');
    const filter = () => {
      const query = input.value.trim().toLocaleLowerCase();
      let visible = 0;
      rows.forEach((row) => {
        const match = query === '' || row.textContent.toLocaleLowerCase().includes(query);
        row.hidden = !match;
        if (match) visible += 1;
      });
      status.textContent = query ? `${visible} of ${rows.length} rows` : `${rows.length} rows`;
    };
    input.addEventListener('input', filter);
    filter();
  });

  document.querySelectorAll('[data-selection-scope]').forEach((scope) => {
    const summary = scope.querySelector('[data-selection-summary]');
    const boxes = [...scope.querySelectorAll('input[type="checkbox"][data-select-item]')];
    const selectAll = scope.querySelector('[data-select-all]');
    const actions = [...scope.querySelectorAll('[data-selection-action]')];
    const refresh = () => {
      const selected = boxes.filter((box) => box.checked).length;
      if (summary) summary.textContent = selected ? `${selected} selected` : 'None selected';
      if (selectAll instanceof HTMLInputElement) {
        selectAll.checked = boxes.length > 0 && selected === boxes.length;
        selectAll.indeterminate = selected > 0 && selected < boxes.length;
      }
      actions.forEach((action) => { action.disabled = selected === 0; });
    };
    boxes.forEach((box) => box.addEventListener('change', refresh));
    selectAll?.addEventListener('change', () => {
      boxes.forEach((box) => { box.checked = selectAll.checked; });
      refresh();
    });
    refresh();
  });

  document.querySelectorAll('[data-drop-zone]').forEach((zone) => {
    const input = zone.querySelector('input[type="file"]');
    const status = zone.querySelector('[data-file-selection]');
    if (!input) return;
    const refreshFile = () => {
      const file = input.files?.[0];
      if (!status) return;
      status.textContent = file
        ? `${file.name} · ${file.size < 1048576 ? `${Math.max(1, Math.round(file.size / 1024))} KB` : `${(file.size / 1048576).toFixed(1)} MB`}`
        : 'No file selected yet.';
      status.classList.toggle('has-file', Boolean(file));
    };
    input.addEventListener('change', refreshFile);
    ['dragenter', 'dragover'].forEach((name) => zone.addEventListener(name, (event) => {
      event.preventDefault();
      zone.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((name) => zone.addEventListener(name, (event) => {
      event.preventDefault();
      zone.classList.remove('is-dragging');
    }));
    zone.addEventListener('drop', (event) => {
      if (event.dataTransfer?.files?.length) {
        input.files = event.dataTransfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  });

  document.querySelectorAll('[data-focal-editor]').forEach((editor) => {
    const x = editor.querySelector('[data-focal-x]');
    const y = editor.querySelector('[data-focal-y]');
    const xOutput = editor.querySelector('[data-focal-x-output]');
    const yOutput = editor.querySelector('[data-focal-y-output]');
    const refresh = () => {
      const xValue = Math.max(0, Math.min(100, Number(x?.value || 50)));
      const yValue = Math.max(0, Math.min(100, Number(y?.value || 50)));
      editor.style.setProperty('--focal-x', `${xValue}%`);
      editor.style.setProperty('--focal-y', `${yValue}%`);
      if (xOutput) xOutput.textContent = `${Math.round(xValue)}%`;
      if (yOutput) yOutput.textContent = `${Math.round(yValue)}%`;
    };
    x?.addEventListener('input', refresh);
    y?.addEventListener('input', refresh);
    refresh();
  });

  document.querySelectorAll('.media-card-actions details').forEach((details) => {
    details.addEventListener('toggle', () => {
      if (!details.open) return;
      document.querySelectorAll('.media-card-actions details[open]').forEach((other) => {
        if (other !== details) other.removeAttribute('open');
      });
    });
  });

  document.querySelectorAll('[data-copy-previous]').forEach((button) => {
    button.addEventListener('click', async () => {
      const input = button.previousElementSibling;
      if (!(input instanceof HTMLInputElement)) return;
      try {
        await navigator.clipboard.writeText(input.value);
        const old = button.textContent;
        button.textContent = 'Copied';
        window.setTimeout(() => { button.textContent = old; }, 1600);
      } catch (_) {
        input.select();
        document.execCommand('copy');
      }
    });
  });

  document.querySelectorAll('[data-menu-editor]').forEach((editor) => {
    const tree = editor.querySelector('[data-menu-tree]');
    const rootDrop = editor.querySelector('[data-root-drop]');
    const save = editor.querySelector('[data-save-tree]');
    const reset = editor.querySelector('[data-reset-tree]');
    const status = editor.querySelector('[data-tree-status]');
    const json = editor.querySelector('[data-tree-json]');
    const treeForm = editor.querySelector('[data-tree-form]');
    const maxDepth = Number(editor.dataset.maxMenuDepth || 5);
    if (!tree || !rootDrop || !save || !status || !json || !treeForm) return;
    let dragging = null;
    let dragArmed = false;
    let dirty = false;

    const nodesIn = (list) => [...list.children].filter((node) => node.matches('[data-menu-node]'));
    const serialize = (list) => nodesIn(list).map((node) => ({
      id: Number(node.dataset.id),
      children: serialize(node.querySelector(':scope > .menu-children')),
    }));
    const depthOf = (node) => {
      let depth = 0;
      let parent = node.parentElement?.closest('[data-menu-node]');
      while (parent) { depth += 1; parent = parent.parentElement?.closest('[data-menu-node]'); }
      return depth;
    };
    const branchHeight = (node) => {
      const children = nodesIn(node.querySelector(':scope > .menu-children'));
      return children.length === 0 ? 0 : 1 + Math.max(...children.map(branchHeight));
    };
    const canPlace = (node, depth) => depth + branchHeight(node) <= maxDepth;
    const clearDropState = () => {
      editor.querySelectorAll('.drop-before,.drop-inside,.drop-after,.is-over').forEach((node) => {
        node.classList.remove('drop-before', 'drop-inside', 'drop-after', 'is-over');
        delete node.dataset.dropMode;
      });
    };
    const refreshControls = () => {
      editor.querySelectorAll('[data-menu-node]').forEach((node) => {
        const parentNode = node.parentElement?.closest('[data-menu-node]');
        const previous = node.previousElementSibling;
        node.querySelector(':scope > .menu-card .move-up').disabled = !previous;
        node.querySelector(':scope > .menu-card .move-down').disabled = !node.nextElementSibling;
        node.querySelector(':scope > .menu-card .indent').disabled = !previous || !canPlace(node, depthOf(previous) + 1);
        node.querySelector(':scope > .menu-card .outdent').disabled = !parentNode;
      });
    };
    const changed = () => {
      dirty = true;
      json.value = JSON.stringify(serialize(tree));
      save.disabled = false;
      if (reset) reset.hidden = false;
      status.textContent = 'Unsaved structure changes — save the menu before editing link details.';
      refreshControls();
    };
    const rejectDepth = () => {
      status.textContent = `That move would exceed the ${maxDepth + 1}-level menu limit.`;
    };

    editor.querySelectorAll('[data-menu-node]').forEach((node) => {
      const grip = node.querySelector(':scope > .menu-card .drag-grip');
      const card = node.querySelector(':scope > .menu-card');
      grip?.addEventListener('pointerdown', () => { dragArmed = true; });
      grip?.addEventListener('pointerup', () => { if (!dragging) dragArmed = false; });
      node.addEventListener('dragstart', (event) => {
        if (event.target !== node) return;
        if (!dragArmed) { event.preventDefault(); return; }
        dragging = node;
        node.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
      });
      node.addEventListener('dragend', (event) => {
        if (event.target !== node) return;
        node.classList.remove('is-dragging');
        dragging = null;
        dragArmed = false;
        clearDropState();
      });
      card?.addEventListener('dragover', (event) => {
        event.preventDefault();
        clearDropState();
        if (!dragging || dragging === node || dragging.contains(node)) return;
        const rect = card.getBoundingClientRect();
        const ratio = (event.clientY - rect.top) / Math.max(1, rect.height);
        const mode = ratio < .28 ? 'before' : (ratio > .72 ? 'after' : 'inside');
        const destinationDepth = mode === 'inside' ? depthOf(node) + 1 : depthOf(node);
        if (!canPlace(dragging, destinationDepth)) { rejectDepth(); return; }
        node.dataset.dropMode = mode;
        node.classList.add(`drop-${mode}`);
      });
      card?.addEventListener('dragleave', (event) => {
        if (!card.contains(event.relatedTarget)) clearDropState();
      });
      card?.addEventListener('drop', (event) => {
        event.preventDefault();
        const mode = node.dataset.dropMode;
        clearDropState();
        if (!dragging || !mode || dragging === node || dragging.contains(node)) return;
        if (mode === 'inside') {
          node.querySelector(':scope > .menu-children').appendChild(dragging);
        } else if (mode === 'before') {
          node.parentElement.insertBefore(dragging, node);
        } else {
          node.parentElement.insertBefore(dragging, node.nextElementSibling);
        }
        changed();
      });
      node.querySelector(':scope > .menu-card .move-up')?.addEventListener('click', () => {
        const previous = node.previousElementSibling;
        if (previous) { node.parentElement.insertBefore(node, previous); changed(); }
      });
      node.querySelector(':scope > .menu-card .move-down')?.addEventListener('click', () => {
        const next = node.nextElementSibling;
        if (next) { node.parentElement.insertBefore(next, node); changed(); }
      });
      node.querySelector(':scope > .menu-card .indent')?.addEventListener('click', () => {
        const previous = node.previousElementSibling;
        if (!previous) return;
        if (!canPlace(node, depthOf(previous) + 1)) { rejectDepth(); return; }
        previous.querySelector(':scope > .menu-children').appendChild(node);
        changed();
      });
      node.querySelector(':scope > .menu-card .outdent')?.addEventListener('click', () => {
        const parentNode = node.parentElement?.closest('[data-menu-node]');
        if (parentNode) { parentNode.parentElement.insertBefore(node, parentNode.nextElementSibling); changed(); }
      });
    });

    rootDrop.addEventListener('dragover', (event) => {
      event.preventDefault();
      clearDropState();
      if (dragging) rootDrop.classList.add('is-over');
    });
    rootDrop.addEventListener('dragleave', () => rootDrop.classList.remove('is-over'));
    rootDrop.addEventListener('drop', (event) => {
      event.preventDefault();
      rootDrop.classList.remove('is-over');
      if (dragging) { tree.appendChild(dragging); changed(); }
    });
    treeForm.addEventListener('submit', () => {
      dirty = false;
      json.value = JSON.stringify(serialize(tree));
    });
    editor.querySelectorAll('[data-menu-detail-form]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        status.textContent = 'Save or discard the menu structure before editing, adding, or removing links.';
        save.focus();
      }, true);
    });

    const wireSource = (form) => {
      const page = form.querySelector('select[name="page_id"]');
      const url = form.querySelector('input[name="url"]');
      const label = form.querySelector('input[name="label"]');
      if (!page || !url) return;
      const sync = (changedByUser = false) => {
        const linked = page.value !== '';
        if (linked && !url.disabled) url.dataset.customUrl = url.value;
        url.disabled = linked;
        if (linked) {
          url.value = '';
          if (changedByUser && label && label.value.trim() === '') {
            label.value = page.selectedOptions[0]?.dataset.pageTitle || '';
          }
        } else if (changedByUser && url.value === '' && url.dataset.customUrl) {
          url.value = url.dataset.customUrl;
        }
      };
      page.addEventListener('change', () => sync(true));
      sync(false);
    };
    editor.querySelectorAll('[data-menu-detail-form]').forEach(wireSource);
    json.value = JSON.stringify(serialize(tree));
    refreshControls();
    window.addEventListener('beforeunload', (event) => {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
  });

  document.querySelectorAll('[data-form-builder]').forEach((builder) => {
    const form = builder.querySelector('form');
    const json = builder.querySelector('[data-form-fields-json]');
    const list = builder.querySelector('[data-form-fields]');
    const empty = builder.querySelector('[data-form-fields-empty]');
    const count = builder.querySelector('[data-form-field-count]');
    const status = builder.querySelector('[data-form-builder-status]');
    const preview = builder.querySelector('[data-form-preview]');
    const typePicker = builder.querySelector('[data-new-field-type]');
    const add = builder.querySelector('[data-add-form-field]');
    if (!form || !json || !list || !add) return;

    const types = {
      text: 'Short text', email: 'Email address', tel: 'Phone number', number: 'Number',
      date: 'Date', textarea: 'Long message', select: 'Dropdown choice', checkbox: 'Checkbox / consent',
    };
    const autocomplete = {
      '': 'No browser suggestion', name: 'Full name', 'given-name': 'First name',
      'family-name': 'Last name', organization: 'Organization', email: 'Email',
      tel: 'Phone', 'street-address': 'Street address', 'address-level2': 'City',
      'address-level1': 'State / region', 'postal-code': 'Postal code', 'country-name': 'Country',
    };
    let fields = [];
    let dragging = null;
    let initialized = false;
    try {
      fields = JSON.parse(json.value || '[]');
      if (!Array.isArray(fields)) fields = [];
    } catch (_) { fields = []; }

    const announce = (message) => { if (status) status.textContent = message; };
    const machineName = (value) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 80);
    const uniqueName = (seed) => {
      const root = machineName(seed) || 'field';
      let candidate = root;
      let number = 2;
      while (fields.some((field) => field.name === candidate)) candidate = `${root}_${number++}`;
      return candidate;
    };
    const serialized = () => fields.map((field) => ({
      label: String(field.label || ''),
      name: String(field.name || ''),
      type: types[field.type] ? field.type : 'text',
      required: Boolean(field.required),
      options: Array.isArray(field.options) ? field.options : String(field.options || '').split(/\r?\n/).filter(Boolean),
      placeholder: String(field.placeholder || ''),
      help: String(field.help || ''),
      autocomplete: String(field.autocomplete || ''),
    }));
    const sync = () => {
      json.value = JSON.stringify(serialized());
      if (initialized) json.dispatchEvent(new Event('input', { bubbles: true }));
      if (empty) empty.hidden = fields.length > 0;
      if (count) count.textContent = `${fields.length} field${fields.length === 1 ? '' : 's'}`;
    };
    const previewField = (field) => {
      const wrap = document.createElement('div');
      wrap.className = 'preview-form-field';
      const label = document.createElement('span');
      label.textContent = (field.label || 'Untitled field') + (field.required ? ' *' : '');
      let control;
      if (field.type === 'textarea') control = document.createElement('textarea');
      else if (field.type === 'select') {
        control = document.createElement('select');
        const option = document.createElement('option');
        option.textContent = (field.options || [])[0] || 'Choose…';
        control.appendChild(option);
      } else {
        control = document.createElement('input');
        control.type = field.type === 'checkbox' ? 'checkbox' : field.type;
      }
      control.disabled = true;
      if (field.placeholder && field.type !== 'checkbox') control.placeholder = field.placeholder;
      if (field.type === 'checkbox') {
        wrap.classList.add('is-checkbox');
        wrap.append(control, label);
      } else {
        wrap.append(label, control);
      }
      if (field.help) {
        const help = document.createElement('small');
        help.textContent = field.help;
        wrap.appendChild(help);
      }
      return wrap;
    };
    const renderPreview = () => {
      if (!preview) return;
      preview.innerHTML = '';
      fields.forEach((field) => preview.appendChild(previewField(field)));
      if (fields.length) {
        const button = document.createElement('button');
        button.type = 'button';
        button.disabled = true;
        button.textContent = form.querySelector('[name="submit_label"]')?.value || 'Submit';
        preview.appendChild(button);
      } else {
        const message = document.createElement('p');
        message.className = 'muted';
        message.textContent = 'Add a field to see the public form preview.';
        preview.appendChild(message);
      }
    };
    const makeSelect = (values, selected, className) => {
      const select = document.createElement('select');
      select.className = className;
      Object.entries(values).forEach(([value, label]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        option.selected = value === selected;
        select.appendChild(option);
      });
      return select;
    };
    const labeled = (text, control, wide = false) => {
      const label = document.createElement('label');
      if (wide) label.className = 'field-wide';
      const title = document.createElement('span');
      title.textContent = text;
      label.append(title, control);
      return label;
    };
    const move = (from, to) => {
      if (from === to || from < 0 || to < 0 || from >= fields.length || to >= fields.length) return;
      const [field] = fields.splice(from, 1);
      fields.splice(to, 0, field);
      announce(`${field.label || 'Field'} moved to position ${to + 1}.`);
      render();
    };
    const render = () => {
      list.innerHTML = '';
      fields.forEach((field, index) => {
        field.type = types[field.type] ? field.type : 'text';
        field.options = Array.isArray(field.options) ? field.options : String(field.options || '').split(/\r?\n/).filter(Boolean);
        const block = document.createElement('article');
        block.className = 'builder-block form-field-card';
        block.dataset.fieldIndex = String(index);

        const head = document.createElement('div');
        head.className = 'builder-block-head';
        const handle = document.createElement('button');
        handle.type = 'button';
        handle.className = 'drag-handle';
        handle.draggable = true;
        handle.setAttribute('aria-label', `Drag field ${index + 1}`);
        handle.title = 'Drag to reorder';
        handle.textContent = '⋮⋮';
        const identity = document.createElement('div');
        identity.className = 'field-card-identity';
        const position = document.createElement('span');
        position.textContent = `Field ${index + 1}`;
        const title = document.createElement('strong');
        title.textContent = field.label || 'Untitled field';
        identity.append(position, title);
        const controls = document.createElement('div');
        controls.className = 'button-row compact';
        const up = document.createElement('button');
        up.type = 'button'; up.className = 'btn small secondary'; up.textContent = '↑'; up.title = 'Move up';
        up.setAttribute('aria-label', `Move field ${index + 1} up`); up.disabled = index === 0;
        const down = document.createElement('button');
        down.type = 'button'; down.className = 'btn small secondary'; down.textContent = '↓'; down.title = 'Move down';
        down.setAttribute('aria-label', `Move field ${index + 1} down`); down.disabled = index === fields.length - 1;
        const remove = document.createElement('button');
        remove.type = 'button'; remove.className = 'btn small danger'; remove.textContent = 'Remove';
        controls.append(up, down, remove);
        head.append(handle, identity, controls);

        const body = document.createElement('div');
        body.className = 'builder-fields form-field-settings';
        const labelInput = document.createElement('input');
        labelInput.value = field.label || ''; labelInput.maxLength = 190; labelInput.required = true;
        const nameInput = document.createElement('input');
        nameInput.value = field.name || ''; nameInput.maxLength = 80; nameInput.pattern = '[a-z0-9_]+';
        nameInput.required = true;
        const typeInput = makeSelect(types, field.type, 'field-type');
        const placeholderInput = document.createElement('input');
        placeholderInput.value = field.placeholder || ''; placeholderInput.maxLength = 190;
        const helpInput = document.createElement('input');
        helpInput.value = field.help || ''; helpInput.maxLength = 300;
        const autocompleteInput = makeSelect(autocomplete, field.autocomplete || '', 'field-autocomplete');
        const optionsInput = document.createElement('textarea');
        optionsInput.rows = 4;
        optionsInput.value = field.options.join('\n');
        optionsInput.placeholder = 'One choice per line';
        const optionsLabel = labeled('Dropdown options', optionsInput, true);
        optionsLabel.classList.add('form-field-options');
        optionsLabel.hidden = field.type !== 'select';
        const requiredLabel = document.createElement('label');
        requiredLabel.className = 'toggle-control compact field-wide';
        const requiredCopy = document.createElement('span');
        requiredCopy.innerHTML = '<strong>Required field</strong><small>Visitors must answer before submitting.</small>';
        const requiredInput = document.createElement('input');
        requiredInput.type = 'checkbox'; requiredInput.checked = Boolean(field.required);
        const track = document.createElement('span');
        track.className = 'toggle-track'; track.setAttribute('aria-hidden', 'true');
        requiredLabel.append(requiredCopy, requiredInput, track);
        body.append(
          labeled('Visitor label', labelInput),
          labeled('Field name', nameInput),
          labeled('Input type', typeInput),
          labeled('Browser autocomplete', autocompleteInput),
          labeled('Placeholder', placeholderInput),
          labeled('Help text', helpInput),
          optionsLabel,
          requiredLabel,
        );
        block.append(head, body);
        list.appendChild(block);

        const update = (key, value) => { field[key] = value; sync(); renderPreview(); };
        labelInput.addEventListener('input', () => {
          const priorGenerated = !field.name || /^field(_\d+)?$/.test(field.name) || field.name === machineName(field.label || '');
          field.label = labelInput.value;
          title.textContent = field.label || 'Untitled field';
          if (priorGenerated) { field.name = uniqueName(field.label); nameInput.value = field.name; }
          sync(); renderPreview();
        });
        nameInput.addEventListener('input', () => update('name', machineName(nameInput.value)));
        nameInput.addEventListener('blur', () => { nameInput.value = field.name || ''; });
        typeInput.addEventListener('change', () => {
          field.type = typeInput.value;
          optionsLabel.hidden = field.type !== 'select';
          sync(); renderPreview();
        });
        placeholderInput.addEventListener('input', () => update('placeholder', placeholderInput.value));
        helpInput.addEventListener('input', () => update('help', helpInput.value));
        autocompleteInput.addEventListener('change', () => update('autocomplete', autocompleteInput.value));
        optionsInput.addEventListener('input', () => update('options', optionsInput.value.split(/\r?\n/).map((value) => value.trim()).filter(Boolean)));
        requiredInput.addEventListener('change', () => update('required', requiredInput.checked));
        up.addEventListener('click', () => move(index, index - 1));
        down.addEventListener('click', () => move(index, index + 1));
        remove.addEventListener('click', () => {
          if (remove.dataset.armed !== 'true') {
            remove.dataset.armed = 'true';
            remove.textContent = 'Confirm remove';
            announce(`Press Confirm remove to delete ${field.label || 'this field'}.`);
            window.setTimeout(() => {
              if (!remove.isConnected) return;
              delete remove.dataset.armed;
              remove.textContent = 'Remove';
            }, 4000);
            return;
          }
          fields.splice(index, 1);
          announce(`${field.label || 'Field'} removed.`);
          render();
        });
        handle.addEventListener('dragstart', (event) => {
          dragging = index;
          block.classList.add('is-dragging');
          event.dataTransfer.effectAllowed = 'move';
        });
        handle.addEventListener('dragend', () => {
          dragging = null;
          block.classList.remove('is-dragging');
          list.querySelectorAll('.is-drop-before,.is-drop-after').forEach((node) => node.classList.remove('is-drop-before', 'is-drop-after'));
        });
        block.addEventListener('dragover', (event) => {
          if (dragging === null || dragging === index) return;
          event.preventDefault();
          const before = event.clientY < block.getBoundingClientRect().top + block.offsetHeight / 2;
          block.classList.toggle('is-drop-before', before);
          block.classList.toggle('is-drop-after', !before);
        });
        block.addEventListener('dragleave', () => block.classList.remove('is-drop-before', 'is-drop-after'));
        block.addEventListener('drop', (event) => {
          if (dragging === null) return;
          event.preventDefault();
          const before = event.clientY < block.getBoundingClientRect().top + block.offsetHeight / 2;
          let destination = before ? index : index + 1;
          if (dragging < destination) destination -= 1;
          move(dragging, Math.min(fields.length - 1, destination));
        });
      });
      sync();
      renderPreview();
    };

    add.addEventListener('click', () => {
      const type = types[typePicker?.value] ? typePicker.value : 'text';
      const label = types[type];
      fields.push({
        label, name: uniqueName(label), type, required: false,
        options: type === 'select' ? ['First choice', 'Second choice'] : [],
        placeholder: '', help: '', autocomplete: type === 'email' ? 'email' : (type === 'tel' ? 'tel' : ''),
      });
      render();
      list.lastElementChild?.querySelector('input')?.focus();
      announce(`${label} added as field ${fields.length}.`);
    });
    form.querySelector('[name="submit_label"]')?.addEventListener('input', renderPreview);
    form.addEventListener('submit', (event) => {
      if (fields.length) return;
      event.preventDefault();
      announce('Add at least one form field before saving.');
      add.focus();
    });
    render();
    initialized = true;
  });

  document.querySelectorAll('[data-submission-bulk]').forEach((form) => {
    const checks = Array.from(form.querySelectorAll('[data-submission-check]'));
    const all = form.querySelector('[data-select-all-submissions]');
    const action = form.querySelector('[data-submission-bulk-action]');
    const submit = form.querySelector('[data-submission-bulk-submit]');
    const count = form.querySelector('[data-submission-selection]');
    const sync = () => {
      const selected = checks.filter((check) => check.checked).length;
      if (count) count.textContent = `${selected} selected`;
      if (all) {
        all.checked = checks.length > 0 && selected === checks.length;
        all.indeterminate = selected > 0 && selected < checks.length;
      }
      if (submit) {
        submit.disabled = selected === 0 || !action?.value;
        if (action?.value === 'delete') {
          submit.dataset.confirm = `Permanently delete ${selected} selected submission${selected === 1 ? '' : 's'}? Responses and consent evidence cannot be recovered.`;
        } else {
          delete submit.dataset.confirm;
        }
      }
      checks.forEach((check) => check.closest('.submission-card')?.classList.toggle('is-selected', check.checked));
    };
    all?.addEventListener('change', () => { checks.forEach((check) => { check.checked = all.checked; }); sync(); });
    checks.forEach((check) => check.addEventListener('change', sync));
    action?.addEventListener('change', sync);
    sync();
  });

  document.querySelectorAll('.submission-details').forEach((details) => {
    details.addEventListener('toggle', () => {
      if (!details.open) return;
      document.querySelectorAll('.submission-details[open]').forEach((other) => {
        if (other !== details) other.removeAttribute('open');
      });
    });
  });

  document.querySelectorAll('.row-editor').forEach((details) => {
    details.addEventListener('toggle', () => {
      if (!details.open) return;
      document.querySelectorAll('.row-editor[open]').forEach((other) => {
        if (other !== details) other.removeAttribute('open');
      });
    });
  });

  const accountMenu = document.querySelector('.account-menu');
  document.addEventListener('click', (event) => {
    if (accountMenu?.open && !accountMenu.contains(event.target)) {
      accountMenu.removeAttribute('open');
    }
  });
})();

