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
    const refresh = () => {
      const selected = boxes.filter((box) => box.checked).length;
      if (summary) summary.textContent = selected ? `${selected} selected` : 'None selected';
    };
    boxes.forEach((box) => box.addEventListener('change', refresh));
    refresh();
  });

  document.querySelectorAll('[data-drop-zone]').forEach((zone) => {
    const input = zone.querySelector('input[type="file"]');
    if (!input) return;
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

  const accountMenu = document.querySelector('.account-menu');
  document.addEventListener('click', (event) => {
    if (accountMenu?.open && !accountMenu.contains(event.target)) {
      accountMenu.removeAttribute('open');
    }
  });
})();

