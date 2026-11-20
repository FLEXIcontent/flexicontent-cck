window.fcfield_address = (() => {
  const shared = window.fcfield_addrint ||= {};
  const engines = shared.engines ||= {};
  const engineFor = id => {
    const container = document.getElementById(id + '_messages');
    return engines[container?.dataset.mapEngine] || Object.values(engines)[0];
  };

  for (const method of ['initAutoComplete', 'initMap', 'initMarkerSelector', 'changeAutoCompleteType', 'updateMarkerIcon']) {
    shared[method] = (id, ...args) => engineFor(id)?.[method]?.(id, ...args);
  }
  shared.toggle_USA_state = input => engineFor(input.id.replace(/_country$/, ''))?.toggle_USA_state(input);

  function setEnabled(button, enabled) {
    const row = button.closest('.fcfieldval_container');
    const properties = row?.querySelector('.fc-field-value-properties-box');
    if (!properties) return;
    FlexiForm.show(row.querySelector('.fcfield-enablevalue')?.parentElement, !enabled);
    FlexiForm.show(row.querySelector('.fcfield-disablevalue')?.parentElement, enabled);
    const controls = enabled
      ? properties.querySelectorAll('.fc-field-prop-disabled')
      : properties.querySelectorAll('input:enabled, select:enabled, textarea:enabled, button:enabled');
    for (const control of controls) {
      control.disabled = !enabled;
      control.classList.toggle('fc-field-prop-disabled', !enabled);
      const choices = control.fc_choices_instance;
      if (choices) enabled ? choices.enable() : choices.disable();
    }
    properties.classList.toggle('fc-field-value-disabled', !enabled);
    row.classList.toggle('fc-address-skipped', !enabled);
    row.querySelector('.fc-address-card')?.classList.toggle('fc-address-skipped', !enabled);
    const skip = row.querySelector('.fcfield-skipvalue');
    skip?.setAttribute('aria-pressed', String(!enabled));
    const icon = skip?.querySelector('.fc-skip-icon');
    icon?.classList.toggle('fa-eye', enabled);
    icon?.classList.toggle('fa-eye-slash', !enabled);
  }

  function toggleSkipped(button) {
    const properties = button.closest('.fcfieldval_container')?.querySelector('.fc-field-value-properties-box');
    if (properties) setEnabled(button, properties.classList.contains('fc-field-value-disabled'));
  }

  function prepare(row, ref, config) {
    const prefix = config.id + '_';
    for (const control of row.querySelectorAll('input, textarea, select')) {
      const original = control.dataset.originalId || control.id;
      const key = original.startsWith(prefix) ? original.slice(prefix.length).match(/^\d+_(.+)$/)?.[1] : null;
      if (!key) continue;
      FlexiFieldRepeater.setControl(row, control, ref.id + '_' + key, ref.name + '[' + key + ']', config.defaults[key] ?? '');
      if (key === 'autocomplete') delete control.dataset.osmInit;
    }
    for (const [selector, id] of [
      ['.addrint_messages', ref.id + '_messages'],
      ['.addrint_map_canvas', 'map_canvas_' + ref.id],
      ['.fcfield_addressint_map', ref.id + '_addressint_map'],
    ]) {
      const node = row.querySelector(selector);
      if (!node) continue;
      node.id = id;
      if (selector === '.fcfield_addressint_map') node.style.display = 'none';
      else node.replaceChildren();
      node.classList.remove('has_fc_google_maps_map', 'has_fc_openstreet_map', 'leaflet-container');
    }
    const zoom = row.querySelector('[id$="_zoom_label"]');
    if (zoom) {
      zoom.id = ref.id + '_zoom_label';
      zoom.textContent = config.defaults.zoom;
    }
    const skip = row.querySelector('.fcfield-skipvalue');
    if (skip) setEnabled(skip, true);
  }

  function start(id, key, showMap = false) {
    const field = window.fcfield_addrint;
    const autocomplete = document.getElementById(id + '_autocomplete');
    if (autocomplete) field?.initAutoComplete?.(id, key);
    if (showMap) field?.initMap?.(id, key);
    if (document.getElementById(id + '_custom_marker')) field?.initMarkerSelector?.(id, key);
  }

  function ready(row, ref, config) {
    if (config.mapEngine !== 'googlemap' || window.google?.maps) start(ref.id, config.fieldKey);
  }

  return { prepare, ready, setEnabled, toggleSkipped, start };
})();
