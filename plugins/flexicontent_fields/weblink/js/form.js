/** Web Link thumbnails from Noembed or the field's configured image list. */
window.fcfield_weblink = (() => {
  const debugToConsole = [];
  const currElement = [];
  const id = value => document.getElementById(value);

  function assign(input, value) {
    if (!input) return;
    input.value = value;
    const media = input.closest('joomla-field-media');
    if (media) media.updatePreview?.();
    else if (typeof window.jInsertFieldValue === 'function') window.jInsertFieldValue(value, input.id);
  }

  function selectImage(select) {
    const box = select.closest('.fc-field-props-box');
    if (!box || select.selectedIndex < 1) return;
    FlexiMediaMetadata.notice(box.querySelector('.fcfield_message_box'), '');
    assign(box.querySelector('.urlimage'), select.value);
    select.selectedIndex = 0;
  }

  function clearImageThumb(element) {
    const input = element?.closest('.fcfieldval_container')?.querySelector('.urlimage');
    assign(input, '');
  }

  function update_path_tip(element) {
    for (const tip of element.closest('.fc-field-props-box')?.querySelectorAll('.hasTipImgpath, .hasTipPreview') || []) {
      const [title, text] = (tip.title || tip.value || '').split('::', 2);
      if (title && typeof tip.store === 'function') {
        tip.store('tip:title', title);
        tip.store('tip:text', text);
      }
    }
  }

  async function fetchData(elementId, config) {
    const input = id(elementId + '_link');
    const box = id('fcfield_message_box_' + elementId);
    const metadata = FlexiMediaMetadata;
    metadata.cancel(input);
    if (!input?.value.trim()) {
      metadata.notice(box, FlexiForm.text('FLEXI_FIELD_WEBLINK_ENTER_MEDIA_URL'));
      return;
    }
    const source = metadata.webURL(input.value);
    if (!['youtube', 'vimeo'].includes(metadata.video(source).provider)) {
      metadata.notice(box, FlexiForm.text('FLEXI_FIELD_WEBLINK_ENTER_MEDIA_URL_WARNING'));
      return;
    }
    metadata.notice(box, '');
    try {
      const response = await metadata.load(input, metadata.noembed(source));
      if (!response) return;
      const thumbnail = metadata.webURL(response.thumbnail_url);
      if (response.error || !thumbnail) {
        metadata.notice(box, response.error || FlexiForm.text('FLEXI_FIELD_WEBLINK_ENTER_MEDIA_URL_WARNING'));
        return;
      }
      const hidden = id(elementId + '_mediathumburl');
      if (hidden) hidden.value = thumbnail.href;
      assign(id(elementId + '_image'), thumbnail.href);
    } catch (error) {
      if (debugToConsole[config]) console.warn(error);
      metadata.notice(box, FlexiForm.text('FLEXI_ERROR'), 'danger');
    }
  }

  return { debugToConsole, currElement, selectImage, fetchData, clearImageThumb, update_path_tip };
})();
