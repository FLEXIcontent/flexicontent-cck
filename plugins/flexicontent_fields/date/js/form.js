/** Prepare new date values for Joomla's calendar. */
window.fcfield_date = (() => {
  function prepare(row, ref, config) {
    const input = row.querySelector('input.fcfield_date');
    if (!input) return false;
    FlexiFieldRepeater.setControl(row, input, ref.id, ref.name, '');
    input.dataset.altValue = '';
    delete input.dataset.localValue;
    if (config.timestamp) {
      const current = row.querySelector('.fcfield_timestamp_value_new');
      if (current) current.style.removeProperty('display');
      const previous = row.querySelector('.fcfield_timestamp_value_existing');
      if (previous) {
        previous.textContent = '';
        previous.style.display = 'none';
      }
      return;
    }

    const calendar = input.closest('.field-calendar');
    const button = input.nextElementSibling;
    if (calendar && button) {
      calendar.id = 'field-calendar-fc_' + config.fieldId + '_' + (ref.index + 1);
      calendar.querySelector('.calendar-container')?.remove();
      button.id = ref.id + '_btn';
      button.dataset.inputfield = ref.id;
      button.dataset.button = button.id;
    } else if (button) {
      button.id = ref.id + '_img';
    }
  }

  function attach(row, ref, config) {
    if (config.timestamp) return;
    const calendar = row.querySelector('.field-calendar');
    if (calendar && window.JoomlaCalendar) JoomlaCalendar.init(calendar);
    else window.Calendar?.setup?.({
      inputField: ref.id, ifFormat: config.format, button: ref.id + '_img', align: 'Tl', singleClick: true,
    });
  }

  // Picker-only dates still allow focus, and Joomla can write a selected date.
  document.addEventListener('beforeinput', event => {
    if (event.target.matches?.('input.fcfield-date-picker-only')) event.preventDefault();
  });

  return { prepare, attach };
})();
