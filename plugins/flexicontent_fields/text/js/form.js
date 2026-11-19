/** Text and Text Select editing controls. */
window.fcfield_text = (() => {
  const repeaters = new Map();

  function repeater(config) {
    if (repeaters.has(config.fieldId)) return repeaters.get(config.fieldId);
    const list = () => document.getElementById('sortables_' + config.fieldId);
    let index = config.initialCount;
    let groupCount = config.initialCount;

    function add(button, suppliedRow, options = {}) {
      const { insert_before = false, remove_previous = false, scroll_visible = true, animate_visible = true } = options;
      const container = list();
      const previous = FlexiForm.element(suppliedRow) || (button ? container?.lastElementChild : null);
      if (!previous) return;
      const count = container ? container.children.length : groupCount;
      if (!remove_previous && config.max && count >= config.max) {
        alert(FlexiForm.text('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + config.max);
        return 'cancel';
      }
      const row = previous.cloneNode(true);
      fc_destroySelectLib(row);
      row.querySelectorAll('.fc-has-value, .invalid').forEach(element => element.classList.remove('fc-has-value', 'invalid'));
      row.querySelectorAll('[aria-invalid]').forEach(element => element.removeAttribute('aria-invalid'));
      row.querySelectorAll('.fc-field-invalid').forEach(element => element.remove());
      const input = row.querySelector('input.fcfield_textval');
      if (!input) return;
      const currentId = input.id;
      input.id = config.id + '_' + index;
      input.name = config.name + '[' + index + ']';
      input.defaultValue = input.value = input.getAttribute('data-defvals') || config.defaultValue;
      for (const label of row.querySelectorAll('label')) {
        if (label.htmlFor !== currentId) continue;
        label.htmlFor = input.id;
        if (label.hasAttribute('data-for')) label.dataset.for = input.id;
      }
      for (const select of row.querySelectorAll('select.fcfield_textselval')) {
        select.id = input.id + '_select';
        select.selectedIndex = 0;
      }
      if (remove_previous) {
        fc_destroySelectLib(previous);
        previous.replaceWith(row);
      } else if (insert_before) previous.before(row);
      else previous.after(row);
      if (input.classList.contains('has_inputmask') && typeof window.Inputmask === 'function') {
        new window.Inputmask().mask(input);
      }
      fc_validationAttach(row);
      fc_initSelect2(null, row);
      fc_initTooltips(null, row);
      if (scroll_visible) fc_scrollIntoView(row, true);
      if (animate_visible && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        row.animate([{ opacity: 0.1 }, { opacity: 1 }], { duration: 400 });
      }
      index++;
      // Field groups pass 2 when replacing the copied row of a newly added group.
      if (!container && (!remove_previous || remove_previous === 2)) groupCount++;
    }

    function remove(button, suppliedRow) {
      const row = FlexiForm.element(suppliedRow) || button?.closest('li');
      if (!row?.isConnected) return;
      const container = list();
      const count = container ? container.children.length : groupCount;
      if (count <= 1) {
        add(null, row, { remove_previous: true, scroll_visible: false, animate_visible: false });
      } else {
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

  function selectValue(select) {
    const choice = select.fc_choices_instance;
    const chosen = choice?.getValue(true);
    const value = Array.isArray(chosen) ? chosen.filter(item => item !== '' && item != null).at(-1) : chosen;
    let ancestor = select.parentElement;
    let input = null;
    while (ancestor && !input) {
      input = ancestor.querySelector('input.fcfield_textval');
      ancestor = ancestor.parentElement;
    }
    if (input) {
      input.value = value === '' || value == null ? select.value : value;
      input.dispatchEvent(new FocusEvent('blur'));
    }
    if (choice) choice.removeActiveItems();
    else if (window.jQuery?.fn.select2 && window.jQuery(select).data('select2')) window.jQuery(select).select2('val', '');
    else select.selectedIndex = 0;
  }

  return { repeater, attachOrdering, selectValue };
})();
