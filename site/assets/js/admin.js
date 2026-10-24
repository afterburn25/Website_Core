(() => {
  'use strict';

  const body = document.body;
  const sidebar = document.getElementById('admin-sidebar');
  const openButton = document.querySelector('[data-admin-nav-open]');
  const closeButtons = document.querySelectorAll('[data-admin-nav-close]');
  const stateKey = 'wc-admin-nav-state-v1';
  const scrollKey = 'wc-admin-nav-scroll-v1';
  let saved = {};

  try {
    saved = JSON.parse(localStorage.getItem(stateKey) || '{}') || {};
  } catch (_) {
    saved = {};
  }

  document.querySelectorAll('[data-nav-group]').forEach((group) => {
    const key = group.dataset.navGroup;
    if (Object.prototype.hasOwnProperty.call(saved, key)) {
      group.open = Boolean(saved[key]);
    }
    group.addEventListener('toggle', () => {
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

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const prompt = event.submitter?.dataset?.confirm || form.dataset.confirm;
      if (prompt && !window.confirm(prompt)) event.preventDefault();
    });
  });

  document.querySelectorAll('table').forEach((table) => {
    if (table.parentElement?.classList.contains('responsive-table') || table.closest('.table-wrap')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'responsive-table';
    table.parentNode?.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });

  document.querySelectorAll('form[method="post"]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (event.defaultPrevented || !form.checkValidity()) return;
      const button = event.submitter instanceof HTMLButtonElement ? event.submitter : form.querySelector('button[type="submit"],button:not([type])');
      if (!button) return;
      button.classList.add('is-busy');
      button.setAttribute('aria-busy', 'true');
      const busyLabel = button.dataset.busyLabel;
      if (busyLabel) button.textContent = busyLabel;
      window.setTimeout(() => { button.disabled = true; }, 0);
    });
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

