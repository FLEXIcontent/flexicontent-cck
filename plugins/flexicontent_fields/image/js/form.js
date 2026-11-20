var fcfield_image = {
  debugToConsole: [],
  use_native_apis: [],
  currElement: [],
  dialog_handle: [],

  // Joomla detaches an empty media control's clear button but keeps its reference.
  prepareMediaClone(source, clone) {
    const originals = source.querySelectorAll('joomla-field-media');
    clone.querySelectorAll('joomla-field-media').forEach((media, index) => {
      const selector = media.getAttribute('button-clear');
      const button = originals[index]?.buttonClearEl;
      if (selector && button && !media.querySelector(selector)) {
        (media.querySelector('.input-group') || media).append(button.cloneNode(true));
      }
    });
  },

  toggleMediaURL(id) {
    const input = document.getElementById(id + '_mediaurl');
    const box = input?.closest('.fcfield-image-mediaurl-box');
    const row = box?.closest('.fcfieldval_container');
    if (!row) return;
    const props = row.querySelector('.fcimg_url_props');
    const button = row.querySelector('.fcfield-mediaurl-btn[data-for="' + id + '"]');
    const shown = getComputedStyle(props || box).display !== 'none';
    // Closing the panel preserves an existing URL and its fetched thumbnail.
    if (!shown) {
      row.querySelectorAll('.fc_file_uploader').forEach(node => FlexiForm.show(node, false));
      FlexiForm.show(row.querySelector('.fcfield_preview_box'));
      row.querySelectorAll('.fc_files_uploader_toggle_btn').forEach(node => {
        node.classList.remove('active'); node.setAttribute('aria-expanded', 'false');
      });
    }
    FlexiForm.show(props, !shown);
    FlexiForm.show(box, !shown);
    button?.classList.toggle('active', !shown);
    button?.setAttribute('aria-expanded', String(!shown));
    FlexiMediaMetadata.notice(document.getElementById('fcfield_message_box_' + id));
  },

  toggleFields(id) {
    const props = document.querySelector('.fcimg_value_props[data-name="' + id + '"]');
    if (!props) return;
    const shown = getComputedStyle(props).display === 'none';
    FlexiForm.show(props, shown);
    const button = document.querySelector('.fc-image-edit-btn[data-for="' + id + '"]');
    button?.classList.toggle('active', shown);
    button?.setAttribute('aria-expanded', String(shown));
  },

  showUploader(fieldName) {
    FlexiMediaField.showUploader('custom_' + fieldName);
  },

  clearData(id, key) {
    const input = document.getElementById(id + '_mediaurl');
    if (!input) return;
    FlexiMediaMetadata.cancel(input);
    input.value = '';
    const thumb = document.getElementById(id + '_mediathumburl');
    if (thumb) thumb.value = '';
    FlexiMediaMetadata.notice(document.getElementById('fcfield_message_box_' + id));
    fcfield_image.clearField(input, {}, key);
  },

  async fetchData(id, key) {
    const input = document.getElementById(id + '_mediaurl');
    const box = document.getElementById('fcfield_message_box_' + id);
    if (!input) return;
    const url = FlexiMediaMetadata.webURL(input.value);
    if (!url) {
      FlexiMediaMetadata.notice(box, FlexiForm.text('FLEXI_FIELD_IMAGE_ENTER_MEDIA_URL'));
      return;
    }
    FlexiMediaMetadata.notice(box);
    try {
      const data = await FlexiMediaMetadata.load(input, FlexiMediaMetadata.noembed(url), true);
      if (!data) return;
      const thumb = FlexiMediaMetadata.webURL(data.thumbnail_url);
      if (!thumb) {
        FlexiMediaMetadata.notice(box, String(data.error || FlexiForm.text('FIELDS_IMAGE_RESPONSE_PARSING_FAILED')), 'info');
        return;
      }
      fcfield_image.clearField(input, {}, key);
      const thumbInput = document.getElementById(id + '_mediathumburl');
      if (thumbInput) thumbInput.value = thumb.href;
      fcfield_image.assignImage(id, thumb.href, thumb.href, 2, FlexiForm.text('FLEXI_FIELD_MEDIA_URL'), key);
    } catch (error) {
      FlexiMediaMetadata.notice(box, FlexiForm.text('FIELDS_IMAGE_SERVER_RESPONDED_WITH_ERROR') + ' ' + error.message, 'danger');
    }
  },

  update_path_tip(element) {
    element.closest('.fc-field-props-box')?.querySelectorAll('.hasTipImgpath, .hasTipPreview').forEach(node => {
      const title = node.title || node.value;
      if (title && node.store) {
        const [heading, tooltip] = title.split('::', 2);
        node.store('tip:title', heading);
        node.store('tip:text', tooltip);
      }
    });
  },

  incrementValCnt(key) {
    const input = FlexiMediaField.row(document.getElementById(fcfield_image.currElement[key]))?.querySelector('.hasvalue');
    if (!input) return;
    const hadValue = !!input.value;
    input.value = '1';
    FlexiMediaField.changeCount(key, hadValue ? 0 : 1);
  },

  toggleProperties(button) {
    const props = FlexiMediaField.row(button)?.querySelector('.fcimg_value_props');
    if (!props) return;
    const shown = FlexiForm.visible(props);
    FlexiForm.show(props, !shown);
    button.setAttribute('aria-expanded', String(!shown));
  },

  clearFieldUploader(box) {
    FlexiMediaField.clearUploader(box);
  },

  clearField(element, options, key) {
    const box = FlexiMediaField.row(element);
    if (!box) return;
    const preview = box.querySelector('.fcfield_preview_box');
    if (options?.hide_image) {
      FlexiForm.show(preview, false);
    } else {
      const hadValue = !!box.querySelector('.hasvalue')?.value;
      FlexiMediaField.clearUploader(box);
      FlexiMediaField.setValue(box, '.originalname, .existingname, .hasvalue', '');
      box.querySelector('.fc_preview_thumb')?.setAttribute('src', FlexiMediaField.placeholder);
      const caption = box.querySelector('.fc_preview_msg');
      if (caption) {
        caption.textContent = ' ';
        caption.removeAttribute('title');
      }
      FlexiForm.show(preview);
      FlexiMediaField.changeCount(key, hadValue ? -1 : 0);
    }
    if (options?.keep_props) FlexiMediaField.setValue(box, 'input, textarea', '');
  },

  assignImage(tagId, file, previewUrl, keepModal, caption, key) {
    const id = tagId.replace(/_existingname$/, '');
    const input = document.getElementById(id);
    const box = FlexiMediaField.row(input);
    if (!box) return;
    const hadValue = !!input.value;
    input.value = file ? '1' : '';
    FlexiMediaField.setValue(box, '.existingname', file);
    FlexiMediaField.setValue(box, '.originalname', '');

    const preview = document.getElementById(id + '_preview_image');
    if (preview) {
      preview.src = previewUrl || FlexiMediaField.placeholder;
      if (keepModal != 2) {
        FlexiForm.show(preview.closest('.fcfield_preview_box'));
        FlexiMediaField.clearUploader(box);
      }
    }
    const message = document.getElementById(id + '_fc_preview_msg');
    if (message) {
      message.textContent = caption || file || '';
      message.title = caption ? file : '';
    }
    FlexiMediaField.resetRemoval(document.getElementById(id + '_remove'));
    FlexiMediaField.changeCount(key, Number(!!file) - Number(hadValue));
    if (!keepModal) fcfield_image.dialog_handle[key]?.dialog('close');
  },

  fileFiltered() {},

  fileUploaded(uploader, file, result, key) {
    const upload = FlexiMediaField.uploaded(uploader, result, '.existingname');
    if (upload) fcfield_image.assignImage(upload.target.id, upload.file.filename, upload.preview,
      window.FlexiUploader ? 0 : 2, upload.file.filename_original, key);
  }
};

function initQuantumImagePicker() {
  const open = window.QuantumuploadimageModalOpen;
  if (typeof open !== 'function') return;
  window.QuantumuploadimageModalOpen = function (...args) {
    const input = typeof quantumuploadimageSelector === 'undefined' ? null : quantumuploadimageSelector?.querySelector('input');
    const subpath = (input?.dataset.defaultSubpath || '').replace(/^\/+|\/+$/g, '');
    const scope = (input?.dataset.defaultScope || 'images').replace(/^\/+|\/+$/g, '');
    if (!input || !scope || !subpath) return open.apply(this, args);
    const value = input.value;
    input.value = '';
    localStorage.setItem('quantummanagerLastDir', 'root/' + subpath);
    localStorage.setItem('quantummanagerScope', scope);
    try {
      return open.apply(this, args);
    } finally {
      if (value) setTimeout(() => { if (!input.value) input.value = value; }, 200);
    }
  };
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initQuantumImagePicker, {once:true});
else initQuantumImagePicker();
