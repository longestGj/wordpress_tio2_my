function connectDialog(dialog, trigger, closeButton) {
  if (!dialog || !trigger || !closeButton) return;
  let background = [];
  trigger.addEventListener('click', () => {
    if (dialog.open) return;
    dialog.showModal();
    trigger.setAttribute('aria-expanded', 'true');
    closeButton.focus();
    background = [...document.body.children].filter(el => el !== dialog && !['SCRIPT','STYLE'].includes(el.tagName)).map(el => ({el, inert: el.inert, hidden: el.getAttribute('aria-hidden')}));
    for (const {el} of background) { el.inert = true; el.setAttribute('aria-hidden', 'true'); }
  });
  closeButton.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    // Full-screen transparent menu surface or true cookie-dialog backdrop.
    if (dialog.id === 'mobile-menu' || event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  });
  dialog.addEventListener('keydown', event => {
    if (event.key !== 'Tab') return;
    const items = [...dialog.querySelectorAll('a[href],button:not([disabled]),[tabindex="0"]')].filter(el => el.getClientRects().length);
    const first = items[0], last = items.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  dialog.addEventListener('close', () => {
    for (const {el, inert, hidden} of background) {
      el.inert = inert;
      if (hidden === null) el.removeAttribute('aria-hidden'); else el.setAttribute('aria-hidden', hidden);
    }
    background = [];
    trigger.setAttribute('aria-expanded', 'false');
    if (trigger.getClientRects().length) trigger.focus();
    else document.querySelector('.desktop-nav a')?.focus();
  });
}
const menu = document.getElementById('mobile-menu');
connectDialog(menu, document.querySelector('.menu-button'), document.querySelector('.menu-close'));
connectDialog(document.getElementById('cookie-dialog'), document.querySelector('.cookie-settings'), document.querySelector('.cookie-close'));
const desktopHeader = matchMedia('(min-width:1025px)');
desktopHeader.addEventListener('change', () => { if (desktopHeader.matches && menu?.open) menu.close(); });

const mobileProducts = matchMedia('(max-width:767px)');
const groups = [...document.querySelectorAll('.product-group')];
groups.forEach(group => {
  const summary = group.querySelector('summary');
  const sign = summary.querySelector('span');
  sign?.classList.add('disclosure-sign');
  const sync = () => { summary.setAttribute('aria-expanded', String(group.open)); if (sign) sign.textContent=group.open?'−':'+'; };
  group.addEventListener('toggle', sync);
  group.open = !mobileProducts.matches;
  sync();
});
mobileProducts.addEventListener('change', () => groups.forEach(group => { group.open = !mobileProducts.matches; }));
