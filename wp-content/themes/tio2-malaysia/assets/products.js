(() => {
  const source = document.getElementById('products-selector-data');
  const results = document.getElementById('product-results');
  const heading = document.getElementById('product-result-heading');
  if (source && results && heading) {
    let data;
    try { data = JSON.parse(source.textContent); }
    catch (_error) { heading.textContent = 'Selection unavailable'; results.textContent = ''; return; }
    const buttons = [...document.querySelectorAll('[data-application]')];
    const selector = document.querySelector('.product-selector');
    let explicitSelection = false;
    const render = application => {
      const grades = data.applications[application] || [];
      results.replaceChildren();
      heading.textContent = data.headingTemplate.replace('{count}', String(grades.length));
      if (application === 'not-sure') {
        const message = document.createElement('div');
        message.className = 'product-result-message';
        const paragraph = document.createElement('p');
        paragraph.textContent = data.noResult;
        const link = document.createElement('a');
        link.href = '#all-grades';
        link.textContent = data.browseLabel;
        message.append(paragraph, link);
        results.append(message);
        return;
      }
      for (const grade of grades) {
        const row = document.createElement('div');
        row.className = 'product-selector-result';
        const name = document.createElement('strong');
        name.textContent = grade.name;
        row.append(name);
        if (grade.url) {
          const link = document.createElement('a');
          link.href = grade.url;
          link.dataset.routeKey = grade.routeKey;
          link.textContent = data.ctaLabel;
          link.setAttribute('aria-label', `${data.ctaLabel} ${grade.name}`);
          row.append(link);
        }
        results.append(row);
      }
    };
    for (const button of buttons) {
      button.addEventListener('click', () => {
        explicitSelection = true;
        if (selector) selector.dataset.explicitSelection = String(explicitSelection);
        for (const candidate of buttons) candidate.setAttribute('aria-pressed', String(candidate === button));
        render(button.dataset.application);
      });
    }
    if (selector) selector.dataset.explicitSelection = String(explicitSelection);
  }

  for (const button of document.querySelectorAll('.product-faq-item button[aria-controls]')) {
    button.addEventListener('click', () => {
      const answer = document.getElementById(button.getAttribute('aria-controls'));
      if (!answer) return;
      const expanded = button.getAttribute('aria-expanded') === 'true';
      button.setAttribute('aria-expanded', String(!expanded));
      answer.hidden = expanded;
      const sign = button.querySelector('[aria-hidden="true"]');
      if (sign) sign.textContent = expanded ? '+' : '−';
    });
  }
})();
