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
  function focusInvalid(form) {
    const el = Array.from(form.querySelectorAll('.fc-field-invalid, .invalid')).find(visible)
      || document.getElementById('system-message-container');
    if (!el) return;
    window.scrollTo({top: Math.max(0, el.getBoundingClientRect().top + window.scrollY - 120), behavior: 'smooth'});
    el.focus({preventScroll: true});
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
  return {element, ready, visible, value, data, show, focusInvalid, change, cloneFieldRow, text, insertHTML};
})();
