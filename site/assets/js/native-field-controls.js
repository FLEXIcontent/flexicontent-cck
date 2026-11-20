/** Native Color controls and adapters for Joomla's field and editor widgets. */
window.fcfield_color = (() => {
  function updatePicker(field) {
    const picker = field.closest('[data-fc-color]')?.querySelector('.fc-color-picker');
    if (!picker) return;
    const value = field.value.trim();
    const hex = /^#[\da-f]{3}$/i.test(value) ? '#' + [...value.slice(1)].map(char => char + char).join('') : value;
    picker.value = /^#[\da-f]{6}$/i.test(hex) ? hex : '#000000';
  }

  document.addEventListener('input', event => {
    const input = event.target;
    if (input.matches?.('.fc-color-picker')) {
      const value = input.closest('[data-fc-color]').querySelector('.fc-color-value');
      value.value = input.value;
      FlexiForm.change(value);
    } else if (input.matches?.('.fc-color-value')) {
      updatePicker(input);
    }
  });

  function prepare(row, ref, config) {
    const field = row.querySelector('.fc-color-value');
    if (!field) return false;
    let value = config.defaultValue;
    if (field.matches('select') && value && ![...field.options].some(option => option.value === value)) field.add(new Option(value, value));
    FlexiFieldRepeater.setControl(row, field, ref.id, ref.name, value);
    updatePicker(field);
  }

  return { prepare };
})();

window.fcfield_jprofile = (() => {
  function prepare(row, ref) {
    const display = row.querySelector('.field-user-input-name');
    if (!display) return false;
    FlexiFieldRepeater.setControl(row, display, ref.id, '', '');
    display.removeAttribute('name');
    const user = row.querySelector('.field-user-input');
    if (user) FlexiFieldRepeater.setControl(row, user, ref.id + '_id', ref.name, '');
    const wrapper = display.closest('joomla-field-user');
    if (wrapper) {
      const url = new URL(wrapper.getAttribute('url'), document.baseURI);
      url.searchParams.set('field', ref.id);
      wrapper.setAttribute('url', url.href);
    } else {
      const modal = row.querySelector('.field-user-wrapper .modal');
      if (modal) {
        modal.id = 'userModal_' + ref.id;
        const button = row.querySelector('.field-user-wrapper .button-select');
        if (button) button.setAttribute('data-target', '#' + modal.id);
      }
    }
  }

  function attach(row) {
    const wrapper = row.querySelector('joomla-field-user');
    if (wrapper) {
      wrapper.addEventListener('change', event => {
        if (event.target !== wrapper || !event.detail) return;
        wrapper.querySelector('.field-user-input-name').value = event.detail.name || event.detail.value || '';
      });
      return;
    }
    const legacy = row.querySelector('div.field-user-wrapper');
    if (legacy && window.jQuery?.fn.fieldUser) window.jQuery(legacy).fieldUser();
  }

  return { prepare, attach };
})();

window.fcfield_textarea = (() => {
  const legacyEditors = new WeakMap();
  const areaSelector = '.fc_txtarea textarea, .fc_termtext textarea';

  function prepare(row, ref, config) {
    const area = row.querySelector(areaSelector);
    if (!area) return false;
    const oldId = area.dataset.originalId || area.id;
    const id = ref.id + (config.term ? '_text' : '');
    if (config.term) {
      const title = row.querySelector('.termtitle');
      FlexiFieldRepeater.setControl(row, title, ref.id + '_title', ref.name + '[title]', title.dataset.defvals || '');
      row.querySelector('.labeltitle').id = title.id + '-lbl';
      row.querySelector('.labeltext').id = id + '-lbl';
    }
    if (config.editor) {
      const source = document.getElementById(oldId);
      const tiny = window.tinymce?.get(oldId);
      const code = source?.nextElementSibling?.CodeMirror;
      if (tiny || code) legacyEditors.set(area, { tiny: tiny?.settings, code: code?.options });
      const box = area.closest('.fc_txtarea, .fc_termtext');
      box.querySelectorAll('.tox-tinymce, .mce-container, .CodeMirror, .cm-editor').forEach(element => element.remove());
      const placeholder = area.closest('[data-fc-editor]');
      if (placeholder) {
        const editor = document.createElement(placeholder.dataset.fcEditor);
        for (const attr of placeholder.attributes) {
          if (attr.name !== 'data-fc-editor') editor.setAttribute(attr.name, attr.value);
        }
        editor.append(...placeholder.childNodes);
        placeholder.replaceWith(editor);
      }
      area.classList.remove('mce_editable');
      area.style.removeProperty('display');
      area.style.removeProperty('visibility');
      area.removeAttribute('aria-hidden');
      for (const button of box.querySelectorAll('#editor-xtd-buttons [onclick], #editor-xtd-buttons [href]')) {
        for (const attr of ['onclick', 'href']) {
          if (button.hasAttribute(attr)) button.setAttribute(attr, button.getAttribute(attr).replaceAll(oldId, id));
        }
      }
    }
    const name = ref.name + (config.term ? '[text]' : '') + (config.editor ? '[' + config.editor + ']' : '');
    FlexiFieldRepeater.setControl(row, area, id, name, '');
  }

  function attach(row, ref, config) {
    if (!config.editor) return;
    const area = row.querySelector(areaSelector);
    const legacy = legacyEditors.get(area);
    if (area.closest('joomla-editor-codemirror')) return;
    if (area.closest('.js-editor-tinymce') && window.Joomla?.JoomlaTinyMCE?.setupEditors) {
      Joomla.JoomlaTinyMCE.setupEditors(row);
    } else if (legacy?.code) {
      window.CodeMirror?.fromTextArea(area, legacy.code).refresh();
    } else if (legacy?.tiny) {
      window.tinymce?.init({ ...legacy.tiny, id: null, selector: null, target: area });
    }
  }

  function dispose(row) {
    const area = row.querySelector(areaSelector);
    if (!area) return;
    const editor = window.tinymce?.get(area.id);
    if (editor?.targetElm === area) {
      editor.remove();
      import('editor-api').then(({ JoomlaEditor }) => JoomlaEditor.unregister(area.id)).catch(() => {});
    }
    area.nextElementSibling?.CodeMirror?.toTextArea();
  }

  return { prepare, attach, dispose };
})();

