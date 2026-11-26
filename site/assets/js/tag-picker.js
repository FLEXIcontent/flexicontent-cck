/** Tag selection for the item editor and CSV import form. */
(() => {
  const pickers = new WeakSet();

  window.fc_initTagPicker = function (config) {
    const input = document.getElementById('input-tags');
    const assigned = document.getElementById('ultagbox');
    if (!input || !assigned || pickers.has(input)) return;
    pickers.add(input);

    const options = document.createElement('div');
    options.id = input.id + '-suggestions';
    options.className = 'fc-tag-suggestions';
    options.setAttribute('role', 'listbox');
    options.hidden = true;
    const status = document.createElement('div');
    status.className = 'fc-tag-status';
    status.setAttribute('role', 'status');
    status.hidden = true;
    input.after(options, status);
    input.classList.add('fc-tag-search');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', options.id);
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('autocomplete', 'off');

    const cache = new Map();
    let records = [];
    let visible = [];
    let highlighted = -1;
    let timer;
    let request;
    let saving = false;

    function isAssigned(id) {
      return !!document.getElementById('quick-tag-' + id)?.checked
        || [...assigned.querySelectorAll('input[type="hidden"]')].some(field => field.value === id);
    }

    function add(record) {
      const id = String(record.id);
      if (!/^[1-9]\d*$/.test(id) || isAssigned(id)) return;
      const quick = document.getElementById('quick-tag-' + id);
      if (quick) {
        quick.checked = true;
        FlexiForm.change(quick);
        return;
      }
      const row = document.createElement('li');
      row.className = 'tagitem';
      const label = document.createElement('span');
      label.textContent = record.label;
      const field = document.createElement('input');
      field.type = 'hidden';
      field.name = config.name;
      field.value = id;
      const remove = document.createElement('a');
      remove.className = 'deletetag';
      remove.href = '#';
      remove.title = config.removeLabel;
      remove.setAttribute('aria-label', config.removeLabel);
      row.append(label, field, remove);
      assigned.append(row);
    }

    function showStatus(message) {
      status.textContent = message;
      status.hidden = !message;
    }

    function highlight(index) {
      if (highlighted >= 0 && options.children[highlighted]) {
        options.children[highlighted].setAttribute('aria-selected', 'false');
      }
      highlighted = index;
      const active = options.children[index];
      if (active) {
        active.setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', active.id);
        active.scrollIntoView({ block: 'nearest' });
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function render() {
      highlight(-1);
      visible = records.filter(record => record.id !== '0' && !isAssigned(record.id));
      options.replaceChildren(...visible.map((record, index) => {
        const option = document.createElement('div');
        option.id = options.id + '-' + index;
        option.dataset.index = index;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        option.textContent = record.label;
        return option;
      }));
      options.hidden = !visible.length;
      input.setAttribute('aria-expanded', String(!options.hidden));
      showStatus(options.hidden ? records.find(record => record.id === '0')?.label || '' : '');
    }

    function close() {
      clearTimeout(timer);
      request?.abort();
      request = null;
      records = [];
      render();
    }

    async function search() {
      clearTimeout(timer);
      request?.abort();
      const controller = new AbortController();
      request = controller;
      const term = input.value;
      const url = new URL(config.suggestions, document.baseURI);
      url.searchParams.set('q', term);
      if (config.itemLanguage) url.searchParams.set('item_lang', document.getElementById('jform_language')?.value || '*');
      const key = url.href;
      try {
        if (!cache.has(key)) {
          const response = await fetch(url, {
            mode: 'same-origin', signal: controller.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
          });
          if (!response.ok) throw new Error(config.requestError);
          const data = await response.json();
          const results = data.map(item => ({
            id: String(item.id),
            name: item.name,
            label: item.name + (item.translated_text ? ' (' + item.translated_text + ')' : '')
          }));
          if (cache.size >= 50) cache.delete(cache.keys().next().value);
          cache.set(key, results);
        }
        if (controller.signal.aborted || document.activeElement !== input || input.value !== term) return;
        records = cache.get(key);
        render();
      } catch (error) {
        if (!controller.signal.aborted) showStatus(config.requestError);
      } finally {
        if (request === controller) request = null;
      }
    }

    function choose(record) {
      add(record);
      if (config.keepOpen) render();
      else {
        input.value = '';
        close();
      }
    }

    async function assignName() {
      const name = input.value.trim();
      if (!name || saving) return;
      const exact = records.find(record => record.id !== '0' && record.name === name);
      close();
      if (exact) {
        add(exact);
        input.value = '';
        return;
      }
      saving = true;
      try {
        const response = await fetch(config.assign, { method: 'POST', body: new URLSearchParams({ name }) });
        if (!response.ok) throw new Error(config.assignError);
        const payload = await response.text();
        const separator = payload.indexOf('|');
        const id = payload.slice(0, separator).trim();
        const label = payload.slice(separator + 1);
        if (separator < 0 || !/^[1-9]\d*$/.test(id)) {
          showStatus(id === '0' ? label : config.assignError);
          return;
        }
        add({ id, label });
        cache.clear();
        if (input.value.trim() === name) input.value = '';
      } catch (error) {
        showStatus(config.assignError);
      } finally {
        saving = false;
      }
    }

    input.addEventListener('focus', search);
    input.addEventListener('click', () => {
      if (options.hidden) search();
    });
    input.addEventListener('input', () => {
      close();
      timer = setTimeout(search, 200);
    });
    input.addEventListener('blur', close);
    if (config.itemLanguage) document.getElementById('jform_language')?.addEventListener('change', close);
    input.addEventListener('keydown', event => {
      if (event.isComposing) return;
      if (event.key === 'Enter') {
        event.preventDefault();
        if (highlighted >= 0) choose(visible[highlighted]);
        else assignName();
      } else if (event.key === 'Escape' && (!options.hidden || !status.hidden || request)) {
        event.preventDefault();
        event.stopPropagation();
        close();
      } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (options.hidden) search();
        else {
          const delta = event.key === 'ArrowDown' ? 1 : -1;
          highlight(highlighted === -1 && delta === -1 ? visible.length - 1 : (highlighted + delta + visible.length) % visible.length);
        }
      }
    });
    // Keep the input focused until an option click is handled.
    options.addEventListener('pointerdown', event => {
      if (event.target.closest('[role="option"]')) event.preventDefault();
    });
    options.addEventListener('click', event => {
      const option = event.target.closest('[role="option"]');
      if (option) choose(visible[Number(option.dataset.index)]);
    });
    assigned.addEventListener('click', event => {
      const button = event.target.closest('.deletetag');
      if (!button) return;
      event.preventDefault();
      button.closest('li')?.remove();
      input.focus();
      if (records.length) render();
    });
  };
})();
