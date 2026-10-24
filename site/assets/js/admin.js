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

  const accountMenu = document.querySelector('.account-menu');
  document.addEventListener('click', (event) => {
    if (accountMenu?.open && !accountMenu.contains(event.target)) {
      accountMenu.removeAttribute('open');
    }
  });
})();

