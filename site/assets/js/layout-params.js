/** Load a selected layout's parameter form, keeping Joomla/Choices initialization. */
async function fc_loadLayoutParams(select, initial, options) {
  if (!select || (initial && select.dataset.fcLayoutFirstRun)) return;
  const selected = select.selectedOptions[0];
  if (!selected) return;
  const filename = selected.dataset.filename || options.stripPrefix + select.value;
  if (!options.remote) {
    const base = document.querySelector('a[href="#attrib-' + options.container + '"]');
    if (base) base.parentElement.hidden = true;
    for (const name of options.layouts) {
      const tab = document.querySelector('a[href="#attrib-' + options.container + '_' + name + '"]');
      if (tab) tab.parentElement.hidden = name !== (selected.dataset.filename || select.value);
    }
    select.dataset.fcLayoutFirstRun = 'true'; return;
  }
  let header = document.querySelector('a[href="#attrib-' + options.container + '"]');
  let panel = document.getElementById('attrib-' + options.container);
  if (!header) {
    header = document.getElementById(options.container);
    const tabs = header?.closest('.tabberlive');
    if (tabs) {
      tabs.querySelectorAll(':scope > .fc_empty_box,:scope > .field-spacer').forEach(node=>node.remove());
      panel = tabs.children[Array.from(header.parentElement.parentElement.children).indexOf(header.parentElement)+1];
    }
  }
  if (!header || !panel) return;
  select.fcAppliedLayout ??= select.value;
  select.dataset.fcLayoutFirstRun = 'true';
  select.fcLayoutRequest?.abort();
  const controller = select.fcLayoutRequest = new AbortController();
  const url = new URL(options.url, document.baseURI);
  url.searchParams.set('layout_name', filename);
  panel.setAttribute('aria-busy', 'true');
  try {
    const response = await fetch(url, {
      credentials: 'same-origin', signal: controller.signal, headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    if (!response.ok || response.redirected) throw Error(response.statusText || FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED'));
    const html = await response.text();
    if (controller.signal.aborted || !select.isConnected) return;
    const previous = [...panel.childNodes];
    try {
      fc_initDynamicLayoutJsCss(panel.id, ['subform-row-add'], html);
    } catch (error) {
      panel.replaceChildren(...previous);
      throw error;
    }
    select.fcAppliedLayout = selected.value;
    let name = filename;
    for (const prefix of [options.stripPrefix, options.trimDisplayName]) {
      if (prefix && name.startsWith(prefix)) name = name.slice(prefix.length);
    }
    const defaultName = selected.value && selected.value !== '-1' ? name.replaceAll('_', ' ') : selected.label;
    header.textContent = options.label + ': ' + (selected.dataset.displayname || defaultName);
    header.parentElement.style.display = '';
  } catch (error) {
    if (controller.signal.aborted || !select.isConnected) return;
    if (initial) delete select.dataset.fcLayoutFirstRun;
    select.value = select.fcAppliedLayout;
    select.fc_choices_instance?.setChoiceByValue(select.fcAppliedLayout);
    FlexiForm.message('error', error.message);
  } finally {
    if (select.fcLayoutRequest === controller) {
      delete select.fcLayoutRequest;
      panel.removeAttribute('aria-busy');
    }
  }
}

(() => {
  const selector = 'select[data-fc-layout-params]';
  const load = (select, initial) => fc_loadLayoutParams(select, initial, JSON.parse(select.dataset.fcLayoutParams));
  const init = root => root.querySelectorAll(selector).forEach(select => load(select, true));
  FlexiForm.ready(() => init(document));
  document.addEventListener('initialize-fc-element', event => {
    if (event.target.matches(selector)) load(event.target, true);
  });
  document.addEventListener('joomla:updated', event => init(event.target));
  document.addEventListener('change', event => {
    if (event.target.matches(selector)) load(event.target, false);
  });
  document.addEventListener('submit', event => {
    const form = event.target;
    if (/(^|\.)cancel$/.test(form.elements.namedItem('task')?.value || '')) return;
    const pending = Array.from(form.elements).some(control => control.matches(selector) && control.fcLayoutRequest)
      || form.querySelector('#fieldspecificproperties[aria-busy="true"]');
    if (!pending) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    FlexiForm.message('warning', FlexiForm.text('FLEXI_PLEASE_WAIT'));
  }, true);
})();
