// FLEXIcontent integration of the iamrobertja6 Uppy Dashboard.
// The component owns validation, field assignment and its existing chunked upload transport.
import Uppy, {UIPlugin} from '@uppy/core';
import {h} from 'preact';
import {useLayoutEffect, useRef} from 'preact/hooks';
import Dashboard from '@uppy/dashboard';
import ImageEditor from '@uppy/image-editor';
import french from '@uppy/locales/lib/fr_FR.js';
import chinese from '@uppy/locales/lib/zh_TW.js';
import '@uppy/core/css/style.min.css';
import '@uppy/dashboard/css/style.min.css';
import '@uppy/image-editor/css/style.min.css';

const text = key => Joomla.Text._(key);

function FilePreview({file}) {
  const host = useRef(null);
  useLayoutEffect(() => {
    const type = FlexiUploader.mediaType(file.name);
    const url = URL.createObjectURL(file.data.slice(0, file.data.size, type));
    const el = FlexiUploader.mediaElement(type, url, file.name);
    host.current.append(el);
    return () => {
      if (el instanceof HTMLMediaElement) el.pause();
      el.remove();
      URL.revokeObjectURL(url);
    };
  }, [file.id, file.data]);
  return h('div', {ref: host, class: 'fc-upload-dashboard-preview'});
}

class MediaPreview extends UIPlugin {
  id = 'FlexiMediaPreview';
  type = 'editor';
  canEditFile = file => file.data instanceof Blob && !!FlexiUploader.mediaType(file.name);
  selectFile = file => this.setPluginState({file: this.canEditFile(file) ? file : null});
  save = () => this.setPluginState({file: null});
  install() { this.mount(this.opts.target, this); }
  uninstall() { this.unmount(); }
  render() {
    const {file} = this.getPluginState();
    return file ? h(FilePreview, {file}) : null;
  }
}

class DashboardImageEditor extends ImageEditor {
  // Dashboard offers each selected file to every installed editor.
  selectFile = file => this.canEditFile(file) ? this.start(file) : this.stop();
}

