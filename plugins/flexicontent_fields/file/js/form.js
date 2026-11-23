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
    if (!options?.hide_image) fcfield_file.setFileInfo(FlexiMediaField.row(element), '', '', {});
  },

  clearMediaFile(button, placeholder) {
    FlexiMediaField.clearMediaFile(button, placeholder);
    fcfield_file.setFileInfo(FlexiMediaField.row(button), '', '', {});
  },

  assignMediaFile(id, filename, preview) {
    const done = FlexiMediaField.assignFile(id, {id: 0, filename, preview}, 1, null, null, null, true);
    if (done) {
      fcfield_file.setFileInfo(FlexiMediaField.row(document.getElementById(id)), filename, '', {
        url: /^(https?:)?\/\//i.test(filename || '') ? filename : '',
        description: '', custom1: '', custom2: '', ext: String(filename || '').split('.').pop(), size: ''
      });
    }
    return done;
  },

  selectMediaFile(id, filename, root) {
    if (filename) fcfield_file.assignMediaFile(id, filename, root + filename);
    if (typeof SqueezeBox !== 'undefined') SqueezeBox.close();
    fc_closeDialog('fc_modal_popup_container');
  },

  getFileBasename(path) {
    return String(path).split('/').pop().replace(/\.[^.]*$/, '');
  },

  /**
   * Boxes "Title" and "Original file name" of a value; the other data of the file (url, description, custom1/2, ext, size)
   * go into the tooltip of these boxes. data: object merged into the current data if merge is true, otherwise replacing them
   */
  setFileInfo(box, original, title, data, merge) {
    if (!box) return;
    const setBox = (selector, value) => box.querySelectorAll(selector).forEach(input => { input.value = value || ''; });
    setBox('.fc_filedata_title', original);
    setBox('.fc_filedata_alttitle', title);

    box.querySelectorAll('.fc-file-info').forEach(info => {
      let current = {};
      if (merge) { try { current = JSON.parse(info.getAttribute('data-fc-info') || '{}') || {}; } catch (e) { current = {}; } }
      current = Object.assign(current, data || {}, {title: title || ''});
      info.setAttribute('data-fc-info', JSON.stringify(current));
      fcfield_file.updateTip(info);
    });
  },

  mimes: {
    pdf: 'application/pdf', zip: 'application/zip', rar: 'application/vnd.rar', '7z': 'application/x-7z-compressed',
    gz: 'application/gzip', tar: 'application/x-tar', dmg: 'application/x-apple-diskimage',
    exe: 'application/vnd.microsoft.portable-executable', msi: 'application/x-msi', apk: 'application/vnd.android.package-archive',
    doc: 'application/msword', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    xls: 'application/vnd.ms-excel', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ppt: 'application/vnd.ms-powerpoint', pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    odt: 'application/vnd.oasis.opendocument.text', ods: 'application/vnd.oasis.opendocument.spreadsheet',
    txt: 'text/plain', csv: 'text/csv', html: 'text/html', htm: 'text/html', css: 'text/css', js: 'text/javascript',
    json: 'application/json', xml: 'application/xml', rtf: 'application/rtf',
    jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp', svg: 'image/svg+xml',
    bmp: 'image/bmp', ico: 'image/x-icon', tif: 'image/tiff', tiff: 'image/tiff', avif: 'image/avif',
    mp3: 'audio/mpeg', wav: 'audio/wav', ogg: 'audio/ogg', m4a: 'audio/mp4', flac: 'audio/flac',
    mp4: 'video/mp4', webm: 'video/webm', mov: 'video/quicktime', avi: 'video/x-msvideo', mkv: 'video/x-matroska',
    epub: 'application/epub+zip', iso: 'application/x-iso9660-image'
  },

  formatSize(size) {
    if (size === '' || size === null || size === undefined) return '';
    if (isNaN(size)) return String(size);   // already formatted text, e.g. "12 KB"
    size = parseFloat(size);
    if (!size) return '';
    if (size < 1024) return size + ' B';
    if (size < 1048576) return (size / 1024).toFixed(size < 10240 ? 1 : 0).replace(/\.0$/, '') + ' KB';
    return (size / 1048576).toFixed(1).replace(/\.0$/, '') + ' MB';
  },

  // HTML of the tooltip (Title, URL link, Description, custom1/custom2, MIME type / Size), empty string if there is nothing to show
  buildTipHtml(info) {
    let data = {}, labels = {};
    try { data = JSON.parse(info.getAttribute('data-fc-info') || '{}') || {}; } catch (e) {}
    try { labels = JSON.parse(info.getAttribute('data-fc-custom-labels') || '{}') || {}; } catch (e) {}

    const plain = (html, max) => {
      const div = document.createElement('div');
      div.innerHTML = String(html || '');
      const text = (div.textContent || '').replace(/\s+/g, ' ').trim();
      return text.length > max ? text.slice(0, max) + '…' : text;
    };

    const root = document.createElement('div');
    root.className = 'fc-file-tip-box';
    const add = (className, text, label) => {
      if (!text) return;
      const line = document.createElement('div');
      line.className = className;
      if (label) { const b = document.createElement('b'); b.textContent = label + ': '; line.append(b); }
      line.append(document.createTextNode(text));
      root.append(line);
    };

    add('fc-file-tip-title', plain(data.title, 200));
    add('fc-file-tip-url', data.url ? String(data.url) : '');
    add('fc-file-tip-desc', plain(data.description, 300));
    add('fc-file-tip-custom', plain(data.custom1, 300), labels[1] || '');
    add('fc-file-tip-custom', plain(data.custom2, 300), labels[2] || '');
    add('fc-file-tip-meta', [fcfield_file.mimes[String(data.ext || '').toLowerCase()] || '', fcfield_file.formatSize(data.size)].filter(Boolean).join(' · '));

    return root.children.length ? root.outerHTML : '';
  },

  // Standard Bootstrap tooltip on the Title / Original file name boxes
  updateTip(info) {
    const html = fcfield_file.buildTipHtml(info);
    const Native = window.bootstrap?.Tooltip;
    const options = {html: true, container: 'body', trigger: 'hover'};

    info.querySelectorAll('input.fc-file-info-val').forEach(input => {
      if (Native) {
        Native.getInstance(input)?.dispose();
        input.removeAttribute('title');
        input.removeAttribute('data-bs-original-title');
        if (html) new Native(input, {...options, title: html});
      } else if (window.jQuery?.fn.tooltip) {
        const el = window.jQuery(input);
        el.tooltip('dispose');
        input.removeAttribute('title');
        input.removeAttribute('data-bs-original-title');
        if (html) { input.setAttribute('title', html); el.tooltip(options); }
      }
    });
  },

  assignFile(id, file, keepModal, key) {
    const thumb = FlexiMediaField.row(document.getElementById(id))?.querySelector('img.fc_preview_thumb');
    const isPdf = String(file?.ext).toLowerCase() === 'pdf' || /\.pdf$/i.test(file?.filename || '');
    if (thumb && isPdf && !file.preview && Number(file.url) !== 1 && /^[1-9]\d*$/.test(file.id) && fcfield_file.pdf_thumb_url) {
      file = {...file, preview: fcfield_file.pdf_thumb_url.replace('__ID__', file.id)};
    }
    const done = FlexiMediaField.assignFile(id, file, keepModal, key, fcfield_file.dialog_handle);
    if (done) {
      const decode = value => { try { return decodeURIComponent(value); } catch (e) { return value; } };
      const name = decode(file.filename || '');
      const alt  = decode(file.altname || '');
      fcfield_file.setFileInfo(FlexiMediaField.row(document.getElementById(id)), decode(file.filename_original || '') || name, alt && alt !== name ? alt : '', {
        url: Number(file.url) === 1 ? name : '',
        description: file.description || '', custom1: file.custom1 || '', custom2: file.custom2 || '',
        ext: file.ext || '', size: file.size !== undefined ? file.size : ''
      });
    }
    return done;
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
      const update = {description: file.description || ''};
      if (file.custom1 !== undefined) update.custom1 = file.custom1 || '';
      if (file.custom2 !== undefined) update.custom2 = file.custom2 || '';
      fcfield_file.setFileInfo(box, file.filename_original || filename, alt, update, true);
      const ext = String(file.ext || '').toUpperCase();
      const preview = box.querySelector('.fc_preview_thumb')?.getAttribute('src');
      if (ext && (!preview || preview === FlexiMediaField.placeholder)) {
        FlexiMediaField.setText(box, '.fc_preview_text', ext);
      }
    });
  }
};

// Tooltips of the Title / Original file name boxes of the values that exist when the page is loaded
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.fc-file-info').forEach(info => fcfield_file.updateTip(info));
});
