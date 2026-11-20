/** Shared controls for standalone and grouped field values. */
window.FlexiFieldRepeater = (() => {
  const repeaters = new Map();

  function setControl(row, control, id, name, value) {
    const oldId = control.dataset.originalId || control.id;
    control.id = id;
    control.name = name;
    delete control.dataset.originalId;
    delete control.dataset.originalName;
    if (control instanceof HTMLSelectElement) {
      const values = new Set(Array.isArray(value) ? value : [value]);
      for (const option of control.options) option.defaultSelected = option.selected = values.has(option.value);
      if (!control.multiple && !Array.from(control.options).some(option => values.has(option.value))) control.selectedIndex = -1;
    } else if (control.matches('input[type=radio], input[type=checkbox]')) {
      control.defaultChecked = control.checked = value;
    } else {
      control.defaultValue = control.value = value;
    }
    for (const label of row.querySelectorAll('label')) {
      if (!oldId || label.htmlFor !== oldId) continue;
      label.htmlFor = id;
      if (label.hasAttribute('data-for')) label.dataset.for = id;
    }
  }

  function prepareControls(row, ref, config) {
    for (const { selector, key, value, idSuffix = key } of config.controls) {
      const control = row.querySelector(selector);
      if (control) setControl(row, control, ref.id + '_' + idSuffix, ref.name + '[' + key + ']', value);
    }
  }

  function get(config, adapter = null) {
    if (repeaters.has(config.fieldId)) return repeaters.get(config.fieldId);
    const list = () => document.getElementById('sortables_' + config.fieldId);
    let index = config.initialCount;
    let groupCount = config.initialCount;

    function add(button, suppliedRow, options = {}) {
      const { insert_before = false, remove_previous = false, scroll_visible = true, animate_visible = true } = options;
      const container = list();
      const previous = FlexiForm.element(suppliedRow) || (button ? container?.lastElementChild : null);
      if (!previous?.isConnected) return 'cancel';
      const count = container ? container.children.length : groupCount;
      if (!remove_previous && config.max && count >= config.max) {
        FlexiForm.alert(FlexiForm.text('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + config.max);
        return 'cancel';
      }
      const row = FlexiForm.cloneFieldRow(previous);
      fc_destroySelectLib(row);
      row.querySelectorAll('.fc-has-value, .invalid').forEach(element => element.classList.remove('fc-has-value', 'invalid'));
      row.querySelectorAll('[aria-invalid]').forEach(element => element.removeAttribute('aria-invalid'));
      row.querySelectorAll('.fc-field-invalid').forEach(element => element.remove());
      const ref = { id: config.id + '_' + index, name: config.name + '[' + index + ']', index };
      if ((adapter?.prepare || prepareControls)(row, ref, config) === false) return 'cancel';
      if (insert_before) previous.before(row);
      else previous.after(row);
      try {
        adapter?.attach?.(row, ref, config);
        window.fc_initInputMasks?.(row);
        fc_validationAttach(row);
        fc_bootstrapAttach(row);
        fc_initSelect2(null, row);
        fc_initTooltips(null, row);
        adapter?.ready?.(row, ref, config, { animate_visible });
      } catch (error) {
        adapter?.dispose?.(row, config);
        fc_destroySelectLib(row);
        row.remove();
        throw error;
      }
      if (remove_previous) {
        if (remove_previous !== 2) adapter?.dispose?.(previous, config);
        fc_destroySelectLib(previous);
        previous.remove();
      }
      index++;
      // A group passes 2 when we replace a copied field in a newly added group.
      if (!container && (!remove_previous || remove_previous === 2)) groupCount++;
      if (scroll_visible) fc_scrollIntoView(row, true);
      if (animate_visible && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        row.animate([{ opacity: 0.1 }, { opacity: 1 }], { duration: 400 });
      }
      return adapter?.result?.(ref, config);
    }

    function remove(button, suppliedRow) {
      const row = FlexiForm.element(suppliedRow) || button?.closest('li');
      if (!row?.isConnected) return;
      const container = list();
      const count = container ? container.children.length : groupCount;
      if (count <= 1) {
        add(null, row, { remove_previous: true, scroll_visible: false, animate_visible: false });
      } else {
        adapter?.dispose?.(row, config);
        fc_destroySelectLib(row);
        row.remove();
        if (!container) groupCount--;
      }
    }

    const instance = { add, remove };
    repeaters.set(config.fieldId, instance);
    return instance;
  }

  function attachOrdering(fieldId) {
    const list = document.getElementById('sortables_' + fieldId);
    if (list && window.Sortable && !Sortable.get(list)) {
      Sortable.create(list, { draggable: '.fcfieldval_container', handle: '.fcfield-drag-handle' });
    }
  }

  return { get, setControl, attachOrdering };
})();
