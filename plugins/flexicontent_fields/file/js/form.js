var fcfield_file = {
  debugToConsole: [],
  use_native_apis: [],
  dialog_handle: [],

  pdf_thumb_url: '',

  toggleFields(id) {
    const panel = Array.from(document.querySelectorAll('.fc-file-value-props[data-name]'))
      .find(element => element.dataset.name === id);
    if (!panel) return;
    const open = panel.matches('details') ? !panel.open : getComputedStyle(panel).display === 'none';
    if (panel.matches('details')) panel.open = open;
    else panel.style.display = open ? 'block' : 'none';
    for (const button of document.querySelectorAll('.fc-file-edit-btn[data-for], .fc-field-mediaurl-btn[data-for]')) {
      if (button.dataset.for !== id) continue;
      button.classList.toggle('active', open);
      button.setAttribute('aria-expanded', String(open));
    }
  },

  toggleMediaURL(id) {
    fcfield_file.toggleFields(id);
  },

  initValue() {},
  fileFiltered() {},

  showUploader(fieldName) {
    FlexiMediaField.showUploader('custom_' + fieldName + '_file-data-txt');
  },

  fileUploaded(uploader, file, result, key) {
    const upload = FlexiMediaField.uploaded(uploader, result, '.fc_filedata_txt');
    if (upload) fcfield_file.assignFile(upload.target.id, upload.file, 0, key);
  },

  clearFieldUploader(box) {
    FlexiMediaField.clearUploader(box);
  },

  clearField(element, options, key) {
    FlexiMediaField.clearFile(element, options, key);
  },

  clearMediaFile(button, placeholder) {
    FlexiMediaField.clearMediaFile(button, placeholder);
  },

  assignMediaFile(id, filename, preview) {
    return FlexiMediaField.assignFile(id, {id: 0, filename, preview}, 1, null, null, null, true);
  },

  selectMediaFile(id, filename, root) {
    if (filename) fcfield_file.assignMediaFile(id, filename, root + filename);
    if (typeof SqueezeBox !== 'undefined') SqueezeBox.close();
    fc_closeDialog('fc_modal_popup_container');
  },

  getFileBasename(path) {
    return String(path).split('/').pop().replace(/\.[^.]*$/, '');
  },

  assignFile(id, file, keepModal, key) {
    const thumb = FlexiMediaField.row(document.getElementById(id))?.querySelector('img.fc_preview_thumb');
    const isPdf = String(file?.ext).toLowerCase() === 'pdf' || /\.pdf$/i.test(file?.filename || '');
    if (thumb && isPdf && !file.preview && Number(file.url) !== 1 && /^[1-9]\d*$/.test(file.id) && fcfield_file.pdf_thumb_url) {
      file = {...file, preview: fcfield_file.pdf_thumb_url.replace('__ID__', file.id)};
    }
    return FlexiMediaField.assignFile(id, file, keepModal, key, fcfield_file.dialog_handle);
  },

  openEditDialog(button, url, checkinUrl, title, noFileMessage) {
    const id = parseInt(FlexiMediaField.row(button)?.querySelector('input.fc-file-id')?.value, 10);
    if (!id) { FlexiForm.alert(noFileMessage); return false; }
    const checkin = () => fetch(checkinUrl.replace('__file_id__', id), {
      credentials: 'same-origin', redirect: 'manual'
    }).catch(() => {});
    fcfield_file.dialog_handle._edit = fc_showDialog(url.replace('__file_id__', id), 'fc_modal_popup_container',
      0, 0, 0, checkin, {title, paddingW: 10, paddingH: 16});
    return false;
  },

  // The file manager calls this after editing a file used in this form.
  refreshFileRows(file) {
    const id = parseInt(file.id, 10);
    if (!id) return;
    document.querySelectorAll('input.fc-file-id').forEach(field => {
      if (parseInt(field.value, 10) !== id) return;
      const box = FlexiMediaField.row(field);
      if (!box) return;
      const filename = file.filename || '';
      const alt = file.altname || '';
      FlexiMediaField.setText(box, '.fc_filedata_storage_name', filename);
      FlexiMediaField.setValue(box, '.fc_filedata_txt', filename);
      const input = box.querySelector('.fc_filedata_txt');
      if (input) input.dataset.filename = filename;
      FlexiMediaField.setText(box, '.fc_filedata_txt_nowrap', filename, alt);
      FlexiMediaField.setValue(box, '.fc_filedata_title', file.filename_original || filename);
      FlexiMediaField.setValue(box, '.fc_filetitle', alt, 'blur');
      FlexiMediaField.setValue(box, '.fc_filedesc', file.description);
      if (file.custom1 !== undefined) FlexiMediaField.setValue(box, '.fc_filecustom1', file.custom1);
      if (file.custom2 !== undefined) FlexiMediaField.setValue(box, '.fc_filecustom2', file.custom2);
      const ext = String(file.ext || '').toUpperCase();
      const preview = box.querySelector('.fc_preview_thumb')?.getAttribute('src');
      if (ext && (!preview || preview === FlexiMediaField.placeholder)) {
        FlexiMediaField.setText(box, '.fc_preview_text', ext);
      }
    });
  }
};
