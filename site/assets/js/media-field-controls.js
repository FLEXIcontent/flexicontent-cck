/** Native controls shared by the Image, File and Media File field forms. */
window.FlexiMediaField = (() => {
  const placeholder = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
  const row = element => FlexiForm.element(element)?.closest('.fcfieldval_container');
  const counter = key => document.getElementById('custom_' + key);

  function changeCount(key, delta) {
    const input = counter(key);
    if (!input) return;
    if (delta) input.value = Math.max(0, (parseInt(input.value, 10) || 0) + delta) || '';
    input.dispatchEvent(new FocusEvent('blur'));
  }

  function resetRemoval(input) {
    if (!input) return;
    input.checked = false;
    FlexiForm.change(input);
  }

  function beforeToggle(button, kind) {
    const box = row(button);
    const preview = box.querySelector('.fcfield_preview_box');
    const media = box.querySelector('.fc_media_file_box');
    if (media && kind !== 'image') {
      FlexiForm.show(preview, !FlexiForm.visible(preview));
      FlexiForm.show(media, !FlexiForm.visible(media));
      return false;
    }
    const opening = !FlexiForm.visible(box.querySelector('.fc_file_uploader'));
    button.classList.toggle('active', opening);
    button.setAttribute('aria-expanded', String(opening));
    FlexiForm.show(preview, !opening);
    if (kind !== 'image') box.querySelector('.inlinefile-prv-box')?.classList.toggle('empty', !opening);
    return true;
  }

  function clearUploader(box) {
    box = FlexiForm.element(box);
    box?.querySelectorAll('.fc_file_uploader').forEach(container => {
      const prefix = container.dataset.tagidPrefix;
      const suffix = prefix ? container.id.slice(prefix.length) : '';
      window[prefix]?.clearUploader(suffix);
      container.style.display = 'none';
    });
    box?.querySelectorAll('.fc_files_uploader_toggle_btn').forEach(button => {
      button.classList.remove('active');
      button.setAttribute('aria-expanded', 'false');
    });
  }

  function uploaded(uploader, result, selector) {
    const container = FlexiForm.element(uploader.settings.container);
    const target = row(container)?.querySelector(selector);
    if (!target) return null;
    let response = result;
    if (!response.data && typeof response.response === 'string') {
      try { response = JSON.parse(response.response); }
      catch { return null; }
    }
    if (response.error) {
      FlexiForm.alert(response.error.message || response.error);
      return null;
    }
    return response.data ? {
      target, file: response.data,
      preview: response.preview || container.querySelector('.plupload_img_preview > img')?.getAttribute('src') || ''
    } : null;
  }

  function setValue(box, selector, value, event) {
    box.querySelectorAll(selector).forEach(input => {
      input.value = value ?? '';
      if (event === 'blur') input.dispatchEvent(new FocusEvent('blur'));
      if (event === 'change') FlexiForm.change(input);
    });
  }

  function setText(box, selector, value, secondLine) {
    box.querySelectorAll(selector).forEach(node => {
      node.textContent = value || '';
      if (secondLine) node.append(document.createElement('br'), secondLine);
    });
  }

  function hasFile(box) {
    return !!box.querySelector('.fc_filedata_txt')?.value || Number(box.querySelector('.fc-file-id')?.value) > 0;
  }

  function clearFile(element, options, key, clearPreview) {
    const box = row(element);
    if (!box) return;
    const hadValue = hasFile(box);
    const previousCount = counter(key)?.value;
    const preview = box.querySelector('.fcfield_preview_box');
    box.querySelectorAll('.field-media-wrapper .button-clear, .clear-btn').forEach(button => button.click());
    FlexiForm.show(preview, false);
    if (!options?.hide_image) {
      clearUploader(box);
      clearPreview?.(box);
      setValue(box, '.fc_filedata_txt, .fc-file-id, .hasvalue', '');
      const input = box.querySelector('.fc_filedata_txt');
      if (input) delete input.dataset.filename;
      setText(box, '.fc_filedata_storage_name, .fc_filedata_txt_nowrap, .fc_preview_text, .fc_preview_msg', '');
      box.querySelector('.fc_preview_thumb')?.setAttribute('src', placeholder);
      FlexiForm.show(box.querySelector('.fc_media_file_box'));
      if (hadValue && counter(key)?.value === previousCount) changeCount(key, -1);
    }
    if (options?.keep_props) setValue(box, 'input, textarea', '');
  }

  function clearMediaFile(button, emptyPreview, clearPreview) {
    const box = row(button);
    if (!box) return;
    const hadValue = hasFile(box);
    const field = box.querySelector('.fc_media_file_box .fc_mediafile');
    const input = box.querySelector('.fc_filedata_txt');
    if (field) field.value = '';
    clearPreview?.(box);
    setValue(box, '.fc_filedata_txt, .fc_filedata_title, .hasvalue', '');
    setValue(box, '.fc-file-id', '0');
    if (input) delete input.dataset.filename;
    setText(box, '.fc_filedata_storage_name, .fc_filedata_txt_nowrap, .fc_preview_text, .fc_preview_msg', '');
    box.querySelectorAll('.inline-preview-img, .inline-preview-obj').forEach(preview => {
      preview.setAttribute(preview.matches('img') ? 'src' : 'data', emptyPreview || placeholder);
      FlexiForm.show(preview, false);
    });
    if (hadValue) changeCount(field?.dataset.config_name || input?.closest('[data-fc-media-key]')?.dataset.fcMediaKey, -1);
  }

  function setFileRemoval(input) {
    const box = row(input);
    if (!box) return;
    box.querySelectorAll('.fc_preview_thumb').forEach(preview => { preview.style.opacity = input.checked ? '0.4' : '1'; });
    box.querySelectorAll('.fc_filedata_txt').forEach(field => { field.style.textDecoration = input.checked ? 'line-through' : ''; });
  }

  function assignFile(id, source, keepModal, key, dialogs, assignPreview, fromFolder = false) {
    const target = document.getElementById(id);
    const box = row(target);
    if (!box || !source?.filename) return false;
    const input = box.querySelector('.fc_filedata_txt');
    key ||= target.dataset.config_name || input?.closest('[data-fc-media-key]')?.dataset.fcMediaKey;
    const file = {...source};
    if (!fromFolder) Object.keys(file).forEach(name => {
      if (typeof file[name] !== 'string') return;
      try { file[name] = decodeURIComponent(file[name]); } catch { /* Already decoded. */ }
    });

    const hadValue = hasFile(box);
    if (fromFolder && target !== input) target.value = file.filename;
    const distinctTitle = file.altname && file.altname !== file.filename;
    setValue(box, '.fc-file-id', file.id);
    setText(box, '.fc_filedata_storage_name', file.filename);
    setValue(box, '.fc_filedata_txt', file.filename_original || file.filename, 'blur');
    if (input) {
      input.classList.remove('file_unpublished');
      input.dataset.filename = file.filename;
    }
    setText(box, '.fc_filedata_txt_nowrap', distinctTitle || fromFolder ? file.filename : '', distinctTitle ? file.altname : '');
    FlexiForm.show(box.querySelector('.fc_filedata_txt_nowrap'));
    setValue(box, '.fc_filedata_title', fromFolder ? '' : distinctTitle ? file.altname : '-');

    const previewBox = box.querySelector('.fcfield_preview_box');
    box.querySelector('.fc_preview_thumb')?.setAttribute('src', file.preview || placeholder);
    const showImage = file.preview && (!fromFolder || /\.(?:jpe?g|gif|png|webp|bmp|ico|xcf|odg|wbmp)(?:[?#]|$)/i.test(file.filename));
    setText(box, '.fc_preview_text', showImage ? '' : String(file.ext || file.filename.split('.').pop()).toUpperCase());
    if (fromFolder) {
      const inlineImage = box.querySelector('img.inline-preview-img');
      const inlineObject = box.querySelector('object.inline-preview-obj');
      inlineImage?.setAttribute('src', showImage ? file.preview : placeholder);
      inlineObject?.setAttribute('data', !showImage && file.preview ? file.preview : '');
      FlexiForm.show(inlineImage, !!showImage);
      FlexiForm.show(inlineObject, !showImage && !!file.preview);
    } else if (keepModal != 2) {
      if (showImage) FlexiForm.show(previewBox);
      clearUploader(box);
    }
    if (!showImage && previewBox?.classList.contains('auto')) FlexiForm.show(previewBox, false);

    setValue(box, '.fc_filetitle', fromFolder ? file.filename : file.altname, 'blur');
    setValue(box, '.fc_filelang', file.language, 'change');
    setValue(box, '.fc_filedesc', file.description);
    resetRemoval(box.querySelector('.inlinefile-del'));
    assignPreview?.(box, input, file, key);
    changeCount(key, hadValue ? 0 : 1);
    if (!keepModal) dialogs?.[key]?.dialog('close');
    return true;
  }

  function showUploader(id) {
    const box = row(document.getElementById(id));
    const uploader = box?.querySelector('.fc_file_uploader');
    if (uploader && window[uploader.dataset.tagidPrefix]) box.querySelector('.fc_files_uploader_toggle_btn')?.click();
  }

  return { placeholder, row, changeCount, resetRemoval, beforeToggle, clearUploader, uploaded,
    setValue, setText, clearFile, clearMediaFile, setFileRemoval, assignFile, showUploader };
})();
