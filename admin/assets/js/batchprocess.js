/** Batch options and dynamically loaded field cards. */
function toggle_importlang_info() {
  FlexiForm.show(document.getElementById('falang-import-info'), !!document.querySelector('#method-usejoomfish:checked, #method-firstjf-thenauto:checked'));
}
function fc_batch_option(id, enabled, visible = enabled) {
  const field = document.getElementById(id);
  if (field) field.disabled = !enabled;
  FlexiForm.show(document.getElementById('row_' + id), visible);
}
function fc_batch_behavior(mode) {
  const copy = mode !== 'moveonly', only = mode === 'copyonly';
  FlexiForm.show(document.getElementById('row_copy_options'), copy);
  for (const id of ['maincat', 'language', 'state']) fc_batch_option(id, true);
  for (const id of ['prefix', 'suffix', 'copynr']) fc_batch_option(id, copy);
  for (const id of ['type_id', 'access', 'seccats']) fc_batch_option(id, !only);
  FlexiForm.show(document.getElementById('row_keepseccats'), !only);
  for (const id of ['keepseccats0', 'keepseccats1']) fc_batch_option(id, !only);
  if (!only) document.getElementById('keepseccats1')?.click();
  for (const id of ['keeptags', 'tags']) FlexiForm.show(document.getElementById('row_' + id));
  for (const id of ['keeptags0', 'keeptags1', 'keeptags2']) fc_batch_option(id, true);
  document.getElementById('keeptags2')?.click();
  document.querySelectorAll('.lang').forEach(el => { el.disabled = false; });
  const state = document.getElementById('state');
  if (state) { state.value = copy ? '0' : ''; FlexiForm.change(state); }
  toggle_importlang_info();
}
function moveonly() { fc_batch_behavior('moveonly'); }
function copymove() { fc_batch_behavior('copymove'); }
function copyonly() { fc_batch_behavior('copyonly'); }
function seccats_on() { fc_batch_option('seccats', true); }
function seccats_off() { fc_batch_option('seccats', false, true); }
function tags_on() { fc_batch_option('tags', true); }
function tags_off() {
  fc_batch_option('tags', false, true);
  const field = document.getElementById('tags');
  if (field) { for (const option of field.options) option.selected = false; FlexiForm.change(field); }
}
function tags_update() { fc_batch_option('tags', true); }

