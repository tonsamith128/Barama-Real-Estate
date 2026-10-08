/* BARAMA_PUBLIC_LOCATION_PICKER_V112 */
(() => {
  'use strict';
  const roots = Array.from(document.querySelectorAll('[data-public-location-picker]'));
  if (!roots.length) return;

  const humanize = (value) => String(value || 'Area').replace(/[_-]+/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase());
  const requestJson = async (url) => {
    const response = await fetch(url, {headers:{Accept:'application/json'},credentials:'same-origin'});
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) throw new Error(data.message || `Location request failed (${response.status}).`);
    return data;
  };

  roots.forEach((root) => {
    if (root.dataset.publicLocationReady === '1') return;
    root.dataset.publicLocationReady = '1';

    const endpoint = root.dataset.locationEndpoint || '/location-options';
    const initialValue = String(root.dataset.selectedLocation || '').trim();
    const emptyLabel = root.dataset.emptyLabel || 'All locations';
    const trigger = root.querySelector('[data-public-location-trigger]');
    const panel = root.querySelector('[data-public-location-panel]');
    const close = root.querySelector('[data-public-location-close]');
    const done = root.querySelector('[data-public-location-done]');
    const clear = root.querySelector('[data-public-location-clear]');
    const country = root.querySelector('[data-public-location-country]');
    const levels = root.querySelector('[data-public-location-levels]');
    const value = root.querySelector('[data-public-location-value]');
    const label = root.querySelector('[data-public-location-label]');
    const status = root.querySelector('[data-public-location-status]');
    if (!trigger || !panel || !country || !levels || !value || !label) return;

    let countries = [];
    let currentCountryName = '';

    const setStatus = (message = '', error = false) => {
      if (!status) return;
      status.textContent = message;
      status.classList.toggle('is-error', error);
    };
    const open = () => {
      panel.hidden = false;
      root.classList.add('is-open');
      trigger.setAttribute('aria-expanded','true');
      window.setTimeout(() => country.focus(), 0);
    };
    const shut = () => {
      panel.hidden = true;
      root.classList.remove('is-open');
      trigger.setAttribute('aria-expanded','false');
    };
    const setChoice = (nextValue, nextLabel) => {
      value.value = String(nextValue || '');
      label.textContent = nextLabel || emptyLabel;
    };
    const clearLevels = (from = 0) => {
      Array.from(levels.querySelectorAll('[data-public-location-level]')).forEach((node, index) => {
        if (index >= from) node.remove();
      });
    };
    const typeLabel = (rows, depth) => {
      const types = [...new Set(rows.map((row) => humanize(row.type)).filter(Boolean))];
      if (types.length === 1) return types[0];
      if (depth === 0) return 'State / Province / Region';
      if (depth === 1) return 'District / County / Area';
      return `Local area ${depth + 1}`;
    };
    const findCountryName = (code) => {
      const row = countries.find((item) => String(item.country_code || '').toUpperCase() === String(code || '').toUpperCase());
      return row ? String(row.name || code) : String(code || '');
    };

    const loadLevel = async (countryCode, parentId, depth, selectedId = 0, parentLabel = '') => {
      const params = new URLSearchParams({country: countryCode});
      if (parentId) params.set('parent_id', String(parentId));
      const data = await requestJson(`${endpoint}?${params.toString()}`);
      const rows = Array.isArray(data.locations) ? data.locations : [];
      if (!rows.length) return null;

      const field = document.createElement('label');
      field.className = 'barama-public-location-picker__field';
      field.dataset.publicLocationLevel = String(depth);
      const caption = document.createElement('span');
      caption.textContent = typeLabel(rows, depth);
      const select = document.createElement('select');
      select.dataset.publicLocationSelect = '1';
      const blank = document.createElement('option');
      blank.value = '';
      blank.textContent = parentLabel ? `All ${parentLabel}` : `All ${currentCountryName || 'areas'}`;
      select.appendChild(blank);
      rows.forEach((row) => {
        const option = document.createElement('option');
        option.value = String(row.id || '');
        option.textContent = String(row.name || '');
        option.dataset.locationName = String(row.name || '');
        if (Number(row.id) === Number(selectedId)) option.selected = true;
        select.appendChild(option);
      });
      field.append(caption, select);
      levels.appendChild(field);

      select.addEventListener('change', async () => {
        clearLevels(depth + 1);
        const selectedOption = select.options[select.selectedIndex];
        const id = Number(select.value || 0);
        if (!id) {
          if (parentId) setChoice(String(parentId), parentLabel || currentCountryName);
          else setChoice(`country:${countryCode}`, currentCountryName || countryCode);
          setStatus('You can search the whole selected country or choose a more specific area.');
          return;
        }
        const name = selectedOption?.dataset?.locationName || selectedOption?.textContent || 'Selected area';
        setChoice(String(id), name);
        setStatus('Location selected. Choose a deeper area only if you need to narrow the search.');
        try {
          await loadLevel(countryCode, id, depth + 1, 0, name);
        } catch (error) {
          setStatus(error.message || 'Unable to load the next location level.', true);
        }
      });
      return select;
    };

    const populateCountries = (rows, preferred = '') => {
      countries = Array.isArray(rows) ? rows : [];
      country.innerHTML = '';
      const all = document.createElement('option');
      all.value = '';
      all.textContent = emptyLabel;
      country.appendChild(all);
      countries.forEach((row) => {
        const option = document.createElement('option');
        const code = String(row.country_code || '').toUpperCase();
        option.value = code;
        option.textContent = `${row.name || code}${Number(row.is_default) === 1 ? ' · Default' : ''}`;
        if (code === String(preferred || '').toUpperCase()) option.selected = true;
        country.appendChild(option);
      });
    };

    const initialize = async () => {
      try {
        setStatus('Loading enabled countries…');
        const numeric = /^\d+$/.test(initialValue) ? Number(initialValue) : 0;
        const countryToken = /^country:([A-Za-z]{2})$/.exec(initialValue);
        const data = numeric > 0
          ? await requestJson(`${endpoint}?location_id=${encodeURIComponent(String(numeric))}`)
          : await requestJson(endpoint);
        const selection = data.selection && typeof data.selection === 'object' ? data.selection : null;
        const selectedCountry = selection?.country_code || (countryToken ? countryToken[1].toUpperCase() : '');
        populateCountries(data.countries || [], selectedCountry);
        clearLevels(0);

        if (selection && Array.isArray(selection.path) && selectedCountry) {
          currentCountryName = findCountryName(selectedCountry);
          let parent = 0;
          let parentLabel = currentCountryName;
          for (let depth = 0; depth < selection.path.length; depth += 1) {
            const node = selection.path[depth];
            await loadLevel(selectedCountry, parent || null, depth, Number(node.id), parentLabel);
            parent = Number(node.id);
            parentLabel = String(node.name || parentLabel);
          }
          setChoice(String(numeric), parentLabel);
          if (parent) await loadLevel(selectedCountry, parent, selection.path.length, 0, parentLabel);
        } else if (selectedCountry) {
          currentCountryName = findCountryName(selectedCountry);
          setChoice(`country:${selectedCountry}`, currentCountryName);
          await loadLevel(selectedCountry, null, 0, 0, currentCountryName);
        } else {
          setChoice('', emptyLabel);
        }
        setStatus(`${countries.length} countries are currently enabled. Administrators can enable more in Location Settings.`);
      } catch (error) {
        setStatus(error.message || 'Unable to load locations.', true);
      }
    };

    country.addEventListener('change', async () => {
      clearLevels(0);
      const code = String(country.value || '').toUpperCase();
      if (!code) {
        currentCountryName = '';
        setChoice('', emptyLabel);
        setStatus('Search across all enabled locations.');
        return;
      }
      currentCountryName = findCountryName(code);
      setChoice(`country:${code}`, currentCountryName);
      setStatus('Loading administrative areas…');
      try {
        await loadLevel(code, null, 0, 0, currentCountryName);
        setStatus('Search the whole country or choose a more specific area.');
      } catch (error) {
        setStatus(error.message || 'Unable to load administrative areas.', true);
      }
    });

    trigger.addEventListener('click', () => panel.hidden ? open() : shut());
    close?.addEventListener('click', shut);
    done?.addEventListener('click', shut);
    clear?.addEventListener('click', () => {
      country.value = '';
      currentCountryName = '';
      clearLevels(0);
      setChoice('', emptyLabel);
      setStatus('Search across all enabled locations.');
    });
    document.addEventListener('click', (event) => {
      if (!panel.hidden && !root.contains(event.target)) shut();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !panel.hidden) shut();
    });

    initialize();
  });
})();
