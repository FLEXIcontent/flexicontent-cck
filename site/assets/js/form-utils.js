/** Native form utilities shared by FLEXIcontent's editor and administrator. */
window.FlexiForm = (() => {
  'use strict';
  const state = new WeakMap();
  const element = value => typeof value === 'string' ? document.querySelector(value)
    : value && (value.nodeType ? value : value[0]);
  const ready = callback => document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', callback, {once: true}) : callback();
  const visible = el => !!el && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const value = el => !el ? '' : el.multiple
    ? Array.from(el.selectedOptions, option => option.value) : el.value;
  function data(el, key, value) {
    el = element(el);
    if (!el) return undefined;
    if (arguments.length === 3) {
      if (!state.has(el)) state.set(el, {});
      state.get(el)[key] = value;
      // Legacy field plugins can still read/write their existing data contract.
      if (window.jQuery) window.jQuery(el).data(key, value);
      return value;
    }
    if (window.jQuery) {
      const old = window.jQuery(el).data(key);
      if (old !== undefined) return old;
    }
    if (state.has(el) && key in state.get(el)) return state.get(el)[key];
    const attr = el.getAttribute('data-' + key.replace(/[A-Z]/g, letter => '-' + letter.toLowerCase()));
    if (attr === null) return undefined;
    if (attr === 'true') return true;
    if (attr === 'false') return false;
    if (attr === 'null') return null;
    if (attr === String(Number(attr))) return Number(attr);
    try { if (/^[\[{]/.test(attr)) return JSON.parse(attr); } catch (_) { /* a string */ }
    return attr;
  }
  function show(el, shown = true) {
    el = element(el);
    if (!el) return;
    if (!shown) {
      if (el.style.display !== 'none') data(el, 'fcPreviousDisplay', el.style.display);
      el.style.display = 'none';
    } else {
      el.hidden = false;
      el.style.display = data(el, 'fcPreviousDisplay') || '';
      if (getComputedStyle(el).display === 'none') el.style.display = 'block';
    }
  }
  function focusInvalid(form, field) {
    field = field || form.querySelector('input.invalid, select.invalid, textarea.invalid');
    if (field) {
      field.dispatchEvent(new CustomEvent('fc:reveal-invalid', {bubbles: true}));
      if (visible(field) && !field.disabled) { field.focus(); return; }
      const editor = window.tinyMCE?.get(field.id) || field.nextElementSibling?.CodeMirror;
      if (editor?.focus) { editor.focus(); return; }
      const widget = field.closest('.choices')?.querySelector('[tabindex="0"], input:not([type="hidden"])')
        || field.previousElementSibling?.querySelector('.select2-focusser');
      if (visible(widget)) { widget.focus(); return; }
    }
    const summary = document.getElementById('system-message-container');
    if (!summary) return;
    summary.setAttribute('tabindex', '-1');
    summary.focus();
  }
  const change = el => el && el.dispatchEvent(new Event('change', {bubbles: true}));
  function cloneFieldRow(row) {
    const copy = row.cloneNode(true);
    // Joomla detaches the Clear button while empty but requires it on connection.
    const media = row.querySelectorAll('joomla-field-media');
    copy.querySelectorAll('joomla-field-media').forEach((field, index) => {
      const clear = media[index].buttonClearEl;
      if (clear && !field.querySelector('.button-clear')) field.querySelector('.input-group')?.append(clear.cloneNode(true));
    });
    return copy;
  }
  const text = key => (Joomla.Text || Joomla.JText)._(key);
  const alert = (message, title) => {
    const Dialog = window.customElements?.get('joomla-dialog');
    return Dialog ? Dialog.alert(escapeHTML(message), title ? escapeHTML(title) : undefined) : Promise.resolve(window.alert(message));
  };
  const confirm = (message, title) => {
    const Dialog = window.customElements?.get('joomla-dialog');
    return Dialog ? Dialog.confirm(escapeHTML(message), title ? escapeHTML(title) : undefined) : Promise.resolve(window.confirm(message));
  };
  const escapeHTML = value => { const node = document.createElement('span'); node.textContent = value; return node.innerHTML; };
  const message = (type, value) => Joomla.renderMessages({[type]:[escapeHTML(value)]});
  const pendingActions = new WeakSet(), approvedForms = new WeakSet();
  // Inline event handlers must return false immediately; a Promise does not
  // cancel the browser's original navigation or form submission.
  function runAction(owner, action) {
    if (!owner || pendingActions.has(owner)) return false;
    pendingActions.add(owner);
    Promise.resolve().then(action).catch(error => message('error', error.message || String(error)))
      .finally(() => pendingActions.delete(owner));
    return false;
  }
  function confirmAction(owner, question, action) {
    return runAction(owner, async () => { if (await confirm(question)) await action(); });
  }
  function followLink(link, event) {
    const next = link.cloneNode(false);
    next.removeAttribute('id'); next.removeAttribute('onclick');
    if (event?.ctrlKey || event?.metaKey || event?.shiftKey || event?.button === 1) next.target = '_blank';
    next.hidden = true; document.body.append(next); next.click(); next.remove();
  }
  function confirmLink(link, question, event) {
    return confirmAction(link, question, () => followLink(link, event));
  }
  function alertLink(link, notice, event) {
    return runAction(link, async () => { await alert(notice); followLink(link, event); });
  }
  function confirmSubmit(form, question, event) {
    if (approvedForms.has(form)) { approvedForms.delete(form); return true; }
    const submitter = event?.submitter;
    return confirmAction(form, question, () => {
      approvedForms.add(form);
      try { HTMLFormElement.prototype.requestSubmit.call(form, submitter || undefined); }
      finally { approvedForms.delete(form); }
    });
  }
  function clearSelect(select) {
    const choices = select.fc_choices_instance || data(select, 'fc_choices_instance');
    if (choices) { choices.removeActiveItems(); if (!select.multiple) choices.setChoiceByValue(''); }
    Array.from(select.options).forEach(option => { option.selected = !select.multiple && option.value === ''; });
  }
  // Only for trusted HTML returned by a same-origin Joomla view. Recreate scripts
  // in document order, as the former jQuery.load() dialog transport did.
  async function insertHTML(target, html, signal) {
    const template = document.createElement('template'); template.innerHTML = html;
    const scripts = [...template.content.querySelectorAll('script')]; scripts.forEach(script => script.remove());
    if (signal?.aborted) return;
    target.replaceChildren(template.content);
    for (const original of scripts) {
      if (signal?.aborted || !target.isConnected) return;
      const script = document.createElement('script');
      for (const attr of original.attributes) script.setAttribute(attr.name, attr.value);
      script.textContent = original.textContent;
      const nonce = original.nonce || document.querySelector('script[nonce]')?.nonce; if (nonce) script.nonce = nonce;
      const executable = !script.type || /^(text|application)\/javascript$/.test(script.type) || script.type === 'module';
      if (executable && script.src) {
        script.async = false;
        await new Promise((resolve, reject) => {
          script.onload = resolve; script.onerror = () => reject(Error('Dialog script could not be loaded'));
          target.append(script);
        });
      } else {
        target.append(script);
        if (script.type === 'application/json' && script.classList.contains('joomla-script-options')) Joomla.loadOptions?.(JSON.parse(script.textContent));
      }
    }
  }
  return {element, ready, visible, value, data, show, focusInvalid, change, cloneFieldRow, text, insertHTML, alert, confirm, message, runAction, confirmAction, confirmLink, alertLink, confirmSubmit, clearSelect};
})();
