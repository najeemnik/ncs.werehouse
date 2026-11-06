/* Disclosures keep ordinary links/selects and the browser's native keyboard behavior. */
(() => {
  const toggle = document.getElementById('headerToolsToggle');
  const panel = document.getElementById('headerToolsPanel');
  const langButton = document.getElementById('langDropdownBtn');
  const languages = document.getElementById('langDropdownMenu');
  if (!toggle || !panel || !langButton || !languages) return;
  const compact = matchMedia('(max-width:900px)');
  const focusables = () => [...panel.querySelectorAll('button:not(:disabled),select,a[href]')].filter(el => el.getClientRects().length);
  const closeLanguages = (restoreFocus = false) => {
    languages.hidden = true;
    langButton.setAttribute('aria-expanded', 'false');
    if (restoreFocus) langButton.focus();
  };
  const setTools = (open, restoreFocus = false) => {
    panel.classList.toggle('is-open', open && compact.matches);
    panel.inert = compact.matches && !open;
    toggle.setAttribute('aria-expanded', String(open && compact.matches));
    if (!open) closeLanguages();
    if (restoreFocus) toggle.focus();
  };
  const sync = () => {
    const hadFocus = panel.contains(document.activeElement);
    setTools(false, compact.matches && hadFocus);
  };
  toggle.addEventListener('click', () => setTools(toggle.getAttribute('aria-expanded') !== 'true'));
  toggle.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setTools(true);
      focusables()[0]?.focus();
    }
  });
  langButton.addEventListener('click', () => {
    const open = languages.hidden;
    languages.hidden = !open;
    langButton.setAttribute('aria-expanded', String(open));
  });
  langButton.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      languages.hidden = false;
      langButton.setAttribute('aria-expanded', 'true');
      languages.querySelector('a')?.focus();
    }
  });
  document.addEventListener('click', event => {
    if (!langButton.contains(event.target) && !languages.contains(event.target)) closeLanguages();
    if (!panel.contains(event.target) && !toggle.contains(event.target)) setTools(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if (!languages.hidden) { event.preventDefault(); closeLanguages(true); }
    else if (toggle.getAttribute('aria-expanded') === 'true') { event.preventDefault(); setTools(false, true); }
  });
  document.addEventListener('focusin', event => {
    if (!panel.contains(event.target) && !toggle.contains(event.target)) setTools(false);
    else if (!langButton.contains(event.target) && !languages.contains(event.target)) closeLanguages();
  });
  document.querySelector('[data-header-lock]')?.addEventListener('click', () => {
    setTools(false, compact.matches);
    if (typeof window.lockShopScreen === 'function') window.lockShopScreen();
  });
  document.getElementById('headerReportCurrency')?.addEventListener('change', event => {
    const url = new window.URL(window.location.href);
    url.searchParams.set('report_currency', event.target.value);
    window.location.assign(url.href);
  });
  compact.addEventListener('change', sync);
  sync();
})();
