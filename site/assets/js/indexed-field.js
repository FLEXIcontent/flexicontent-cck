/** Repeatable list, radio and checkbox values. */
window.fcfield_indexed = (() => {
  function prepare(row, ref, config) {
    const select = row.querySelector('select.fcfield_textselval');
    const set = row.querySelector('.fc_input_set');
    const name = ref.name + (config.arrayValue ? '[]' : '');
    if (select) {
      const defaults = select.dataset.defvals?.split('|||') || [''];
      FlexiFieldRepeater.setControl(row, select, ref.id, name, defaults);
      select.setAttribute('data-uniqueRowNum', ref.index);
      if (select.hasAttribute('data-select2-initdata')) {
        const selected = [...select.selectedOptions].filter(option => option.value);
        select.dataset.select2Initdata = JSON.stringify(selected.map(option => ({ id: option.value, text: option.text })));
      }
    } else if (set) {
      for (const wrapper of set.querySelectorAll('.prettyradio, .prettycheckbox')) {
        const controls = [...wrapper.children].filter(element => element.matches('input, label'));
        controls.find(control => control.matches('input'))?.style.removeProperty('display');
        wrapper.replaceWith(...controls);
      }
      set.id = ref.id;
      const inputs = set.querySelectorAll('input[type=radio], input[type=checkbox]');
      inputs.forEach((input, index) => {
        const oldGroup = input.dataset.elementGrpid;
        if (oldGroup) input.classList.remove(oldGroup);
        input.classList.add(ref.id);
        input.dataset.elementGrpid = ref.id;
        FlexiFieldRepeater.setControl(row, input, ref.id + '_' + index, name, input.hasAttribute('data-is-defval'));
      });
    } else {
      return false;
    }
    const holder = row.querySelector('.fcfield_value_holder');
    if (holder) FlexiFieldRepeater.setControl(row, holder, config.holderId + '_' + ref.index, config.holderName + '[' + ref.index + ']', '-');
  }

  function attach(row) {
    const jquery = window.jQuery;
    if (!jquery?.fn.prettyCheckable) return;
    for (const input of row.querySelectorAll('input.use_prettycheckable')) {
      const label = input.labels[0];
      const html = label?.innerHTML || '';
      label?.remove();
      jquery(input).prettyCheckable({ label: html });
    }
  }

  function ready(row, ref, config) {
    // A group may render the dependent field before its controlling field.
    queueMicrotask(() => {
      if (!row.isConnected) return;
      const cascade = config.cascade;
      if (!cascade) {
        FlexiForm.change(document.getElementById(ref.id));
        return;
      }
      const group = row.closest('.fcfield-group > li');
      const prefix = cascade.sourceBase + '_';
      const master = !cascade.singleMaster && group
        ? [...group.querySelectorAll('select[id], .fc_input_set[id]')].find(element => element.id.startsWith(prefix))
        : null;
      const source = master?.id || prefix + (cascade.singleMaster ? 0 : ref.index);
      fcCascadedField(config.fieldId, cascade.itemId, cascade.fieldType, 'select#' + source + ', input.' + source,
        ref.id, cascade.prompt, cascade.promptEnabled, ref.index, Boolean(master));
    });
  }

  return { prepare, attach, ready };
})();
