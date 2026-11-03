(() => {
  'use strict';
  const toggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
      nav.classList.toggle('is-open', !open);
    });
    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape' || toggle.getAttribute('aria-expanded') !== 'true') return;
      toggle.setAttribute('aria-expanded', 'false');
      nav.classList.remove('is-open');
      toggle.focus();
    });
    document.addEventListener('click', (event) => {
      if (!nav.classList.contains('is-open') || nav.contains(event.target) || toggle.contains(event.target)) return;
      toggle.setAttribute('aria-expanded', 'false');
      nav.classList.remove('is-open');
    });
  }

  const header = document.querySelector('[data-site-header]');
  if (header) {
    const updateHeader = () => header.classList.toggle('is-scrolled', window.scrollY > 12);
    updateHeader();
    window.addEventListener('scroll', updateHeader, {passive: true});
  }

  document.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', (event) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
        || link.target || link.hasAttribute('download')) return;
      const destination = new URL(link.href, window.location.href);
      if (destination.origin !== window.location.origin) return;
      const current = new URL(window.location.href);
      if (destination.pathname === current.pathname && destination.search === current.search && destination.hash === '') {
        event.preventDefault();
        window.scrollTo({top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
        return;
      }
      if (destination.hash !== '' && destination.pathname === current.pathname && destination.search === current.search) return;
      document.body.classList.add('is-page-leaving');
    });
  });

  document.querySelectorAll('[data-open-window]').forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      window.open(link.href, 'wc-nav-window', 'noopener,noreferrer,width=1200,height=800,resizable=yes,scrollbars=yes');
    });
  });

  const filter = document.querySelector('[data-dietary-filter]');
  const cards = [...document.querySelectorAll('[data-dietary-tags]')];
  const applyFilter = () => {
    cards.forEach((card) => {
      const selected = card.querySelector('input[type="checkbox"]')?.checked;
      const dietaryHidden = Boolean(filter?.value && !card.dataset.dietaryTags.split(' ').includes(filter.value));
      const packageHidden = card.dataset.packageVisible === 'false';
      card.hidden = Boolean((dietaryHidden || packageHidden) && !selected);
    });
    document.querySelectorAll('[data-planner-category]').forEach((category) => {
      category.hidden = [...category.querySelectorAll('[data-dietary-tags]')].every((card) => card.hidden);
    });
  };
  if (filter) {
    document.querySelector('[data-filter-controls]')?.removeAttribute('hidden');
    filter.addEventListener('change', applyFilter);
  }

  const form = document.querySelector('[data-quote-form]');
  if (!form) return;
  const steps = [...form.querySelectorAll('[data-quote-step]')];
  const stepItems = [...document.querySelectorAll('[data-step-list] li')];
  const label = document.querySelector('[data-step-label]');
  const progress = document.querySelector('[data-progress]');
  const back = form.querySelector('[data-quote-back]');
  const next = form.querySelector('[data-quote-next]');
  const submit = form.querySelector('[data-quote-submit]');
  const estimate = form.querySelector('[data-menu-estimate]');
  const review = form.querySelector('[data-review-plan]');
  const packageSelect = form.querySelector('[data-planner-package]');
  const fulfillmentMethod = form.querySelector('[data-fulfillment-method]');
  const fulfillmentWindow = form.querySelector('[data-fulfillment-window]');
  const deliveryAddress = form.querySelector('[data-delivery-address]');
  const rulesText = form.querySelector('[data-planner-rules]');
  const money = (cents) => new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'}).format(cents / 100);
  let current = 0;
  let debounce;
  let sequence = 0;
  let controller;
  form.noValidate = true;
  document.querySelector('[data-quote-progress]').hidden = false;
  estimate.tabIndex = -1;

  const paragraph = (parent, text, className = '') => {
    const element = document.createElement('p');
    element.textContent = text;
    if (className) element.className = className;
    parent.append(element);
  };
  const renderPlan = (plan) => {
    estimate.replaceChildren();
    review.replaceChildren();
    const heading = document.createElement('h3');
    heading.textContent = 'Preliminary food estimate';
    estimate.append(heading);
    if (plan.errors?.length) paragraph(estimate, plan.errors.join(' '), 'c-form-error');
    if (plan.items?.length) paragraph(review, 'Selected dishes: ' + plan.items.map((item) => item.name).join(', '));
    if (plan.package_name) paragraph(review, 'Package: ' + plan.package_name);
    (plan.lines || []).forEach((line) => {
      if (line.discount_bps) paragraph(estimate, `${line.name}: guest-count discount ${(line.discount_bps / 100).toFixed(2)}% applied.`);
      paragraph(estimate, line.name + (line.quantity === null ? ' · Team confirmation required' :
        ` · ${line.quantity} × ${money(line.unit_cents)} = ${money(line.total_cents)}`));
    });
    const total = plan.estimate_cents === null || plan.estimate_cents === undefined ?
      'Complete estimate needs team confirmation' : 'Food estimate: ' + money(plan.estimate_cents);
    const strong = document.createElement('strong');
    strong.textContent = total;
    estimate.append(strong);
    paragraph(review, total);
    (plan.operations?.lines || []).forEach((line) => {
      paragraph(estimate, `${line.name} · ${line.quantity} × ${money(line.unit_cents)} = ${money(line.total_cents)}`);
    });
    if (plan.operations?.combined_estimate_cents !== null && plan.operations?.combined_estimate_cents !== undefined) {
      const combined = 'Combined preliminary estimate: ' + money(plan.operations.combined_estimate_cents);
      const combinedStrong = document.createElement('strong');
      combinedStrong.textContent = combined;
      estimate.append(combinedStrong);
      paragraph(review, combined);
    }
    if (plan.operations?.fulfillment_method) paragraph(review,
      `Fulfillment: ${plan.operations.fulfillment_method} · ${plan.operations.fulfillment_window}`);
    (plan.notes || []).forEach((note) => paragraph(estimate, note, 'c-disclaimer'));
  };
  const updateRules = () => {
    let rules = {};
    let groupItems = [];
    const option = packageSelect.selectedOptions[0];
    try { rules = JSON.parse(option?.dataset.rules || '{}'); } catch { /* Server still validates the catalog. */ }
    try { groupItems = JSON.parse(option?.dataset.groupItems || '[]'); } catch { /* Server still validates the catalog. */ }
    const hasGroup = Boolean(option?.dataset.groupName);
    const allowed = new Set(groupItems.map(String));
    cards.forEach((card) => { card.dataset.packageVisible = !hasGroup || allowed.has(card.dataset.itemId) ? 'true' : 'false'; });
    const details = Object.values(rules).map((rule) => `${rule.label}: choose ${rule.min}–${rule.max}`);
    const collection = hasGroup ? ` Choose from the ${option.dataset.groupName} collection.` : '';
    rulesText.textContent = details.length ? 'Included in the package price — ' + details.join('; ') + '. Other categories are outside this package.' + collection :
      (packageSelect.value ? 'Dish allowances have not been configured for this package. The team will confirm your selected menu and price.' + collection :
        'Choose individual dishes, or leave your menu open for recommendations.');
    applyFilter();
  };
  const updateFulfillmentWindows = () => {
    if (!fulfillmentMethod || !fulfillmentWindow) return;
    [...fulfillmentWindow.options].forEach((option) => {
      if (!option.dataset.method) return;
      option.disabled = Boolean(fulfillmentMethod.value && option.dataset.method !== fulfillmentMethod.value);
    });
    if (fulfillmentWindow.selectedOptions[0]?.disabled) fulfillmentWindow.value = '';
    if (deliveryAddress) {
      const delivery = fulfillmentMethod.value === 'delivery';
      deliveryAddress.querySelectorAll('[name="address_line_1"], [name="city"], [name="region"]').forEach((input) => {
        input.required = delivery;
      });
    }
  };
  const refreshEstimate = async () => {
    clearTimeout(debounce);
    controller?.abort();
    controller = new AbortController();
    const request = ++sequence;
    estimate.setAttribute('aria-busy', 'true');
    try {
      const payload = new FormData();
      ['_csrf', 'guest_count', 'package_id', 'event_date', 'service_style', 'fulfillment_method', 'fulfillment_window', 'postal_code']
        .forEach((name) => payload.set(name, form.elements.namedItem(name)?.value || ''));
      form.querySelectorAll('[name="menu_items[]"]:checked').forEach((field) => payload.append('menu_items[]', field.value));
      form.querySelectorAll('[name="extras[]"]:checked').forEach((field) => payload.append('extras[]', field.value));
      const response = await fetch(form.dataset.estimateUrl, {
        method: 'POST', body: payload, credentials: 'same-origin',
        headers: {'Accept': 'application/json'}, signal: controller.signal,
      });
      const plan = await response.json();
      if (request !== sequence) return null;
      if (!response.ok && !Array.isArray(plan.errors)) throw new Error('Invalid estimate response');
      renderPlan(plan);
      return plan;
    } catch (error) {
      if (error.name !== 'AbortError' && request === sequence) {
        estimate.replaceChildren();
        paragraph(estimate, 'The estimate could not be updated. Try Calculate food estimate again. You can still send a request for the team to review.');
      }
      return null;
    } finally {
      if (request === sequence) estimate.removeAttribute('aria-busy');
    }
  };
  const show = (index, focus = true) => {
    current = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((step, i) => { step.hidden = i !== current; });
    stepItems.forEach((item, i) => {
      item.classList.toggle('is-active', i === current);
      if (i === current) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
    });
    label.textContent = `Step ${current + 1} of ${steps.length}`;
    progress.style.width = `${((current + 1) / steps.length) * 100}%`;
    back.hidden = current === 0;
    next.hidden = current === steps.length - 1;
    submit.hidden = current !== steps.length - 1;
    if (focus) {
      steps[current].querySelector('input,select,textarea')?.focus({preventScroll: true});
      window.scrollTo({top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
    }
  };
  next.addEventListener('click', async () => {
    const invalid = [...steps[current].querySelectorAll('input,select,textarea')].find((field) => !field.checkValidity());
    if (invalid) { invalid.reportValidity(); return; }
    if (current === 1) {
      next.disabled = true;
      const plan = await refreshEstimate();
      next.disabled = false;
      if (plan?.errors?.length) { estimate.focus(); return; }
    }
    show(current + 1);
  });
  back.addEventListener('click', () => show(current - 1));
  form.addEventListener('input', (event) => {
    if (!event.target.matches('[name="guest_count"], [name="package_id"], [name="menu_items[]"], [name="event_date"], [name="service_style"], [name="fulfillment_method"], [name="fulfillment_window"], [name="postal_code"], [name="extras[]"]')) return;
    updateRules();
    updateFulfillmentWindows();
    applyFilter();
    clearTimeout(debounce);
    debounce = setTimeout(refreshEstimate, 400);
  });
  form.addEventListener('submit', (event) => {
    if (event.submitter?.matches('[data-preview-plan]')) {
      event.preventDefault();
      refreshEstimate();
      return;
    }
    const invalid = [...form.querySelectorAll('input,select,textarea')].find((field) => !field.checkValidity());
    if (invalid) {
      event.preventDefault();
      show(steps.findIndex((step) => step.contains(invalid)));
      invalid.reportValidity();
      return;
    }
    if (current < steps.length - 1) { event.preventDefault(); next.click(); }
  });
  updateRules();
  updateFulfillmentWindows();
  const invalid = [...form.querySelectorAll('input,select,textarea')].find((field) => !field.checkValidity());
  const serverError = document.querySelector('[data-quote-error]');
  show(serverError ? (invalid ? steps.findIndex((step) => step.contains(invalid)) : 1) : 0, false);
  if (serverError) serverError.focus();
})();
