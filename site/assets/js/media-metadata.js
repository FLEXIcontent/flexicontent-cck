/** Requests shared by the fields that fetch metadata from external media providers. */
window.FlexiMediaMetadata = (() => {
  const pending = new WeakMap();

  function webURL(value) {
    if (typeof value !== 'string' || !/^(https?:)?\/\//i.test(value.trim())) return null;
    try {
      const url = new URL(value, document.baseURI);
      return /^https?:$/.test(url.protocol) ? url : null;
    } catch {
      return null;
    }
  }

  function video(source) {
    if (!source) return {};
    const host = source.hostname.toLowerCase().replace(/^(www|m)\./, '');
    const [, route, id] = source.pathname.split('/');
    let youtube;
    if (host === 'youtu.be') youtube = route;
    if (host === 'youtube.com' || host === 'youtube-nocookie.com') youtube = source.searchParams.get('v') || id;
    if (youtube && /^[\w-]{11}$/.test(youtube)) return { provider:'youtube', id:youtube };
    if (host === 'vimeo.com' && /^\d+$/.test(route)) return { provider:'vimeo', id:route };
    if (host === 'dailymotion.com' && ['video','hub'].includes(route)) {
      const clip = new URLSearchParams(source.hash.slice(1)).get('video') || id?.split('_')[0];
      if (clip && /^[\w-]+$/.test(clip)) return { provider:'dailymotion', id:clip };
    }
    return {};
  }

  function noembed(source) {
    const url = new URL('https://noembed.com/embed');
    url.searchParams.set('url', source.href);
    return url;
  }

  function cancel(input) {
    pending.get(input)?.abort();
    pending.delete(input);
  }

  // The answer is read as data. A provider that sends no CORS headers is not asked through a script element
  // (JSONP): that would run whatever the provider returns in the session of the editor.
  async function load(input, url) {
    cancel(input);
    const request = new AbortController();
    const value = input.value;
    pending.set(input, request);
    const timeout = setTimeout(() => request.abort(new Error('Metadata request timed out')), 12000);
    const current = () => pending.get(input) === request && input.isConnected && input.value === value;
    try {
      const response = await fetch(url, { signal: request.signal, credentials:'omit' });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const data = await response.json();
      return current() ? data : undefined;
    } catch (error) {
      if (current()) throw error;
      return undefined;
    } finally {
      clearTimeout(timeout);
      if (pending.get(input) === request) pending.delete(input);
    }
  }

  function notice(box, text, type = 'warning') {
    if (!box) return;
    box.replaceChildren();
    if (!text) return;
    const message = document.createElement('span');
    message.className = 'alert alert-' + type + ' fc-iblock fcpadded';
    message.setAttribute('role', 'status');
    message.textContent = text;
    box.append(message);
  }

  return { webURL, video, noembed, load, cancel, notice };
})();
