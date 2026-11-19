/** FLEXIcontent form validation, including hidden editors and repeatable controls. */
var fcform_1st_invalid_found = false;
var max_cat_assign_fc = 0;
var existing_cats_fc = [];
var fcpass_element = {jform_catid: 1};
var JFormValidator_FC_instance = null;

var JFormValidator_FC = function () {
  'use strict';
  if (JFormValidator_FC_instance) return JFormValidator_FC_instance;
  const F = FlexiForm, handlers = {}, custom = {}, attached = new WeakSet(), editors = new WeakSet();
  const rtl = document.documentElement.dir === 'rtl';
  const controls = form => [...new Set([...form.querySelectorAll('fieldset'), ...form.elements])];
  const required = el => el.classList.contains('required') || el.required || el.getAttribute('aria-required') === 'true';
  const empty = value => value == null || value.length === 0;
  const inputSet = el => el.closest('.fc_input_set, .btn-group, fieldset');
  const isGroupControl = el => /^(radio|checkbox)$/.test(el.type);
  const group = el => {
    const fields = el.name && el.form ? Array.from(el.form.elements) : [el];
    return fields.filter(other => other.name === el.name && other.type === el.type
      && !other.matches(':disabled, .novalidate'));
  };
  function setHandler(name, fn, enabled = true) { handlers[name] = {exec: fn, enabled: enabled !== false}; }
  function label(el) {
    const cached = F.element(F.data(el, 'label'));
    if (cached?.isConnected) return cached;
    const id = el.getAttribute('data-element-grpid') || el.id;
    if (!id) return null;
    const labels = Array.from(document.querySelectorAll('label'));
    const find = key => labels.find(lbl => lbl.htmlFor === key || lbl.getAttribute('data-for') === key || lbl.id === key + '-lbl');
    const found = find(id) || find(id.replace(/_[0-9]+$/, '')) || null;
    F.data(el, 'label', found && window.jQuery ? window.jQuery(found) : found || false);
    return found;
  }
  function message(anchor, text, before = rtl) {
    if (!anchor) return;
    let note = before ? anchor.previousElementSibling : anchor.nextElementSibling;
    if (!note?.classList.contains('fc-field-invalid')) note = null;
    if (!text) { note?.remove(); return; }
    if (!note) {
      note = document.createElement('span');
      note.className = 'fc-field-invalid alert alert-warning fc_notes';
      anchor[before ? 'before' : 'after'](note);
    }
    note.textContent = text;
    return note;
  }
  function limits(el, count, anchor) {
    const min = Number(el.getAttribute('data-min_values')) || 0;
    const max = Number(el.getAttribute('data-max_values')) || 0;
    const exact = Number(el.getAttribute('data-exact_values')) || 0;
    const error = min && count < min ? F.text('FLEXI_FIELD_SELECT_AT_LEAST_PROMPT') + ': ' + min
      : max && count > max ? F.text('FLEXI_FIELD_SELECT_UP_TO_PROMPT') + ': ' + max
      : exact && count !== exact ? F.text('FLEXI_FIELD_SELECT_EXACTLY_PROMPT') + ': ' + exact : '';
    message(anchor, error);
    return !error;
  }
  setHandler('username', value => !/[<>"'%;()&]/i.test(value));
  setHandler('password', value => /^\S[\S ]{2,98}\S$/.test(value));
  setHandler('numeric', value => /^(\d|-)?(\d|,)*\.?\d*$/.test(value));
  setHandler('fieldname', value => /^[a-zA-Z0-9_-]+$/.test(value));
  setHandler('email', value => {
    if (window.punycode) value = window.punycode.toASCII(value);
    return /^[a-zA-Z0-9.!#$%&’*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/.test(value);
  });
  // These two handlers retain the public API: a radio/checkbox's parent is passed in.
  for (const name of ['radio', 'checkbox']) setHandler(name, parent => !parent?.parentNode
    || Array.from(parent.parentNode.querySelectorAll('input')).some(el => el.checked && !el.matches(':disabled')));
  setHandler('fcuploader', el => !!el.value && el.value !== 'done');
  setHandler('catid', el => {
    if (el.value) return true;
    const values = F.value(document.getElementsByName('jform[cid][]')[0]);
    if (Array.isArray(values) && values.length === 1) { el.value = values[0]; F.change(el); }
    return !!el.value;
  });
  setHandler('fccats', () => {
    const values = F.value(document.getElementById('jform_cid')) || [];
    const primary = document.getElementById('jform_catid')?.value;
    const selected = [...new Set([...values, ...(primary ? [primary] : [])])];
    if (max_cat_assign_fc && selected.length > max_cat_assign_fc
      && !selected.every(value => existing_cats_fc.map(String).includes(value))) {
      alert(F.text('FLEXI_TOO_MANY_ITEM_CATEGORIES') + max_cat_assign_fc); return false;
    }
    return true;
  });
  setHandler('sellimitations', el => limits(el, el.selectedOptions.length, el));
  setHandler('cboxlimitations', el => {
    const sets = F.data(el.form, 'fcform_input_sets');
    if (sets && Object.prototype.hasOwnProperty.call(sets, el.name)) return null;
    const count = group(el).filter(other => other.checked).length;
    if (sets) sets[el.name] = count;
    return limits(el, count, inputSet(el) || el.parentElement);
  });
  function syncEditor(el) {
    if (el.tagName !== 'TEXTAREA') return;
    const tiny = window.tinyMCE?.get(el.id);
    const mirror = el.nextElementSibling?.CodeMirror;
    const editor = tiny || mirror;
    if (editor && (!tiny || tiny.initialized !== false) && !F.visible(el)) {
      el.value = (tiny ? tiny.getContent() : mirror.getValue()).trim();
      if (!el.value) tiny ? tiny.setContent('') : mirror.setValue('');
    }
    F.data(el, 'use_fcfield_box', !!editor || el.classList.contains('use_fcfield_box'));
  }
  function attachTinyMceValidation(value) {
    const el = F.element(value);
    if (!el || !el.classList.contains('mce_editable')) return;
    setTimeout(() => {
      const editor = window.tinyMCE?.get(el.id);
      if (!editor || editors.has(editor)) return;
      editors.add(editor);
      const changed = () => { syncEditor(el); el.dispatchEvent(new Event('blur')); };
      if (editor.on) editor.on('change', changed);
      else editor.onChange?.add(changed);
    }, 10000);
  }
  function reveal(el) {
    document.querySelector('[href="#attrib-fcfields"]')?.click();
    let tab = el.parentElement?.closest('div.tabbertab');
    while (tab) {
      const set = tab.parentElement?.closest('div.tabberlive');
      if (!set) break;
      window.fctabber?.[set.id]?.tabShow(Array.from(tab.parentElement.children).indexOf(tab) - 1);
      tab = set.parentElement?.closest('div.tabbertab');
    }
    const row = el.closest('li.fcfieldval_container');
    const box = row?.parentElement?.parentElement;
    if (box && !F.visible(box)) {
      const button = box.previousElementSibling?.querySelector('.fc-show-vals-btn');
      if (button) { F.data(button, 'fc_noeffect', 1); button.click(); }
    }
    row?.querySelectorAll('.fc-toggle-group-down').forEach(button => {
      if (F.visible(button)) { F.data(button, 'fc_noeffect', 1); button.click(); }
    });
  }
  function response(valid, el, preserveMessage = false, failure = '') {
    if (!valid && !fcform_1st_invalid_found) reveal(el);
    const lbl = label(el), isGroup = isGroupControl(el);
    const box = F.data(el, 'use_fcfield_box') ? el.closest('.fcfield_box.required_box') : null;
    const set = isGroup ? inputSet(el) : null;
    const members = isGroup ? group(el) : [el];
    for (const member of members) {
      member.classList.toggle('invalid', !valid);
      member.setAttribute('aria-invalid', String(!valid));
      label(member)?.classList.toggle('invalid', !valid);
    }
    box?.classList.toggle('invalid', !valid);
    set?.classList.toggle('invalid', !valid);
    if (F.data(el.form, 'use_all_mssg_box') && lbl && !failure && !el.inputmask
      && !el.matches('.validate-email, [data-fc-format=email]')) return;
    const hidden = el.classList.contains('fc_hidden_value');
    const expanded = el.parentElement?.classList.contains('fc-xpended-row');
    const anchor = box || set || (hidden ? el.parentElement?.firstElementChild : expanded ? el.parentElement : el);
    if (preserveMessage && !valid) return;
    const text = valid ? '' : failure || F.data(el, 'error-mssg') || F.text(empty(F.value(el)) ? 'FLEXI_REQUIRED' : 'FLEXI_INVALID');
    const note = message(anchor, text, !!box || hidden || (isGroup && rtl));
    if (note && F.data(el, 'error-css')) note.style.cssText = F.data(el, 'error-css');
  }
  function validate(el) {
    el = F.element(el);
    if (!el || F.data(el.form, 'skip_validation') || el.id?.startsWith('jform_rules')) return true;
    if (el.matches(':disabled')) {
      if (isGroupControl(el)) {
        el.classList.remove('invalid');
        el.removeAttribute('aria-invalid');
      } else response(true, el);
      return true;
    }
    syncEditor(el);
    const isGroup = isGroupControl(el);
    const mask = el.inputmask;
    const entered = F.value(el);
    const complete = mask?.isComplete();
    const value = mask && !empty(entered)
      ? (complete === false && entered === mask.getemptymask() ? '' : mask.unmaskedvalue())
      : entered;
    let valid = true;
    let failure = '';
    if (required(el)) {
      if (isGroup) valid = group(el).some(other => other.checked);
      else if (el.tagName === 'FIELDSET') {
        valid = Array.from(el.querySelectorAll('input')).some(other => other.checked && !other.matches(':disabled'));
      } else valid = !empty(value);
    }
    const name = String(el.className || '').match(/validate-([a-zA-Z0-9_-]+)/)?.[1];
    const handler = handlers[name];
    const limitation = name === 'sellimitations' || name === 'cboxlimitations';
    if (valid && !empty(value)) {
      const email = name === 'email' || mask?.opts?.alias === 'email' || el.dataset.fcFormat === 'email';
      if (email && handlers.email.enabled && handlers.email.exec(value) === false) {
        valid = false;
        failure = F.text('FLEXI_VALIDATION_EMAIL_FORMAT');
      } else if (complete === false) {
        valid = false;
        failure = F.text(email ? 'FLEXI_VALIDATION_EMAIL_FORMAT' : 'FLEXI_INPUTMASK_INCOMPLETE');
      }
    }
    if (valid && handler?.enabled && name !== 'email') {
      let result = true;
      if (limitation || fcpass_element[el.id] || name === 'fccats') result = handler.exec(el);
      else if (name === 'fcuploader') { if (!empty(F.value(el))) result = handler.exec(el); }
      else if (isGroup && required(el)) result = handler.exec(el.parentNode);
      else if (!isGroup && !empty(value)) result = handler.exec(value);
      if (result === null) return true;
      valid = result === true;
    }
    response(valid, el, limitation, failure);
    return valid;
  }
  function attachToForm(form, container) {
    form = F.element(form); container = F.element(container);
    form = form || container?.closest('form') || container?.querySelector('input, select, textarea')?.form;
    if (!form) return;
    const fields = container ? container.querySelectorAll('input, textarea, select, fieldset, button') : controls(form);
    for (const el of fields) {
      const box = el.tagName === 'TEXTAREA' && el.closest('.fcfield_box.required_box');
      if (box) { el.classList.add('required', 'required_boxed'); F.data(el, 'label_text', F.data(box, 'label_text')); }
      if (required(el)) { el.setAttribute('aria-required', 'true'); el.setAttribute('required', 'required'); }
      if (attached.has(el)) continue;
      attached.add(el);
      if (['submit', 'image'].includes(el.type)) {
        if (el.classList.contains('validate')) el.addEventListener('click', event => { if (!isValid(el.form)) event.preventDefault(); });
      } else if (el.tagName !== 'BUTTON' && el.tagName !== 'FIELDSET' && el.type !== 'button') {
        el.addEventListener('change', () => validate(el));
        if (!/^(radio|checkbox)$/.test(el.type)) el.addEventListener('blur', () => validate(el));
        if (el.classList.contains('validate-email')) el.type = 'email';
        attachTinyMceValidation(el);
      }
    }
  }
  function isValid(form) {
    form = F.element(form);
    if (F.data(form, 'skip_validation')) { F.data(form, 'fcform_isValid', true); return true; }
    form.querySelectorAll('.fc-field-invalid').forEach(el => el.remove());
    fcform_1st_invalid_found = false;
    F.data(form, 'fcform_input_sets', Object.create(null));
    const invalid = [];
    for (const el of controls(form)) {
      if (el.classList.contains('novalidate') || el.tagName === 'BUTTON') continue;
      if (!validate(el)) { invalid.push(el); fcform_1st_invalid_found = true; }
    }
    let valid = !invalid.length;
    for (const validator of Object.values(custom)) if (validator.exec() !== true) valid = false;
    const captcha = form.querySelector('#recaptcha_response_field');
    if (captcha) { response(!!captcha.value, captcha); if (!captcha.value) valid = false; }
    if (invalid.length) {
      const errors = new Set();
      for (const el of invalid) {
        const lbl = label(el), text = lbl?.textContent || F.data(el, 'label_text');
        if (lbl) F.show(lbl.closest('.control-group'));
        if (text) errors.add(F.text('JLIB_FORM_FIELD_INVALID') + text.replace('*', ''));
      }
      Joomla.renderMessages({error: [...errors]});
    } else if (valid) document.getElementById('system-message-container')?.replaceChildren();
    F.data(form, 'fcform_isValid', valid);
    F.data(form, 'fcform_input_sets', null);
    if (!valid && !window.fc_validateOnSubmitForm) setTimeout(() => F.focusInvalid(form), 20);
    return valid;
  }
  return {attachTinyMceValidation, isValid, validate, setHandler, attachToForm, custom};
};

JFormValidator_FC_instance = new JFormValidator_FC();
document.formvalidator = null;
FlexiForm.ready(() => {
  document.formvalidator = JFormValidator_FC_instance;
  document.querySelectorAll('form.form-validate').forEach(form => document.formvalidator.attachToForm(form));
});

function flexi_submit(task, btn_box, msg_box) {
  Joomla.submitbutton(task);
  const form = document.getElementById('adminForm');
  if (!FlexiForm.data(form, 'fcform_isValid')) return;
  for (const [key, id, show] of [['btn_box', btn_box, false], ['msg_box', msg_box, true]]) {
    if (id === undefined) continue;
    const el = document.getElementById(id);
    FlexiForm.data(form, key, el); FlexiForm.show(el, show);
  }
}
