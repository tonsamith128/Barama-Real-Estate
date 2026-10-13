/* Footer editor menu rows: accessible add/remove/reorder, no extra dependencies. */
(() => {
  'use strict';

  function initialize() {
    const form = document.querySelector('.barama-footer-editor__form');
    if (!form) return;

    const MAX_ITEMS = 15;
    const columns = ['brand', 'marketplace', 'compliance', 'help'];

    function listFor(column) {
      if (!columns.includes(column)) return null;
      return form.querySelector('[data-footer-list="' + column + '"]');
    }

    function rows(list) {
      return Array.from(list.querySelectorAll(':scope > [data-footer-item]'));
    }

    function sync(column) {
      const list = listFor(column);
      if (!list) return;
      const items = rows(list);
      items.forEach((item, index) => {
        const label = item.querySelector('[data-footer-label]');
        const url = item.querySelector('[data-footer-url]');
        if (label) label.name = 'footer_links[' + column + '][' + index + '][label]';
        if (url) url.name = 'footer_links[' + column + '][' + index + '][url]';

        const up = item.querySelector('[data-footer-up]');
        const down = item.querySelector('[data-footer-down]');
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === items.length - 1;
      });

      const host = list.closest('[data-footer-column]');
      const add = host?.querySelector('[data-footer-add]');
      const empty = host?.querySelector('[data-footer-empty]');
      if (add) {
        add.disabled = items.length >= MAX_ITEMS;
        add.title = add.disabled ? 'Maximum 15 menu items per column' : 'Add a menu item';
      }
      if (empty) empty.hidden = items.length !== 0;
    }

    function createRow() {
      const item = document.createElement('div');
      item.className = 'barama-footer-editor__link-row';
      item.dataset.footerItem = '';

      const fields = document.createElement('div');
      fields.className = 'barama-footer-editor__link-fields';

      for (const [labelText, flag, max, placeholder] of [
        ['Label', 'footerLabel', 80, 'Menu label'],
        ['URL', 'footerUrl', 500, '/page or https://example.com'],
      ]) {
        const wrapper = document.createElement('label');
        wrapper.textContent = labelText;
        const input = document.createElement('input');
        input.type = 'text';
        input.maxLength = max;
        input.required = true;
        input.placeholder = placeholder;
        input.dataset[flag] = '';
        wrapper.append(input);
        fields.append(wrapper);
      }

      const actions = document.createElement('div');
      actions.className = 'barama-footer-editor__item-actions';
      actions.setAttribute('role', 'group');
      actions.setAttribute('aria-label', 'Reorder or remove menu item');

      for (const [flag, text, description] of [
        ['footerUp', '↑ Up', 'Move item up'],
        ['footerDown', '↓ Down', 'Move item down'],
        ['footerRemove', 'Remove', 'Remove menu item'],
      ]) {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset[flag] = '';
        button.textContent = text;
        button.setAttribute('aria-label', description);
        actions.append(button);
      }
      item.append(fields, actions);
      return item;
    }

    form.addEventListener('click', (event) => {
      const target = event.target.closest('button');
      if (!target || !form.contains(target)) return;

      if (target.hasAttribute('data-footer-add')) {
        const column = target.dataset.footerAdd;
        const list = listFor(column);
        if (!list || rows(list).length >= MAX_ITEMS) return;
        const row = createRow();
        list.append(row);
        sync(column);
        row.querySelector('[data-footer-label]')?.focus();
        return;
      }

      const row = target.closest('[data-footer-item]');
      const list = row?.parentElement;
      const column = list?.dataset.footerList;
      if (!row || !listFor(column) || !list.isSameNode(listFor(column))) return;

      if (target.hasAttribute('data-footer-remove')) {
        const focus = row.nextElementSibling || row.previousElementSibling;
        row.remove();
        sync(column);
        focus?.querySelector('[data-footer-label]')?.focus();
        if (!focus) {
          form.querySelector('[data-footer-add="' + column + '"]')?.focus();
        }
      } else if (target.hasAttribute('data-footer-up')) {
        const prev = row.previousElementSibling;
        if (prev) list.insertBefore(row, prev);
        sync(column);
        row.querySelector('[data-footer-up]')?.focus();
      } else if (target.hasAttribute('data-footer-down')) {
        const next = row.nextElementSibling;
        if (next) list.insertBefore(next, row);
        sync(column);
        row.querySelector('[data-footer-down]')?.focus();
      }
    });

    form.addEventListener('submit', () => {
      columns.forEach(sync);
    });
    columns.forEach(sync);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
