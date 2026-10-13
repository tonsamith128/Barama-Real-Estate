/* BARAMA_GLOBAL_LOCATION_CASCADE_V110 */
(() => {
  'use strict';
  const humanize = (value) => String(value || 'Area').replace(/[_-]+/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase());
  const roots = Array.from(document.querySelectorAll('[data-location-cascade]'));
  if (!roots.length) return;

  const requestJson = async (url) => {
    const response = await fetch(url, {headers:{'Accept':'application/json'}, credentials:'same-origin'});
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) throw new Error(data.message || `Location request failed (${response.status}).`);
    return data;
  };

  roots.forEach((root) => {
    const endpoint = root.dataset.locationEndpoint || '/location-options';
    const country = root.querySelector('[data-location-country]');
    const value = root.querySelector('[data-location-value]');
    const holder = root.querySelector('[data-location-selects]');
    const hint = root.querySelector('[data-location-hint]');
    const selectedId = Number(root.dataset.selectedLocation || 0);
    if (!country || !value || !holder) return;

    const setLoading = (on) => root.classList.toggle('is-loading', on);
    const setMessage = (message, error = false) => {
      if (!hint) return;
      hint.textContent = message;
      hint.classList.toggle('barama-location-cascade__error', error);
    };
    const clearLevels = (from = 0) => {
      Array.from(holder.querySelectorAll('[data-location-level]')).forEach((label, index) => {
        if (index >= from) label.remove();
      });
    };
    const labelFor = (rows, depth) => {
      const types = [...new Set(rows.map((row) => humanize(row.type)).filter(Boolean))];
      if (types.length === 1) return types[0];
      if (depth === 0) return 'State / Province / Region';
      if (depth === 1) return 'District / County / Area';
      return `Local area ${depth + 1}`;
    };

    const loadChildren = async (countryCode, parentId, depth, selectedChild = 0) => {
      const params = new URLSearchParams({country: countryCode});
      if (parentId) params.set('parent_id', String(parentId));
      const data = await requestJson(`${endpoint}?${params.toString()}`);
      const rows = Array.isArray(data.locations) ? data.locations : [];
      if (!rows.length) return null;
      const label = document.createElement('label');
      label.dataset.locationLevel = String(depth);
      const span = document.createElement('span');
      span.textContent = labelFor(rows, depth);
      const select = document.createElement('select');
      select.className = 'form-select';
      select.dataset.locationSelect = '1';
      const blank = document.createElement('option');
      blank.value = '';
      blank.textContent = 'Select area';
      select.appendChild(blank);
      rows.forEach((row) => {
        const option = document.createElement('option');
        option.value = String(row.id);
        option.textContent = `${row.name} · ${humanize(row.type)}`;
        if (Number(row.id) === Number(selectedChild)) option.selected = true;
        select.appendChild(option);
      });
      label.append(span, select);
      holder.appendChild(label);
      select.addEventListener('change', async () => {
        clearLevels(depth + 1);
        const id = Number(select.value || 0);
        value.value = id > 0 ? String(id) : (parentId ? String(parentId) : '');
        if (!id) return;
        try {
          setLoading(true);
          await loadChildren(countryCode, id, depth + 1, 0);
          setMessage('Location selected. Choose a deeper area if available.');
        } catch (error) {
          setMessage(error.message || 'Unable to load locations.', true);
        } finally { setLoading(false); }
      });
      return select;
    };

    const populateCountries = (rows, preferred = '') => {
      const existingBlank = country.querySelector('option[value=""]')?.textContent || 'No location';
      country.innerHTML = '';
      const blank = document.createElement('option');
      blank.value = '';
      blank.textContent = existingBlank;
      country.appendChild(blank);
      rows.forEach((row) => {
        const option = document.createElement('option');
        option.value = String(row.country_code || '');
        option.textContent = row.name + (Number(row.is_default) === 1 ? ' · Default' : '') + (Number(row.enabled) !== 1 ? ' · Current (disabled)' : '');
        if (option.value === preferred) option.selected = true;
        country.appendChild(option);
      });
    };

    const initialize = async () => {
      try {
        setLoading(true);
        const initial = selectedId > 0
          ? await requestJson(`${endpoint}?location_id=${encodeURIComponent(String(selectedId))}`)
          : await requestJson(endpoint);
        const countries = Array.isArray(initial.countries) ? initial.countries : [];
        const selection = initial.selection && typeof initial.selection === 'object' ? initial.selection : null;
        const selectedCountry = selection?.country_code || '';
        populateCountries(countries, selectedCountry);
        clearLevels(0);
        if (selection && Array.isArray(selection.path)) {
          let parent = 0;
          for (let depth = 0; depth < selection.path.length; depth += 1) {
            const node = selection.path[depth];
            await loadChildren(selectedCountry, parent || null, depth, Number(node.id));
            parent = Number(node.id);
          }
          value.value = String(selectedId);
          if (parent) await loadChildren(selectedCountry, parent, selection.path.length, 0);
        }
      } catch (error) {
        setMessage(error.message || 'Unable to initialize locations.', true);
      } finally { setLoading(false); }
    };

    country.addEventListener('change', async () => {
      clearLevels(0);
      value.value = '';
      const code = country.value;
      if (!code) return;
      try {
        setLoading(true);
        await loadChildren(code, null, 0, 0);
        setMessage('Choose the deepest available administrative area.');
      } catch (error) {
        setMessage(error.message || 'Unable to load locations.', true);
      } finally { setLoading(false); }
    });

    initialize();
  });
})();
