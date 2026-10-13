/* Barama A7: authenticated, temporary Mapbox suggestions. Never write provider result into Barama locations. */
(() => {
  'use strict';
  document.querySelectorAll('[data-location-mapbox]').forEach((root) => {
    if (root.dataset.mapboxBound === '1') return;
    root.dataset.mapboxBound = '1';
    const input = root.querySelector('[data-mapbox-q]');
    const button = root.querySelector('[data-mapbox-search]');
    const results = root.querySelector('[data-mapbox-results]');
    const cascade = root.closest('[data-location-cascade]');
    const country = cascade?.querySelector('[data-location-country]');
    if (!input || !button || !results || !country) return;
    const text = (message) => {
      results.replaceChildren();
      const small = document.createElement('small');
      small.textContent = message;
      results.appendChild(small);
    };
    const search = async () => {
      const q = input.value.trim();
      if (q.length < 3 || q.length > 120) { text('Enter at least 3 characters.'); return; }
      const params = new URLSearchParams({q});
      if (country.value) params.set('country', country.value);
      button.disabled = true;
      text('Searching Mapbox…');
      try {
        const response = await fetch(`${root.dataset.searchEndpoint}?${params}`, {
          credentials:'same-origin', headers:{'Accept':'application/json'}
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Location search is unavailable.');
        results.replaceChildren();
        if (!payload.results?.length) { text('No Mapbox results found. Select a Barama location below.'); return; }
        payload.results.forEach((item) => {
          const option = document.createElement('button');
          option.type = 'button';
          option.textContent = item.label;
          option.title = 'Select this country in Barama. Continue choosing the matching administrative area.';
          option.addEventListener('click', () => {
            const code = item.country_code;
            const available = [...country.options].some(o => o.value === code);
            if (!code || !available) {
              text('This place is outside enabled countries. Ask an administrator to change country scope.');
              return;
            }
            country.value = code;
            country.dispatchEvent(new Event('change', {bubbles:true}));
            text(`Mapbox place: ${item.label}. Now select the Barama administrative area below; no Mapbox data was saved.`);
          });
          results.appendChild(option);
        });
      } catch (error) { text(error instanceof Error ? error.message : 'Unable to search Mapbox.'); }
      finally { button.disabled = false; }
    };
    button.addEventListener('click', search);
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') { event.preventDefault(); search(); }
    });
  });
})();