var fc_batch_fields = {
  loaded_scripts: {},
  scriptLoaded(src) {
    const clean = new URL(src, document.baseURI).href.split('?')[0];
    return !!this.loaded_scripts[clean] || Array.from(document.scripts).some(el => el.src.split('?')[0] === clean);
  },
  async loadScripts(list, done) {
    for (const item of list) {
      const src = typeof item === 'string' ? item : item.src;
      if (!src) continue;
      const clean = new URL(src, document.baseURI).href.split('?')[0];
      if (this.loaded_scripts[clean]) { await this.loaded_scripts[clean]; continue; }
      if (this.scriptLoaded(src)) continue;
      this.loaded_scripts[clean] = new Promise((resolve, reject) => {
        const script = document.createElement('script'); script.src = src;
        if (item.module) script.type = 'module'; else script.async = false;
        script.onload = resolve;
        script.onerror = () => { script.remove(); delete this.loaded_scripts[clean]; reject(new Error('Field script failed to load')); };
        document.head.append(script);
      });
      await this.loaded_scripts[clean];
    }
    if (done) done();
  },
  async loadAssets(assets, done) {
    if (assets.options && Joomla.loadOptions) Joomla.loadOptions(assets.options);
    if (assets.importmap) await this.runInline({code: JSON.stringify(assets.importmap), type: 'importmap'});
    for (const href of assets.styles || []) {
      const url = new URL(href, document.baseURI).href.split('?')[0];
      if (Array.from(document.querySelectorAll('link[rel=stylesheet]')).some(el => el.href.split('?')[0] === url)) continue;
      const link = document.createElement('link'); link.rel = 'stylesheet'; link.href = href; document.head.append(link);
    }
    for (const css of assets.style_inline || []) { const style = document.createElement('style'); style.textContent = css; document.head.append(style); }
    await this.loadScripts(assets.scripts || []);
    if (done) done();
  },
  refreshPicker() {
    const picker = document.getElementById('batch_field_picker');
    const choices = window.fc_choices_instances?.[picker?.id || picker?.name];
    if (choices?.refresh) choices.refresh();
    // Select2 remains an explicitly configured legacy select library.
    if (window.jQuery && picker && window.jQuery(picker).data('select2')) window.jQuery(picker).trigger('change.select2');
  },
  async addField(fieldId, label, multiple) {
    fieldId = Number(fieldId);
    if (!Number.isInteger(fieldId) || fieldId < 1) return;
    const container = document.getElementById('batch_field_cards');
    if (!container || container.querySelector('[data-field-id="' + fieldId + '"]')) return;
    const card = document.getElementById('batch_field_card_tmpl').content.firstElementChild.cloneNode(true);
    card.dataset.fieldId = fieldId; card.classList.add('fc-loading');
    card.querySelector('.batch-field-label').textContent = label;
    const mode = card.querySelector('.batch-field-mode'), body = card.querySelector('.batch-field-body');
    mode.name = 'batch_fields[' + fieldId + '][mode]'; mode.disabled = true;
    if (!multiple) mode.querySelector('option[value=add]')?.remove();
    const controller = new AbortController();
    card.querySelector('.batch-field-remove').addEventListener('click', () => {
      controller.abort(); card.remove();
      const option = document.querySelector('#batch_field_picker option[value="' + fieldId + '"]');
      if (option) option.disabled = false;
      this.refreshPicker();
    });
    container.append(card);
    try {
      const url = new URL(FlexiForm.data(container, 'url'), document.baseURI); url.searchParams.set('field_id', fieldId);
      if (url.origin !== location.origin) throw new Error('Invalid field URL');
      const response = await fetch(url, {credentials: 'same-origin', signal: controller.signal, headers: {'X-Requested-With': 'XMLHttpRequest'}});
      if (!response.ok || response.redirected) throw new Error('Field request failed');
      const result = await response.json();
      if (!result || result.error) throw new Error(result?.error || 'Field response is empty');
      if (!card.isConnected) return;
      await this.loadAssets(result.assets || {});
      if (!card.isConnected) return;
      // These scripts are generated by the selected field on the authenticated same-origin endpoint.
      const template = document.createElement('template'); template.innerHTML = result.html;
      const scripts = [...template.content.querySelectorAll('script')]; scripts.forEach(el => el.remove());
      body.replaceChildren(template.content);
      for (const original of scripts) {
        if (original.src) await this.loadScripts([{src: original.src, module: original.type === 'module'}]);
        else await this.runInline({code: original.textContent, type: original.type});
      }
      for (const code of result.assets?.inline || []) await this.runInline(code);
      this.stripValidation(card);
      card.querySelectorAll('.field-calendar').forEach(el => { if (window.JoomlaCalendar) JoomlaCalendar.init(el); });
      card.querySelectorAll('select[multiple].use_select2_lib').forEach(el => el.classList.add('fc_select2_no_check'));
      if (window.fc_initSelect2) fc_initSelect2(null, card);
      mode.disabled = false;
    } catch (error) {
      if (!card.isConnected) return;
      const warning = document.createElement('div'); warning.className = 'alert alert-warning'; warning.textContent = error.message;
      body.replaceChildren(warning); mode.disabled = true;
    } finally { card.classList.remove('fc-loading'); }
  },
  async runInline(value) {
    const code = typeof value === 'string' ? value : value?.code;
    if (typeof code !== 'string' || !code.trim()) return;
    const script = document.createElement('script'); script.textContent = code; script.type = value?.type || 'text/javascript';
    const nonce = document.querySelector('script[nonce]')?.nonce; if (nonce) script.nonce = nonce;
    // Inline modules do not dispatch a load event. Leave their execution and
    // dependency ordering to the browser instead of waiting indefinitely.
    document.head.append(script);
    if (script.type !== 'module' && script.type !== 'importmap') script.remove();
  },
  stripValidation(card) {
    card = FlexiForm.element(card);
    const drop = new Set(['required', 'required_boxed', 'required_box', 'invalid', 'validate-radio', 'validate-checkbox', 'validate-cboxlimitations', 'validate-sellimitations']);
    card.querySelectorAll('*').forEach(el => {
      for (const name of [...el.classList]) if (drop.has(name)) el.classList.remove(name);
      for (const attr of ['required', 'aria-required', 'aria-invalid']) el.removeAttribute(attr);
    });
  },
  toggleMode(select) { FlexiForm.show(select.closest('.batch-field-card').querySelector('.batch-field-body'), select.value !== 'clear'); }
};

FlexiForm.ready(() => {
  const mode = document.querySelector('input[name=initial_behaviour]')?.value;
  if (['copyonly', 'copymove', 'moveonly'].includes(mode)) fc_batch_behavior(mode);
  document.querySelectorAll('input[name=keeptags]').forEach(el => el.addEventListener('change', tags_update));
  tags_update();
  document.getElementById('type_id')?.addEventListener('change', event => FlexiForm.show(document.getElementById('fc-change-warning'), !!event.target.value));
  document.getElementById('batch_field_picker')?.addEventListener('change', event => {
    const select = event.target, option = select.selectedOptions[0], id = Number(option?.value);
    if (!id) return;
    fc_batch_fields.addField(id, option.textContent.trim().replace(/\s{2,}\(.*\)$/, ''), option.dataset.multiple === '1');
    option.disabled = true; select.value = ''; fc_batch_fields.refreshPicker();
  });
  document.getElementById('batch_field_cards')?.addEventListener('change', event => {
    if (event.target.matches('.batch-field-mode')) fc_batch_fields.toggleMode(event.target);
  });
});
