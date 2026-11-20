/* FLEXIcontent upload queues for inline fields, the picker and File Manager. */
var fc_file_props_handle = null;

window.FlexiUploader = (() => {
  const queues = new WeakMap();
  const translate = key => (Joomla.Text || Joomla.JText)._(key);
  const imageExtensions = /\.(jpe?g|gif|png|webp|bmp|wbmp|ico)$/i;

  function button(text, className, callback) {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'btn ' + className;
    el.textContent = text;
    el.addEventListener('click', callback);
    return el;
  }

  function resetUploadValidation(input) {
    if (!input) return;
    input.value = '';
    input.dataset.errorMssg = '';
    input.classList.remove('invalid');
    input.removeAttribute('aria-invalid');
    const box = input.closest('.fc_uploader_status_box') || input.parentElement;
    // The legacy validator prepends its notice; the native validator can append it.
    box?.querySelectorAll(':scope > .fc-field-invalid').forEach(notice => notice.remove());
  }

  class Queue {
    constructor(container, options, owner, suffix) {
      this.container = container;
      this.options = options;
      this.owner = owner;
      this.settings = {container};
      this.suffix = suffix;
      this.files = [];
      this.input = document.createElement('input');
      this.input.type = 'file';
      this.input.className = 'form-control';
      this.input.multiple = options.upload_maxcount !== 1;
      const accepted = options.extensions;
      if (accepted.length) this.input.accept = accepted.map(ext => '.' + ext).join(',');
      this.input.setAttribute('aria-label', translate('FLEXI_UPLOAD'));
      this.input.addEventListener('change', () => {
        this.addFiles(this.input.files);
        this.input.value = '';
      });

      const panel = document.createElement('div');
      panel.className = 'fc-native-upload plupload_container';
      panel.style.setProperty('--fc-upload-thumb-size', options.thumb_size_default + 'px');
      this.list = document.createElement('ul');
      this.list.className = 'fc-native-upload-list plupload_filelist';
      this.message = document.createElement('div');
      this.message.className = 'fc-native-upload-message';
      this.message.setAttribute('role', 'status');
      this.startButton = button(translate('FLEXI_START_UPLOAD'), 'btn-primary', () => this.start());
      this.startButton.hidden = options.autostart_on_select;
      const footer = document.createElement('div');
      footer.className = 'fc-native-upload-controls';
      footer.append(this.input, this.startButton);
      panel.append(footer, this.message, this.list);
      if (options.add_size_slider) {
        const size = document.createElement('select');
        size.className = 'form-select fc-native-upload-size';
        size.setAttribute('aria-label', translate('FLEXI_THUMBNAILS'));
        const sizes = options.thumb_size_slider_cfg?.step_values || [90, 120, 150, 200, 250];
        for (const value of sizes) size.add(new Option(value + ' × ' + value, value));
        size.selectedIndex = options.thumb_size_slider_cfg?.initial_pos || 0;
        const updateSize = () => panel.style.setProperty('--fc-upload-thumb-size', size.value + 'px');
        size.addEventListener('change', updateSize);
        updateSize();
        footer.append(size);
      }
      container.replaceChildren(panel);
      container.addEventListener('dragover', this.onDragOver);
      container.addEventListener('drop', this.onDrop);
      this.refresh();
    }

    getOption(name) {
      return name === 'tag_id_sfx' ? this.suffix : this.options[name];
    }

    onDragOver = event => {
      if (event.dataTransfer?.types.includes('Files')) event.preventDefault();
    };

    onDrop = event => {
      if (!event.dataTransfer?.files.length) return;
      event.preventDefault();
      this.addFiles(event.dataTransfer.files);
    };

    addFiles(files) {
      if (this.running || this.disposed) return;
      const errors = [];
      for (const file of files) {
        const extension = file.name.split('.').pop().toLowerCase();
        if (this.options.extensions.length && !this.options.extensions.includes(extension)) {
          errors.push(file.name + ': ' + translate('FLEXI_UPLOAD_TYPE_REJECTED'));
          continue;
        }
        // Selecting a replacement in a single-value field releases the old slot,
        // including a server-rejected file. Keep it until a new valid choice is made.
        if (this.options.upload_maxcount === 1) {
          for (const entry of this.files.filter(entry => ['done', 'failed'].includes(entry.state))) this.remove(entry);
        }
        if (this.files.some(entry => entry.state !== 'done' && entry.file.name === file.name &&
            entry.file.size === file.size && entry.file.lastModified === file.lastModified)) continue;
        if (this.options.upload_maxcount && this.files.filter(entry => entry.state !== 'done').length >= this.options.upload_maxcount) {
          errors.push(file.name + ': ' + translate('FLEXI_UPLOAD_LIMIT_REACHED'));
          continue;
        }
        const id = 'fc-upload-' + Array.from(crypto.getRandomValues(new Uint32Array(4)), n => n.toString(16).padStart(8, '0')).join('');
        const entry = {id, file, name: file.name, state: 'queued', getNative: () => file};
        this.files.push(entry);
        this.addRow(entry);
        this.options.handle_FileFiltered?.(this, entry);
      }
      this.message.textContent = errors.join(' ');
      this.refresh();
      if (this.options.autostart_on_select) this.start(false);
    }

    addRow(entry) {
      const row = entry.row = document.createElement('li');
      row.id = entry.id;
      row.className = 'fc-native-upload-row plupload_file';
      const name = entry.label = document.createElement('span');
      name.className = 'plupload_file_name';
      name.textContent = entry.name;
      entry.status = document.createElement('span');
      entry.status.className = 'fc-native-upload-status';
      entry.progress = document.createElement('progress');
      entry.progress.max = 1;
      entry.progress.value = 0;
      entry.progress.hidden = true;
      entry.progress.setAttribute('aria-label', translate('FLEXI_UPLOADING'));
      const actions = document.createElement('div');
      actions.className = 'fc-native-upload-actions';
      entry.removeButton = button(translate('FLEXI_REMOVE_FILE'), 'btn-outline-secondary fc_uploader_row_remove', () => this.remove(entry));
      entry.retryButton = button(translate('FLEXI_START_UPLOAD'), 'btn-outline-primary', () => this.start());
      entry.retryButton.hidden = true;
      actions.append(entry.retryButton, entry.removeButton);
      if (this.options.edit_properties) {
        entry.editButton = button(translate('FLEXI_FILE_PROPERTIES'), 'btn-outline-secondary fc_props_edit_btn', () => this.editProperties(entry));
        actions.prepend(entry.editButton);
      }
      if (this.options.upload_maxcount !== 1) {
        entry.moveButtons = [
          button('↑', 'btn-outline-secondary', () => this.move(entry, -1)),
          button('↓', 'btn-outline-secondary', () => this.move(entry, 1)),
        ];
        entry.moveButtons[0].setAttribute('aria-label', translate('JLIB_HTML_MOVE_UP'));
        entry.moveButtons[1].setAttribute('aria-label', translate('JLIB_HTML_MOVE_DOWN'));
        actions.append(...entry.moveButtons);
      }
      if (imageExtensions.test(entry.name)) {
        entry.previewUrl = URL.createObjectURL(entry.file);
        const preview = document.createElement('span');
        preview.className = 'plupload_img_preview';
        const img = document.createElement('img');
        img.src = entry.previewUrl;
        img.alt = '';
        preview.append(img);
        row.append(preview);
      }
      row.append(name, entry.status, entry.progress, actions);
      this.list.append(row);
    }

    move(entry, offset) {
      if (this.running) return;
      const index = this.files.indexOf(entry);
      const adjacent = this.files[index + offset];
      if (!adjacent) return;
      [this.files[index], this.files[index + offset]] = [adjacent, entry];
      if (offset < 0) adjacent.row.before(entry.row);
      else adjacent.row.after(entry.row);
    }

    refresh() {
      this.input.disabled = this.running;
      this.startButton.disabled = this.running || !this.files.some(entry => entry.state !== 'done');
      this.files.forEach(entry => {
        if (entry.editButton) entry.editButton.disabled = this.running || entry.state === 'done';
        if (entry.moveButtons) entry.moveButtons.forEach(el => { el.disabled = this.running; });
        entry.retryButton.hidden = this.running || entry.state !== 'failed';
      });
    }

    remove(entry) {
      if (entry.controller) entry.controller.abort();
      this.files = this.files.filter(candidate => candidate !== entry);
      if (entry.previewUrl) URL.revokeObjectURL(entry.previewUrl);
      entry.row.remove();
      this.refresh();
    }

    async start(retryFailed = true) {
      const pending = entry => entry.state === 'queued' || (retryFailed && entry.state === 'failed');
      if (this.running || this.disposed || !this.files.some(pending)) return;
      this.running = true;
      this.refresh();
      const status = this.container.parentElement.querySelector('input.validate-fcuploader');
      if (status) {
        status.value = 'done'; // Existing FLEXIcontent validator treats this as an unfinished assignment.
        status.dataset.errorMssg = translate('FLEXI_PLEASE_WAIT');
      }
      try {
        for (const entry of [...this.files]) {
          if (this.disposed || !pending(entry) || !this.files.includes(entry)) continue;
          await this.upload(entry);
        }
        if (!this.disposed && this.options.refresh_on_complete && this.files.length &&
            this.files.every(entry => entry.state === 'done')) location.reload();
      } finally {
        this.running = false;
        this.refresh();
        if (queues.get(this.container) === this) resetUploadValidation(status);
      }
    }

    async upload(entry) {
      const controller = entry.controller = new AbortController();
      entry.state = 'uploading';
      entry.status.textContent = translate('FLEXI_UPLOADING');
      entry.progress.hidden = false;
      entry.progress.value = 0;
      try {
        const blob = await FlexiUploadTransport.resize(entry.file, this.options).catch(() => entry.file);
        if (this.options.upload_maxsize && blob.size > this.options.upload_maxsize) {
          throw new Error(translate('FLEXI_UPLOAD_LIMIT_REACHED'));
        }
        const fields = {...entry.metadata, file_row_id: entry.id};
        const response = await FlexiUploadTransport.upload(this.options.action, blob, entry.name, fields,
          controller.signal, value => { entry.progress.value = value; });
        if (controller.signal.aborted || this.disposed || !this.container.isConnected) return;
        entry.state = 'done';
        entry.status.textContent = translate('FLEXI_UPLOAD_FINISHED');
        const preview = entry.previewUrl && this.options.handle_FileUploaded
          ? await this.readPreview(blob) : '';
        if (controller.signal.aborted || this.disposed || !this.container.isConnected) return;
        // Pass the parsed result and the previous callback response shape for third-party fields.
        this.options.handle_FileUploaded?.(this, entry, {...response, preview, response: JSON.stringify(response)});
      } catch (error) {
        if (!controller.signal.aborted) {
          const saved = entry.state === 'done';
          if (!saved) entry.state = 'failed';
          entry.status.textContent = saved ? translate('FLEXI_UPLOAD_ASSIGN_FAILED') : error.message;
        }
      } finally {
        entry.controller = null;
        entry.progress.hidden = true;
      }
    }

    readPreview(blob) {
      return new Promise(resolve => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result || '');
        reader.readAsDataURL(blob);
      });
    }

    editProperties(entry) {
      const form = document.getElementById('filePropsForm');
      const box = document.getElementById('filePropsForm_box');
      if (!form || !box) return;
      form.reset();
      for (const el of form.elements) {
        if (entry.properties?.has(el.name)) {
          const value = entry.properties.get(el.name);
          if (el.type === 'radio') el.checked = el.value === value;
          else el.value = value;
        }
        if (el.tagName === 'SELECT') el.dispatchEvent(new Event('change', {bubbles: true}));
      }
      const dot = entry.name.lastIndexOf('.');
      form.elements.namedItem('file-props-name').value = dot < 1 ? entry.name : entry.name.slice(0, dot);
      form.elements.namedItem('file-props-name-ext').value = dot < 1 ? '' : entry.name.slice(dot + 1);
      form.elements.namedItem('file_row_id').value = entry.id;
      fc_file_props_handle = fc_showAsDialog(box, 600, 600, null, {title: translate('FLEXI_FILE_PROPERTIES')});
    }

    submitProperties(button) {
      const form = button.form;
      const data = new FormData(form);
      const entry = this.files.find(file => file.id === data.get('file_row_id'));
      if (!entry || this.running) return;
      const name = fc_sanitize_filename(data.get('file-props-name') || '');
      if (!name) {
        form.elements.namedItem('file-props-name').focus();
        return;
      }
      const dot = entry.file.name.lastIndexOf('.');
      entry.name = name + (dot > 0 ? entry.file.name.slice(dot) : '');
      entry.label.textContent = entry.name;
      entry.properties = data;
      entry.metadata = {};
      for (const [input, key] of [['file-props-title', 'file-title'], ['file-props-desc', 'file-desc'],
        ['file-props-lang', 'file-lang'], ['file-props-access', 'file-access'], ['secure', 'secure'], ['stamp', 'stamp']]) {
        if (data.has(input)) entry.metadata[key] = data.get(input);
      }
      entry.editButton.classList.add('btn-success');
      fc_file_props_handle.dialog('close');
    }

    destroy() {
      this.disposed = true;
      for (const entry of [...this.files]) this.remove(entry);
      this.container.removeEventListener('dragover', this.onDragOver);
      this.container.removeEventListener('drop', this.onDrop);
      if (this.toggleButton) {
        this.toggleButton.classList.remove('active');
        this.toggleButton.setAttribute('aria-expanded', 'false');
      }
      resetUploadValidation(this.container.parentElement?.querySelector('input.validate-fcuploader'));
      queues.delete(this.container);
      this.container.replaceChildren();
    }
  }

  class Uploader {
    constructor(options) {
      this.options = options;
      this.uploader_instances = {};
    }

    toggleUploader(suffix, action, button) {
      const container = document.getElementById(this.options.tag_id + suffix);
      if (!container) return;
      const current = queues.get(container);
      const show = action ? action === 'show' : !current || container.style.display === 'none';
      container.style.display = show ? '' : 'none';
      if (button) {
        button.classList.toggle('active', show);
        button.setAttribute('aria-expanded', String(show));
        const row = button.closest('.fcfieldval_container');
        row?.querySelectorAll('.fcfield_preview_box').forEach(preview => {
          preview.style.display = show ? 'none' : '';
        });
        container.closest('.inlinefile-prv-box')?.classList.toggle('empty', !show);
      }
      if (!show) return current;
      if (current) {
        if (button) current.toggleButton = button;
        return current;
      }
      this.uploader_instances[suffix]?.destroy();
      const queue = this.uploader_instances[suffix] = new Queue(container, this.options, this, suffix);
      queue.toggleButton = button;
      queues.set(container, queue);
      // Existing site overrides use these data keys to reach their properties dialog or preview.
      if (window.jQuery) {
        window.jQuery(container).data('plupload_instance', queue);
        window.jQuery(queue).data('fc_plupload_instance', this);
      }
      this.autoResize(suffix);
      return queue;
    }

    clearUploader(suffix) {
      this.uploader_instances[suffix]?.destroy();
      delete this.uploader_instances[suffix];
    }

    autoResize(suffix) {
      if (!this.options.height_spare) return;
      const height = Math.max(120, Math.min(800, window.innerHeight - this.options.height_spare));
      const queues = suffix === undefined ? Object.values(this.uploader_instances) : [this.uploader_instances[suffix]];
      queues.forEach(queue => { if (queue) queue.list.style.maxHeight = height + 'px'; });
    }

    submit_props_form(button, queue) {
      queue.submitProperties(button);
    }
  }

  // Block item form submission while an upload is still assigning a value.
  document.addEventListener('submit', event => {
    const form = event.target;
    if (/(^|\.)cancel$/.test(form.elements?.namedItem('task')?.value || '')) return;
    const pending = [...form.querySelectorAll('.fc_file_uploader')].some(container => queues.get(container)?.running);
    if (!pending) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    Joomla.renderMessages?.({warning: [translate('FLEXI_PLEASE_WAIT')]});
  }, true);

  // Repeatable fields may remove a whole row without going through clearUploader().
  new MutationObserver(records => {
    for (const record of records) for (const item of record.removedNodes) {
      if (item.nodeType !== Node.ELEMENT_NODE || item.isConnected) continue;
      const containers = item.matches('.fc_file_uploader') ? [item] : item.querySelectorAll('.fc_file_uploader');
      for (const container of containers) {
        const queue = queues.get(container);
        if (queue) queue.owner.clearUploader(queue.suffix);
      }
    }
  }).observe(document.documentElement, {childList: true, subtree: true});

  return {
    Uploader,
    toggleImageProperties(button) {
      const props = button.closest('.fcfieldval_container')?.querySelector('.fcimg_value_props');
      if (!props) return;
      const show = props.style.display === 'none';
      props.style.display = show ? '' : 'none';
      button.setAttribute('aria-expanded', String(show));
    },
    clear(container) {
      const queue = queues.get(container);
      if (queue) queue.owner.clearUploader(queue.suffix);
    },
    submitProperties(button, id) {
      queues.get(document.getElementById(id))?.submitProperties(button);
    },
  };
})();
