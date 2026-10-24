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
  }

  document.querySelectorAll('[data-open-window]').forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      window.open(link.href, 'wc-nav-window', 'noopener,noreferrer,width=1200,height=800,resizable=yes,scrollbars=yes');
    });
  });

  const form = document.querySelector('[data-quote-form]');
  if (!form) return;
  const steps = [...form.querySelectorAll('[data-quote-step]')];
  const stepItems = [...document.querySelectorAll('[data-step-list] li')];
  const label = document.querySelector('[data-step-label]');
  const progress = document.querySelector('[data-progress]');
  const back = form.querySelector('[data-quote-back]');
  const next = form.querySelector('[data-quote-next]');
  const submit = form.querySelector('[data-quote-submit]');
  let current = 0;

  const show = (index) => {
    current = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((step, i) => { step.hidden = i !== current; });
    stepItems.forEach((item, i) => item.classList.toggle('is-active', i === current));
    if (label) label.textContent = `Step ${current + 1} of ${steps.length}`;
    if (progress) progress.style.width = `${((current + 1) / steps.length) * 100}%`;
    back.hidden = current === 0;
    next.hidden = current === steps.length - 1;
    submit.hidden = current !== steps.length - 1;
    const first = steps[current].querySelector('input,select,textarea');
    if (first && current > 0) first.focus({preventScroll: true});
    window.scrollTo({top: 0, behavior: 'smooth'});
  };

  next.addEventListener('click', () => {
    const fields = [...steps[current].querySelectorAll('input,select,textarea')];
    const invalid = fields.find((field) => !field.checkValidity());
    if (invalid) { invalid.reportValidity(); return; }
    show(current + 1);
  });
  back.addEventListener('click', () => show(current - 1));
  show(0);
})();
