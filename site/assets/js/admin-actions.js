/** Native requests for the administrator's result forms and maintenance tasks. */
(function (window, document) {
  'use strict';
  const F = window.FlexiForm;
  const node = id => document.getElementById(id);
  const text = (id, value) => { if (node(id)) node(id).textContent = value; };
  const html = (id, value) => { if (node(id)) node(id).innerHTML = value; };
  async function request(url, options = {}) {
    const response = await fetch(url, {credentials: 'same-origin', ...options});
    if (!response.ok || response.redirected) throw Error('Request failed (HTTP ' + response.status + ')');
    return response.text();
  }
  const endpoint = (base, params) => {
    const url = new URL(base, document.baseURI);
    Object.entries(params).forEach(([name, value]) => url.searchParams.set(name, value));
    return url;
  };
  function progress(done, total) {
    const percent = total ? Math.min(100, 100 * done / total) : 100;
    if (node('insideprogress')) node('insideprogress').style.width = percent + '%';
    text('updatepercent', ' ' + percent.toFixed(2) + ' %');
  }
  function fields(response, separator) {
    const parts = response.split(separator);
    if (parts[0].trim() !== 'success') {
      // Error pages and failed responses are text, never executable markup.
      const doc = new DOMParser().parseFromString(parts[1] || response, 'text/html');
      throw Error((doc.body.textContent || 'Unexpected server response').trim().slice(0, 1000));
    }
    return parts;
  }
  function number(value) {
    const result = Number(value);
    if (!Number.isSafeInteger(result) || result < 0) throw Error('Invalid progress response');
    return result;
  }
  const pause = () => new Promise(resolve => setTimeout(resolve, 20));
  window.FlexiAdmin = {
    bindResultForm(form, result) {
      if (!form || !result || form.fc_result_bound) return;
      form.fc_result_bound = true;
      form.addEventListener('submit', async event => {
        event.preventDefault();
        if (form.fc_result_busy) return;
        form.fc_result_busy = true;
        result.innerHTML = '<span class="ajax-loader"></span>';
        try {
          const body = new URLSearchParams();
          for (const [name, value] of new FormData(form)) if (typeof value === 'string') body.append(name, value);
          await F.insertHTML(result, await request(form.action, {method: 'POST', body}));
        } catch (error) { result.textContent = error.message; }
        finally { form.fc_result_busy = false; }
      });
    },
    async runIndexer(options) {
      const start = performance.now();
      try {
        const initial = fields(await request(options.countUrl), '|');
        const total = number(initial[1]);
        let done = number(initial[3]);
        progress(done, total);
        while (done < total) {
          const result = fields(await request(endpoint(options.indexUrl, {records_per_call: 1000, records_cnt: done})), '|');
          const next = number(result[1]);
          if (next <= done) throw Error('Indexer stopped making progress');
          done = next;
          progress(done, total);
          html('statuscomment', Math.min(done, total) + ' / ' + total + ' ' + String(options.records).replace(/[&<>]/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;'}[c]))
            + '<br>' + (result[2] || '') + '<br>Total task time: ' + ((performance.now() - start) / 1000).toFixed(2)
            + ' secs<br>' + (result[3] || ''));
          await pause();
        }
        progress(total, total);
        node('statuscomment')?.insertAdjacentHTML('beforeend', '<br><strong>INDEXING FINISHED</strong>. You may close this window');
      } catch (error) { text('statuscomment', 'INDEXER HALTED: ' + error.message); }
    },
    async runImport(options) {
      try {
        const initial = fields(await request(options.countUrl), '|');
        const total = number(initial[1]), batch = number(options.batch);
        if (!batch) throw Error('Import batch must be greater than zero');
        let done = number(initial[2]), token = initial[3];
        progress(done, total);
        while (done < total) {
          const result = fields(await request(endpoint(options.importUrl, {items_per_call: batch, itemcnt: done, [token]: 1})), '||||');
          token = result[1];
          node('statuslog')?.insertAdjacentHTML('beforeend', result.slice(2).join('||||'));
          done = Math.min(total, done + batch);
          progress(done, total);
          text('statuscomment', done + ' / ' + total + ' items');
          await pause();
        }
        const failed = document.querySelectorAll('#statuslog .fc_import_failed').length;
        text('statuscomment', failed ? 'IMPORT FINISHED WITH ' + failed + ' FAILED ITEM(S). Review the import log and any partially created items.' : 'IMPORT FINISHED. You can review or reset the import process.');
        if (failed) {
          text('fc_import_failed_box', failed + ' item(s) failed to import, see the red lines below');
          F.show(node('fc_import_failed_box'));
        }
      } catch (error) { text('statuscomment', 'IMPORT HALTED: ' + error.message); }
    }
  };
})(window, document);
