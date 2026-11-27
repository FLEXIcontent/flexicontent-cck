/**
 * FLEXIcontent's chunk protocol, adapted from the iamrobertja6 Uppy uploader.
 * Requests run in order. A new attempt gets its own server-side staging key.
 */
window.FlexiUploadTransport = (() => {
  const chunkSize = 1024 * 1024;

  function send(endpoint, form, signal, progress) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      const abort = () => xhr.abort();
      if (signal.aborted) {
        reject(signal.reason);
        return;
      }
      xhr.open('POST', endpoint);
      xhr.timeout = 120000;
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      signal.addEventListener('abort', abort, {once: true});
      xhr.addEventListener('loadend', () => signal.removeEventListener('abort', abort));
      xhr.upload.onprogress = event => {
        if (event.lengthComputable) progress(event.loaded);
      };
      xhr.onabort = () => reject(signal.reason);
      xhr.onerror = xhr.ontimeout = () => reject(new Error(Joomla.Text._('FLEXI_UPLOAD_CONNECTION_FAILED')));
      xhr.onload = () => {
        let response;
        try {
          response = JSON.parse(xhr.responseText);
        } catch (_) {
          const message = xhr.status === 403 ? 'FLEXI_UPLOAD_FORBIDDEN' : 'FLEXI_UPLOAD_INVALID_RESPONSE';
          reject(new Error(Joomla.Text._(message)));
          return;
        }
        if (xhr.status < 200 || xhr.status >= 300 || response?.jsonrpc !== '2.0' || response.error) {
          reject(new Error(response?.error?.message || Joomla.Text._('FLEXI_UPLOAD_REQUEST_FAILED')));
          return;
        }
        resolve(response);
      };
      xhr.send(form);
    });
  }

  async function upload(action, blob, name, fields, signal, progress) {
    const endpoint = new URL(action, document.baseURI);
    if (endpoint.origin !== location.origin) throw new Error(Joomla.Text._('FLEXI_UPLOAD_INVALID_URL'));
    const uploadId = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
    const chunks = Math.max(1, Math.ceil(blob.size / chunkSize));
    let response;
    for (let index = 0; index < chunks; index++) {
      const offset = index * chunkSize;
      const form = new FormData();
      for (const [key, value] of Object.entries(fields)) form.set(key, value);
      form.set('name', name);
      form.set('filename', name);
      form.set('chunk', index);
      form.set('chunks', chunks);
      form.set('filesize', blob.size);
      form.set('upload_id', uploadId);
      form.set('file', blob.slice(offset, offset + chunkSize), name);
      response = await send(endpoint.href, form, signal, loaded => progress(Math.min(blob.size, offset + loaded) / Math.max(1, blob.size)));
    }
    if (!response.data || (!response.data.id && !response.data.filename)) throw new Error(Joomla.Text._('FLEXI_UPLOAD_UNCONFIRMED'));
    delete response.data.full_path;
    return response;
  }

  // Preserve formats that the canvas encoder can write without changing the file extension.
  async function resize(file, options) {
    const width = options.upload_max_w;
    const height = options.upload_max_h;
    if (!options.resize_on_upload || width < 1 || height < 1 ||
        !/^image\/(jpeg|png|webp)$/.test(file.type) || typeof createImageBitmap !== 'function') return file;

    const bitmap = await createImageBitmap(file, {imageOrientation: 'from-image'});
    try {
      if (bitmap.width <= width && bitmap.height <= height) return file;
      const crop = options.upload_crop ?? Number(options.upload_method) === 1;
      const scale = crop ? Math.max(width / bitmap.width, height / bitmap.height)
        : Math.min(width / bitmap.width, height / bitmap.height);
      const canvas = document.createElement('canvas');
      canvas.width = crop ? width : Math.max(1, Math.round(bitmap.width * scale));
      canvas.height = crop ? height : Math.max(1, Math.round(bitmap.height * scale));
      const scaledWidth = bitmap.width * scale;
      const scaledHeight = bitmap.height * scale;
      canvas.getContext('2d').drawImage(bitmap, (canvas.width - scaledWidth) / 2,
        (canvas.height - scaledHeight) / 2, scaledWidth, scaledHeight);
      const blob = await new Promise(resolve => canvas.toBlob(resolve, file.type, options.upload_quality / 100));
      return blob?.type === file.type ? blob : file;
    } finally {
      bitmap.close();
    }
  }

  return {upload, resize};
})();
