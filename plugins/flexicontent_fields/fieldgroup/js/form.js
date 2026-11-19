/** Editing controls for repeatable groups of FLEXIcontent fields. */
window.fcfield_group = (() => {
  const repeaters = new WeakMap();

  function callChild(hook, row, child, options) {
    // Existing child plugins receive jQuery collections. Native hooks take nodes.
    const wrap = window.jQuery && !hook.fcNative ? window.jQuery : element => element;
    return hook(null, wrap(row), wrap(child), options);
  }

  function children(row, fields) {
    return fields.flatMap(field => {
      const element = row.querySelector('.fcfieldval_container_' + field.id);
      return element ? [{ ...field, element, add: window['addField' + field.id], remove: window['deleteField' + field.id] }] : [];
    });
  }

  function prepareCopy(row, prefix) {
    row.querySelectorAll('.fc-has-value, .invalid').forEach(element => element.classList.remove('fc-has-value', 'invalid'));
    row.querySelectorAll('[aria-invalid]').forEach(element => element.removeAttribute('aria-invalid'));
    row.querySelectorAll('.fc-field-invalid').forEach(element => element.remove());
    // Keep cloned Joomla editors from mounting before their child field is reindexed.
    row.querySelectorAll(':is(.fc_txtarea, .fc_termtext) joomla-editor-codemirror').forEach(editor => {
      const placeholder = document.createElement('div');
      for (const attribute of editor.attributes) placeholder.setAttribute(attribute.name, attribute.value);
      placeholder.dataset.fcEditor = editor.localName;
      placeholder.append(...editor.childNodes);
      editor.replaceWith(placeholder);
    });
    row.querySelectorAll('input, select, textarea, button').forEach((control, index) => {
      if (control.id) control.dataset.originalId = control.id;
      if (control.name) control.dataset.originalName = control.name;
      control.name = control.id = prefix + index;
    });
  }

  function attachConditions(row, fields, fallbackIndex) {
    const indices = new Map();
    for (const field of children(row, fields)) {
      const prefix = 'custom[' + field.name + ']';
      const control = [...field.element.querySelectorAll('[name]')].find(element => element.name.startsWith(prefix + '['));
      const index = control?.name.slice(prefix.length).match(/^\[(\d+)\]/)?.[1] ?? fallbackIndex;
      indices.set(prefix, index);
      const label = field.element.closest('.control-fc_subgroup')?.querySelector('label.fc_sub_label');
      if (label) {
        const id = 'custom_' + field.name + '_' + index;
        label.htmlFor = id;
        label.dataset.for = id;
        label.id = id + '-lbl';
      }
    }
    const conditions = row.querySelectorAll('[data-showon-pattern]');
    for (const element of conditions) {
      const rules = JSON.parse(element.dataset.showonPattern);
      for (const rule of rules) {
        const fieldName = rule.field.match(/^custom\[[^\]]+\]/)?.[0];
        rule.field = rule.field.replaceAll('[__n__]', '[' + (indices.get(fieldName) ?? fallbackIndex) + ']');
      }
      element.dataset.showon = JSON.stringify(rules);
      element.removeAttribute('data-showon-initialised');
    }
    if (conditions.length) window.Joomla?.Showon?.initialise(row);
  }

  function report(error) {
    console.error(error);
    window.Joomla?.renderMessages?.({ error: [FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED')] });
  }

  function repeater(config) {
    const list = document.getElementById('sortables_' + config.id);
    if (!list) return;
    if (repeaters.has(list)) return repeaters.get(list);
    let nextIndex = config.initialCount;

    function removeRow(row, fields) {
      for (const field of fields) {
        if (typeof field.remove !== 'function') continue;
        try {
          callChild(field.remove, row, field.element);
        } catch (error) {
          report(error);
        }
      }
      row.remove();
    }

    function add(suppliedRow, options = {}) {
      const previous = FlexiForm.element(suppliedRow) || list.lastElementChild;
      if (previous?.parentElement !== list) return;
      const { insert_before, remove_previous, scroll_visible = true, animate_visible = true } = options;
      if (!remove_previous && config.max && list.children.length >= config.max) {
        alert(FlexiForm.text('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + config.max);
        return 'cancel';
      }
      const index = nextIndex++;
      const prefix = '_duplicated_' + config.id + '_' + index + '_';
      const row = FlexiForm.cloneFieldRow(previous);
      prepareCopy(row, prefix);
      const initialized = [];
      if (insert_before) previous.before(row);
      else previous.after(row);
      try {
        for (const field of children(row, config.fields)) {
          if (typeof field.add !== 'function') {
            field.element.querySelectorAll('.fc-non-editable-value').forEach(element => { element.textContent = '-'; });
            continue;
          }
          const result = callChild(field.add, row, field.element, { remove_previous: 2, scroll_visible: 0, animate_visible: 0 });
          if (result === 'cancel') throw new Error('Could not add grouped field ' + field.name);
          initialized.push(field.id);
        }
        row.querySelectorAll('[name^="' + prefix + '"]').forEach(element => element.removeAttribute('name'));
        attachConditions(row, config.fields, index);
        fc_initTooltips(null, row);
      } catch (error) {
        removeRow(row, children(row, config.fields).filter(field => initialized.includes(field.id)));
        report(error);
        return 'cancel';
      }
      if (remove_previous) removeRow(previous, children(previous, config.fields));
      if (scroll_visible) fc_scrollIntoView(row, true);
      if (animate_visible && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        row.animate([{ opacity: 0.1 }, { opacity: 1 }], { duration: 400 });
      }
      return row;
    }

    function remove(button) {
      const row = button.closest('li');
      if (row?.parentElement !== list) return;
      const restoreFocus = row.contains(document.activeElement);
      let next = row.nextElementSibling || row.previousElementSibling;
      if (list.children.length === 1) {
        next = add(row, { remove_previous: true, scroll_visible: false, animate_visible: false });
      } else {
        removeRow(row, children(row, config.fields));
      }
      if (restoreFocus && next instanceof Element) next.querySelector('.fcfield-delvalue')?.focus({ preventScroll: true });
    }

    if (window.Sortable && !Sortable.get(list)) {
      Sortable.create(list, { draggable: '>li', handle: '.fcfield-drag-handle' });
    }
    const instance = { add, remove };
    repeaters.set(list, instance);
    return instance;
  }

  function toggleDetails(button, show) {
    const row = button.closest('li');
    row.classList.toggle('fc-fieldgroup-collapsed', !show);
    const opener = row.querySelector('.fc-toggle-group-down');
    const closer = row.querySelector('.fc-toggle-group-up');
    opener.hidden = show;
    closer.hidden = !show;
    if (document.activeElement === button || document.activeElement === document.body) {
      (show ? closer : opener).focus({ preventScroll: true });
    }
  }

  function toggleValues(id, show, focus = false) {
    const outer = document.getElementById('sortables_outer_' + id);
    if (!outer) return;
    outer.hidden = !show;
    const hideButton = document.getElementById('sortables_' + id + '_hide_vals_btn');
    const showButton = document.getElementById('sortables_' + id + '_show_vals_btn');
    if (hideButton) hideButton.hidden = !show;
    if (showButton) showButton.hidden = show;
    if (focus) (show ? hideButton : showButton)?.focus({ preventScroll: true });
  }

  return { repeater, toggleDetails, toggleValues };
})();
