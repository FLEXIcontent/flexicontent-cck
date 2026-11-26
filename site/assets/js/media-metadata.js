/** Requests shared by the fields that fetch metadata from external media providers. */
window.FlexiMediaMetadata = (() => {
  const pending = new WeakMap();
  let requestId = 0;

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

  function jsonp(url, signal) {
    return new Promise((resolve, reject) => {
      if (signal.aborted) { reject(signal.reason); return; }
      const script = document.createElement('script');
      const callback = 'fcMediaMetadata' + ++requestId;
      const source = new URL(url);
      source.searchParams.set('callback', callback);
      function cleanup() {
        signal.removeEventListener('abort', abort);
        script.remove();
        delete window[callback];
      }
      function abort() {
        cleanup();
        reject(signal.reason);
      }
      window[callback] = data => { cleanup(); resolve(data); };
      script.onerror = () => { cleanup(); reject(new Error('Metadata request failed')); };
      signal.addEventListener('abort', abort, { once:true });
      script.src = source.href;
      document.head.append(script);
    });
  }

  function cancel(input) {
    pending.get(input)?.abort();
    pending.delete(input);
  }

  async function load(input, url, allowJsonp = false) {
    cancel(input);
    const request = new AbortController();
    const value = input.value;
    pending.set(input, request);
    const timeout = setTimeout(() => request.abort(new Error('Metadata request timed out')), 12000);
    const current = () => pending.get(input) === request && input.isConnected && input.value === value;
    try {
      let response;
      try {
        response = await fetch(url, { signal: request.signal, credentials:'omit' });
      } catch (error) {
        // Some providers support JSONP when CORS is unavailable.
        if (!allowJsonp || request.signal.aborted || !(error instanceof TypeError)) throw error;
      }
      if (response && !response.ok) throw new Error('HTTP ' + response.status);
      const data = response ? await response.json() : await jsonp(url, request.signal);
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
