/** Native submission, including the large-form PHP input-limit workaround. */
function fc_submit_form(form, task) {
  if (form.fc_ajax_saving) return false;
  const F = FlexiForm;
  const cancel = /(^|\.)cancel$/.test(task);
  const ajax = /(^|\.)apply_ajax$/.test(task)
    || (/(^|\.)apply$/.test(task) && form.hasAttribute('data-fc_force_apply_ajax'));
  const serialize = !form.hasAttribute('data-fc_doserialized_submit') || !!form.getAttribute('data-fc_doserialized_submit');
  const force = form.hasAttribute('data-fc_force_serialization')
    ? !!form.getAttribute('data-fc_force_serialization') : form.id === 'component-form';
  const max = Joomla.fc_max_input_vars || 1000;
  let disabled = [], injected = [];
  const restore = () => {
    injected.forEach(el => el.remove());
    disabled.forEach(el => { el.disabled = false; });
    injected = []; disabled = []; form.form_was_serialized = 0;
  };
  const hidden = (name, value) => {
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = name; input.value = value;
    form.append(input); injected.push(input); return input;
  };
  const submit = event => {
    if (event.defaultPrevented) return;
    if (serialize && (force || form.elements.length >= max)) {
      const info = {}, fields = Joomla.serializeForm(form, info);
      if (force || info.fields_active.length >= max) {
        hidden('fcdata_serialized', JSON.stringify(fields));
        hidden('fcdata_serialized_count', info.fields_active.length);
        disabled = info.fields_active;
        disabled.forEach(el => { el.disabled = true; });
        form.form_was_serialized = 1;
      }
    }
    // Preserve full multipart submission when a file is selected.
    const hasFile = Array.from(form.querySelectorAll('input[type=file]:enabled')).some(el => el.value);
    if (!ajax || hasFile) {
      if (!cancel) fc_showSubmitProgress();
      setTimeout(restore, 0); // downloads may leave this page open
      return;
    }
    event.preventDefault(); form.fc_ajax_saving = true;
    hidden('fc_doajax_submit', '1');
    const body = new URLSearchParams();
    for (const [name, value] of new FormData(form)) if (typeof value === 'string') body.append(name, value);
    const loading = document.createElement('span');
    loading.id = 'fc_doajax_loading'; loading.textContent = '… Saving';
    form.after(loading); F.show(form, false);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 120000);
    let settled = false;
    const cleanup = () => {
      if (settled) return;
      settled = true; clearTimeout(timeout); restore(); form.fc_ajax_saving = false;
      loading.remove(); document.getElementById('fc_filter_form_blocker')?.remove();
      F.show(F.data(form, 'btn_box')); F.show(F.data(form, 'msg_box'), false); F.show(form);
    };
    const fail = () => {
      cleanup();
      const key = 'FLEXI_AJAX_SAVE_UNCONFIRMED', message = F.text(key);
      const box = document.getElementById('system-message-container');
      if (box) {
        const warning = document.createElement('div');
        warning.className = 'alert alert-danger'; warning.setAttribute('role', 'alert');
        warning.textContent = message && message !== key ? message : 'The save could not be confirmed. Your entries are still in this form. Keep this page open and try again.';
        box.replaceChildren(warning);
      }
      form.dispatchEvent(new CustomEvent('flexicontent:save-failed', {bubbles: true}));
    };
    const url = new URL(form.action, document.baseURI), method = (form.method || 'post').toUpperCase();
    const data = body.toString();
    if (method === 'GET') body.forEach((value, key) => url.searchParams.append(key, value));
    form.dispatchEvent(new CustomEvent('flexicontent:save-start', {bubbles: true, detail:{url:url.href, data}}));
    const receive = html => {
      cleanup();
      const box = document.getElementById('system-message-container');
      if (!box) return;
      // This endpoint returns Joomla's message fragment; do not execute returned scripts.
      const template = document.createElement('template'); template.innerHTML = html;
      template.content.querySelectorAll('script').forEach(el => el.remove());
      if (!template.content.querySelector('.alert, joomla-alert')) { fail(); return; }
      box.replaceChildren(template.content);
      if (box.querySelector('.alert-success, .alert-message, joomla-alert[type=success], joomla-alert[type=message]')
        && !box.querySelector('.alert-danger, .alert-error, joomla-alert[type=danger], joomla-alert[type=error]')) {
        form.dispatchEvent(new CustomEvent('flexicontent:save-confirmed', {detail:{status:200, responseText:html}}));
      }
    };
    // Restore the form and messages BEFORE legacy global ajaxSuccess/ajaxError
    // listeners. Existing editor plugins use those hooks for their saved baseline.
    if (window.jQuery?.ajax) {
      try {
        const request = window.jQuery.ajax({url: url.href, type: method, data: method === 'GET' ? undefined : body.toString(),
          dataType: 'text', timeout: 120000, cache: false,
          success: html => { try { receive(html); } catch (_) { fail(); } }, error: fail, complete: cleanup});
        controller.signal.addEventListener('abort', () => request.abort(), {once: true});
      } catch (_) { fail(); }
    } else {
      fetch(url, {method, body: method === 'GET' ? undefined : body, credentials: 'same-origin', signal: controller.signal,
        headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(response => {
        if (!response.ok || response.redirected) throw new Error('Save response was not confirmed');
        return response.text();
      }).then(receive).catch(fail).finally(cleanup);
    }
    if (!cancel) fc_showSubmitProgress();
  };
  form.addEventListener('submit', submit, {once: true});
  const button = document.createElement('input'); button.type = 'submit'; button.hidden = true;
  form.append(button); button.click(); button.remove(); form.removeEventListener('submit', submit);
}

function fc_showSubmitProgress() {
  let blocker = document.getElementById('fc_filter_form_blocker');
  if (!blocker) {
    blocker = document.createElement('div'); blocker.id = 'fc_filter_form_blocker';
    blocker.innerHTML = '<div class="fc_blocker_overlay"></div><div class="fc_blocker_content"><div class="fc_blocker_logo"></div><div class="fc_blocker_mssg"></div><div class="fc_blocker_bar"><div></div></div></div>';
    document.body.prepend(blocker);
  }
  FlexiForm.show(blocker);
  blocker.querySelector('.fc_blocker_mssg').textContent = FlexiForm.text('FLEXI_FORM_IS_BEING_SUBMITTED');
  fc_admin_progress(95, blocker.querySelector('.fc_blocker_bar'));
}

function fc_admin_progress(percent, element) {
  const bar = FlexiForm.element(element)?.querySelector('div');
  if (!bar) return;
  bar.style.transition = 'width 5s'; bar.style.width = Math.max(0, Math.min(100, percent)) + '%';
  bar.textContent = '';
}

Joomla.submitform = function(task, form, validate) {
  form = form || document.getElementById('adminForm');
  if (!form || form.fc_ajax_saving) return false;
  const field = form.elements.namedItem('task');
  let requested = task || (field && field.value) || '';
  const changingType = FlexiForm.visible(document.getElementById('fc-change-warning'));
  if (changingType) requested = requested.replace(/(^|\.)apply_ajax$/, '$1apply');
  if (!changingType && window.fc_validateOnSubmitForm && document.formvalidator && !/(^|\.)cancel$/.test(requested)) {
    if (!document.formvalidator.isValid(form)) {
      setTimeout(() => FlexiForm.focusInvalid(form), 20); return false;
    }
  }
  if (field) field.value = requested;
  if (form.H5Form) form.H5Form.donotValidate = true;
  form.noValidate = true; // validation above includes hidden editor controls
  fc_submit_form(form, requested);
  if (field) field.value = '';
};

Joomla.serializeForm = function(form, result) {
  const active = [], entries = [];
  for (const field of form.elements) {
    if (!field.name || field.matches(':disabled') || /^(file|reset|submit|button|image)$/.test(field.type) || field.tagName === 'FIELDSET') continue;
    active.push(field);
    if (field.type === 'select-multiple') {
      for (const option of field.selectedOptions) if (!option.disabled && !option.closest('optgroup[disabled]')) entries.push({name: field.name, value: option.value});
    } else if (!/^(checkbox|radio)$/.test(field.type) || field.checked) {
      entries.push({name: field.name, value: field.value});
    }
  }
  if (result) result.fields_active = active;
  return entries;
};