// These fields render their related controls with the same row ID prefix.
function fc_reindexFieldControls(row, oldId, ref, defaults) {
  if (!oldId) return;
  for (const element of row.querySelectorAll('*')) {
    const id = element.dataset.originalId || element.id;
    const key = id.startsWith(oldId + '_') ? id.slice(oldId.length + 1) : '';
    if (key && element.matches('input, select, textarea')) {
      const property = element.classList.contains('autoprefix') ? 'autoprefix' : key;
      const value = element.matches('input[type=radio], input[type=checkbox]') ? false : defaults[property] ?? '';
      FlexiFieldRepeater.setControl(row, element, ref.id + '_' + key, ref.name + '[' + property + ']', value);
      if (property === 'autoprefix') element.nextElementSibling?.classList.remove('active');
    } else if (element.id.includes(oldId)) {
      element.id = element.id.replace(oldId, ref.id);
    }
    for (const attr of ['onclick', 'onchange', 'for', 'aria-controls', 'data-for', 'data-name']) {
      const value = element.getAttribute(attr);
      if (value?.includes(oldId)) element.setAttribute(attr, value.replaceAll(oldId, ref.id));
    }
  }
}

window.fcfield_weblink_rows = (() => {
  function prepare(row, ref, config) {
    const link = row.querySelector('.urllink');
    if (!link) return false;
    const oldId = (link.dataset.originalId || link.id).slice(0, -5);
    fc_reindexFieldControls(row, oldId, ref, config.defaults);
    const message = row.querySelector('.fcfield_message_box');
    if (message) message.replaceChildren();
    const hits = row.querySelector('.hitcount');
    if (hits) hits.textContent = '0 ' + config.hitsLabel;
    const preview = row.querySelector('img.media-preview');
    if (preview) preview.removeAttribute('src');
  }

  function attach(row) {
    const legacy = row.querySelector('div.field-media-wrapper');
    if (legacy && window.jQuery?.fn.fieldMedia) window.jQuery(legacy).fieldMedia();
  }

  return { prepare, attach };
})();

window.fcfield_sharedmedia_rows = (() => {
  function prepare(row, ref) {
    const url = row.querySelector('.sm_url');
    if (!url) return false;
    const oldId = (url.dataset.originalId || url.id).slice(0, -4);
    fc_reindexFieldControls(row, oldId, ref, {});
    for (const node of row.querySelectorAll('.fcfield_message_box, .sm_preview')) node.replaceChildren();
    for (const panel of row.querySelectorAll('.sm_url_props, .sm_value_props')) panel.style.display = 'none';
    for (const button of row.querySelectorAll('.fc-sm-url-btn, .fc-sm-edit-btn')) {
      button.classList.remove('active'); button.setAttribute('aria-expanded', 'false');
    }
    for (const name of ['title', 'author', 'duration', 'description', 'api_type', 'embed_url', 'media_id', 'preview', 'width', 'height']) {
      FlexiForm.show(row.querySelector('#' + CSS.escape(ref.id + '_' + name))?.closest('tr'), false);
    }
  }

  return { prepare };
})();
