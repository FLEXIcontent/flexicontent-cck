/** Reset the form controls of an Image, File or Media File row. */
window.fcfield_media_rows = (() => {
  const blankImage = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
  const fieldId = (row, image) => row.querySelector(image ? 'input.hasvalue' : 'input.fc-file-id');

  function reindexControls(row, oldId, ref, config) {
    const namePrefix = config.name + '[';
    const oldField = oldId.replace(/^custom_/, '');
    const newField = ref.id.replace(/^custom_/, '');
    for (const control of row.querySelectorAll('*')) {
      const id = control.dataset.originalId || control.id;
      const name = control.dataset.originalName || control.getAttribute('name') || '';
      if (control.matches('input, select, textarea') && (name.startsWith(namePrefix) || control.matches('input.hasvalue'))) {
        const suffix = name.startsWith(namePrefix) ? name.slice(name.indexOf(']', namePrefix.length) + 1) : '';
        const property = /^\[([^\]]+)\]/.exec(suffix)?.[1];
        const inputName = suffix ? ref.name + suffix : ref.id + '_hasvalue';
        const inputId = id.replace(oldId, ref.id);
        let value = config.defaults[property] ?? '';
        if (control.matches('input[type=radio], input[type=checkbox]')) {
          value = config.defaults[property] === control.value;
          if (property === 'secure' || property === 'stamp') {
            control.disabled = false;
            control.nextElementSibling?.classList.toggle('active', value);
          }
        } else if (property === 'file-lang') {
          value = control.options[0]?.value ?? '';
        }
        FlexiFieldRepeater.setControl(row, control, inputId, inputName, value);
      } else if (id.includes(oldField)) {
        control.id = id.replace(oldField, newField);
      }
      if (control.hasAttribute('data-rowno')) control.dataset.rowno = ref.index;
      if (control.hasAttribute('aria-expanded') && control.matches('.fc_files_uploader_toggle_btn')) control.setAttribute('aria-expanded', 'false');
      for (const attr of ['onclick', 'onchange', 'for', 'aria-controls', 'data-for', 'data-name', 'data-href', 'href', 'data-modal-id']) {
        const value = control.getAttribute(attr);
        if (value?.includes(oldField)) control.setAttribute(attr, value.replaceAll(oldField, newField));
      }
    }
  }

  function prepare(row, ref, config) {
    const image = config.kind === 'image';
    const primary = fieldId(row, image);
    if (!primary) return false;
    const originalId = primary.dataset.originalId || primary.id;
    const oldId = image ? originalId : originalId.replace(/_file-id$/, '');
    reindexControls(row, oldId, ref, config);
    for (const item of row.querySelectorAll('.fc_file_uploader')) {
      item.id = item.dataset.tagidPrefix + ref.index;
      item.replaceChildren();
      item.style.display = 'none';
    }
    for (const item of row.querySelectorAll('.fcfield_message_box, .fc_preview_msg, .fc_preview_text, .fc_filedata_storage_name, .fc_mediafile_audio_spectrum')) {
      item.replaceChildren();
    }
    for (const preview of row.querySelectorAll('img.fc_preview_thumb')) {
      preview.src = blankImage;
      if (!image && !config.previewAlways) preview.parentElement.style.display = 'none';
    }
    const filename = row.querySelector('.fc_filedata_txt');
    if (filename) {
      for (const attr of ['data-filename', 'data-wfpreview', 'data-wfpeaks']) filename.removeAttribute(attr);
      filename.classList.remove('file_unpublished');
    }
    const current = row.querySelector('.fc_filedata_txt_nowrap');
    if (current) current.textContent = '-';
    const title = row.querySelector('.fc_filedata_title');
    if (title) title.value = '-';
    row.querySelectorAll('.inlinefile-secure-info, .inlinefile-stamp-info').forEach(element => element.remove());
    row.querySelectorAll('.inlinefile-secure-data, .inlinefile-stamp-data').forEach(element => FlexiForm.show(element));
  }

  function ready(row, ref, config, options) {
    const legacy = row.querySelector('div.field-media-wrapper');
    if (legacy && window.jQuery?.fn.fieldMedia) window.jQuery(legacy).fieldMedia();
    const quantum = row.querySelector('.quantummanager');
    if (quantum && typeof window.initQuantumuploadimage === 'function') window.initQuantumuploadimage(row);
    if (config.kind === 'mediafile') window.fcfield_mediafile?.initValue?.(ref.id.replace(/^custom_/, ''), config.fieldKey);
    if (config.kind === 'image' && options.animate_visible) {
      const uploader = row.querySelector('.fc_file_uploader');
      if (window[uploader?.dataset.tagidPrefix]) {
        row.querySelector('.fc_files_uploader_toggle_btn')?.click();
      }
    }
  }

  function dispose(row, config) {
    const image = config.kind === 'image';
    const value = fieldId(row, image)?.value;
    const hasValue = image ? value : Number(value) > 0 || row.querySelector('.fc_filedata')?.value;
    if (!hasValue) return;
    const counter = document.getElementById('custom_' + config.fieldKey);
    if (counter) counter.value = Number(counter.value) > 1 ? Number(counter.value) - 1 : '';
  }

  function result(ref, config) {
    return ref.id + (config.kind === 'image' ? '_existingname' : '_file-id');
  }

  return { prepare, ready, dispose, result };
})();
