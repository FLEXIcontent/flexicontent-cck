/** Text and Text Select editing controls. */
window.fcfield_text = (() => {
  function prepare(row, ref, config) {
    const input = row.querySelector('input.fcfield_textval');
    if (!input) return false;
    FlexiFieldRepeater.setControl(row, input, ref.id, ref.name, input.dataset.defvals || config.defaultValue);
    for (const select of row.querySelectorAll('select.fcfield_textselval')) {
      select.id = ref.id + '_select';
      select.selectedIndex = 0;
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

  return { prepare, selectValue };
})();