function open(queue, opener = queue.input, droppedFiles = []) {
  if (queue.running || queue.disposed || document.querySelector('.fc-upload-dialog[open]')) return;
  const {extensions, upload_maxcount, upload_maxsize, resize_on_upload} = queue.options;
  const dialog = document.createElement('dialog');
  dialog.className = 'fc-upload-dialog';
  dialog.setAttribute('aria-label', text('FLEXI_UPLOAD_FILES'));
  const header = document.createElement('header');
  const title = document.createElement('strong');
  title.textContent = text('FLEXI_UPLOAD_FILES');
  const heading = document.createElement('div');
  const hint = document.createElement('small');
  hint.className = 'fc-upload-types';
  const limits = [];
  if (extensions.length) limits.push(extensions.map(ext => ext.toUpperCase()).join(' · '));
  if (upload_maxsize) {
    const megabytes = Intl.NumberFormat(document.documentElement.lang || undefined, {maximumFractionDigits: 2}).format(upload_maxsize / 1000000);
    limits.push(text('FLEXI_UPLOAD_MAX_EACH').replace('%s', megabytes));
  }
  hint.textContent = limits.join(' — ');
  heading.append(title, hint);
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'btn btn-outline-secondary';
  close.textContent = text('JCLOSE');
  const body = document.createElement('div');
  header.append(heading);
  const defaults = new Map();
  if (queue.options.edit_properties) {
    for (const [key, label] of [['file-access', 'FLEXI_ACCESS'], ['file-lang', 'FLEXI_LANGUAGE']]) {
      const original = document.getElementById(key);
      if (!(original instanceof HTMLSelectElement)) continue;
      const field = document.createElement('label');
      field.textContent = text(label);
      const select = document.createElement('select');
      select.className = 'form-select';
      for (const option of original.options) select.add(new Option(option.text, option.value));
      select.value = original.value;
      defaults.set(key, select);
      field.append(select);
      header.append(field);
    }
  }
  header.append(close);
  dialog.append(header, body);
  document.body.append(dialog);
  dialog.showModal();

  const lang = document.documentElement.lang.toLowerCase();
  const uppy = new Uppy({
    id: queue.container.id,
    autoProceed: droppedFiles.length > 0 && !!queue.options.autostart_on_select,
    locale: lang.startsWith('fr') ? french : /^zh-(tw|hant)/.test(lang) ? chinese : undefined,
    restrictions: {
      allowedFileTypes: extensions.length ? extensions.map(ext => '.' + ext) : null,
      maxNumberOfFiles: upload_maxcount || null,
      maxFileSize: !resize_on_upload && upload_maxsize ? upload_maxsize : null
    }
  });
  uppy.use(Dashboard, {
    inline: true, target: body, width: '100%', height: 450,
    theme: dialog.closest('[data-bs-theme]')?.dataset.bsTheme === 'dark' ? 'dark' : 'light',
    proudlyDisplayPoweredByUppy: false, showProgressDetails: true,
    doneButtonHandler: () => dialog.close()
  });
  uppy.use(DashboardImageEditor, {target: Dashboard, quality: 0.92});
  uppy.use(MediaPreview, {target: Dashboard});

  const entries = new Map();
  let transferring = false;
  let hasUploaded = false;
  uppy.on('file-removed', file => {
    const entry = entries.get(file.id);
    if (entry && entry.state !== 'done') queue.remove(entry);
    entries.delete(file.id);
  });
  uppy.addUploader(async ids => {
    const files = ids.map(id => uppy.getFile(id)).filter(Boolean);
    uppy.emit('upload-start', files);
    transferring = true;
    close.disabled = true;
    try {
      for (const original of files) {
        const file = uppy.getFile(original.id);
        if (!file) continue;
        if (queue.disposed || !queue.container.isConnected) {
          uppy.emit('upload-error', file, new Error(text('FLEXI_UPLOAD_ASSIGN_FAILED')));
          continue;
        }
        let entry = entries.get(file.id);
        if (!entry || !queue.files.includes(entry)) {
          const selected = new File([file.data], file.name, {type: file.type, lastModified: file.data.lastModified});
          [entry] = queue.addFiles([selected], false);
          if (!entry) {
            uppy.emit('upload-error', file, new Error(queue.message.textContent || text('FLEXI_UPLOAD_LIMIT_REACHED')));
            continue;
          }
          entries.set(file.id, entry);
        }
        for (const [key, select] of defaults) (entry.metadata ||= {})[key] = select.value;
        await queue.start(true, [entry], fraction => {
          const current = uppy.getFile(file.id);
          if (current) uppy.emit('upload-progress', current, {
            uploadStarted: current.progress.uploadStarted, bytesUploaded: fraction * file.size, bytesTotal: file.size
          });
        });
        if (!uppy.getFile(file.id)) continue;
        if (entry.state === 'done' && !entry.assignmentFailed) {
          hasUploaded = true;
          uppy.emit('upload-success', file, {status: 200, body: entry.result});
        }
        else uppy.emit('upload-error', file, new Error(entry.status.textContent || text('FLEXI_UPLOAD_ASSIGN_FAILED')));
      }
    } finally {
      transferring = false;
      close.disabled = false;
    }
  });
  uppy.on('complete', result => {
    if (result.successful.length && !result.failed.length) {
      dialog.close();
    }
  });
  close.addEventListener('click', () => { if (!transferring) dialog.close(); });
  dialog.addEventListener('cancel', event => {
    if (transferring) event.preventDefault();
  });
  dialog.addEventListener('close', () => {
    uppy.destroy();
    dialog.remove();
    if (hasUploaded && queue.options.refresh_on_complete) {
      location.reload();
      return;
    }
    if (opener.isConnected) opener.focus();
    else queue.container.closest('.fcfieldval_container')?.querySelector('.fc_files_uploader_toggle_btn')?.focus();
  }, {once: true});
  if (droppedFiles.length) {
    for (const file of droppedFiles) {
      try { uppy.addFile({name: file.name, type: file.type, data: file}); }
      catch (error) { uppy.info(error.message, 'error', 5000); }
    }
  }
}

window.FlexiUploadDashboard = {open};
