/** Shared Media editor controls and provider metadata. */
window.fcfield_sharemedia = (() => {
  const debugToConsole = [];
  const use_native_apis = [];
  const youtube_key = [];
  const field = (id, name) => document.getElementById(id + '_' + name);
  const message = id => document.getElementById('fcfield_message_box_' + id);

  function setHeight(selector, factor) {
    for (const frame of document.querySelectorAll(selector)) frame.style.height = Math.floor(factor * frame.clientWidth) + 'px';
  }

  function setMetaData(id, data) {
    for (const key of ['title', 'author', 'duration', 'description', 'thumb']) {
      const control = field(id, key);
      if (control) control.value = data[key] ?? '';
    }
  }

  function toggleMETArows(id, action = 0) {
    const props = document.querySelector('.sm_value_props[data-name="' + CSS.escape(id) + '"]');
    if (props) { togglePanel(props, '.fc-sm-edit-btn', id, action); return; }
    for (const key of ['title', 'author', 'duration', 'description', 'api_type', 'embed_url', 'media_id', 'preview', 'width', 'height']) {
      const row = field(id, key)?.closest('tr');
      if (row) row.style.display = action === -1 || (!action && row.style.display !== 'none') ? 'none' : '';
    }
  }

  function togglePanel(panel, selector, id, action = 0) {
    if (!panel) return;
    const show = action === 1 || (action === 0 && getComputedStyle(panel).display === 'none');
    panel.style.display = show ? 'block' : 'none';
    const button = document.querySelector(selector + '[data-for="' + CSS.escape(id) + '"]');
    button?.classList.toggle('active', show);
    button?.setAttribute('aria-expanded', String(show));
  }

  function toggleURL(id) {
    togglePanel(document.querySelector('.sm_url_props[data-name="' + CSS.escape(id) + '"]'), '.fc-sm-url-btn', id);
  }

  function toggleFields(id) { toggleMETArows(id); }

  const embedHosts = {
    youtube: 'https://www.youtube.com/embed/',
    vimeo: 'https://player.vimeo.com/video/',
    dailymotion: 'https://www.dailymotion.com/embed/video/',
  };

  function setEmbedData(id, type, clip) {
    const host = embedHosts[type];
    const url = FlexiMediaMetadata.webURL(host && clip ? host + encodeURIComponent(clip) : clip);
    field(id, 'api_type').value = url ? type : '';
    field(id, 'media_id').value = url && host ? clip : '';
    field(id, 'embed_url').value = url && !host ? url.href : '';
    const preview = field(id, 'preview');
    preview.replaceChildren();
    if (!url) return;
    const frame = document.createElement('iframe');
    frame.className = 'sharedmedia seamless';
    frame.src = url.href;
    frame.title = FlexiForm.text('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_PREVIEW');
    frame.style.cssText = 'width:100%;height:100%;border:0;';
    frame.allowFullscreen = true;
    preview.append(frame);
    requestAnimationFrame(() => setHeight('iframe.sharedmedia', 2/3));
  }

  function clearData(id) {
    FlexiMediaMetadata.cancel(field(id, 'url'));
    FlexiMediaMetadata.notice(message(id), '');
    setEmbedData(id, '', '');
    setMetaData(id, {});
    for (const key of ['height','width']) { const control = field(id, key); if (control) control.value = ''; }
    toggleMETArows(id, -1);
  }

  function providerRequest(source, provider, clip, config) {
    switch (provider) {
      case 'youtube': {
        const url = new URL('https://www.googleapis.com/youtube/v3/videos');
        url.search = new URLSearchParams({ id:clip, key:youtube_key[config] || '', part:'snippet,contentDetails,statistics,status' });
        return url;
      }
      case 'vimeo': return new URL('https://vimeo.com/api/v2/video/' + clip + '.json');
      case 'dailymotion': return new URL('https://api.dailymotion.com/video/' + clip + '?fields=description,duration,owner.screenname,thumbnail_60_url,title');
      default: return FlexiMediaMetadata.noembed(source);
    }
  }

  function youtubeDurationToSeconds(value) {
    const parts = /^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/.exec(value || '');
    return parts ? (Number(parts[1] || 0) * 86400 + Number(parts[2] || 0) * 3600 + Number(parts[3] || 0) * 60 + Number(parts[4] || 0)) : '';
  }

  function parseMetadata(data, provider, clip) {
    if (data.error || data.type === 'error') throw new Error(data.error?.message || data.error_message || data.error || 'Media unavailable');
    switch (provider) {
      case 'youtube': {
        const item = data.items?.[0];
        if (!item?.snippet) throw new Error('Video not found');
        return { type:provider, clip, title:item.snippet.title, author:item.snippet.channelTitle,
          duration:youtubeDurationToSeconds(item.contentDetails?.duration), description:item.snippet.description, thumb:item.snippet.thumbnails?.medium?.url };
      }
      case 'vimeo': {
        const item = data[0];
        if (!item) throw new Error('Video not found');
        return { type:provider, clip, title:item.title, author:item.user_name, duration:item.duration, description:item.description, thumb:item.thumbnail_small };
      }
      case 'dailymotion':
        return { type:provider, clip, title:data.title, author:data['owner.screenname'], duration:data.duration, description:data.description, thumb:data.thumbnail_60_url };
      default: {
        const markup = document.createElement('template');
        markup.innerHTML = data.html || '';
        const embed = FlexiMediaMetadata.webURL(markup.content.querySelector('iframe[src], embed[src]')?.getAttribute('src'));
        if (!embed) throw new Error('Media response has no embed URL');
        return { type:'noembed:' + String(data.provider_name || '').toLowerCase(), clip:embed.href, title:data.title, author:data.author_name,
          duration:data.duration, description:data.description, thumb:data.thumbnail_url };
      }
    }
  }

  async function fetchData(id, config) {
    clearData(id);
    const input = field(id, 'url');
    if (!input?.value.trim()) return;
    const source = FlexiMediaMetadata.webURL(input.value);
    const box = message(id);
    if (!source) {
      FlexiMediaMetadata.notice(box, FlexiForm.text('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_URL_NOT_MEDIA'));
      return;
    }
    const { provider, id:clip } = use_native_apis[config] ? FlexiMediaMetadata.video(source) : {};
    try {
      const data = await FlexiMediaMetadata.load(input, providerRequest(source, provider, clip, config), true);
      if (!data) return;
      const result = parseMetadata(data, provider, clip);
      setEmbedData(id, result.type, result.clip);
      setMetaData(id, result);
      toggleMETArows(id, 1);
    } catch (error) {
      if (debugToConsole[config]) console.warn(error);
      FlexiMediaMetadata.notice(box, FlexiForm.text('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_SERVER_RESPONDED_WITH_ERROR') + ' ' + error.message);
    }
  }

  window.addEventListener('resize', () => setHeight('iframe.sharedmedia', 2/3));
  document.addEventListener('DOMContentLoaded', () => setHeight('iframe.sharedmedia', 2/3), {once:true});
  document.addEventListener('mouseenter', event => {
    if (event.target.matches?.('.fcfield_sharedmedia_valuebox')) setHeight('iframe.sharedmedia', 2/3);
  }, true);

  return { debugToConsole, use_native_apis, youtube_key, fetchData, clearData, setMetaData, setEmbedData, toggleMETArows, toggleURL, toggleFields, setHeight, youtubeDurationToSeconds };
})();
