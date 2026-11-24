/* Office files stay in this sandbox. The parent supplies bytes, never an external viewer URL. */
'use strict';
function loadScript(src) {
  return new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = src;
    script.onload = resolve;
    script.onerror = reject;
    document.head.append(script);
  });
}

async function renderWorkbook(file, name, labels) {
  await loadScript('sheetjs/xlsx.core.min.js');
  const maxRows = 200;
  const maxCols = 50;
  const workbook = XLSX.read(await file.arrayBuffer(), {
    type: 'array', sheetRows: maxRows + 1, cellHTML: false, bookVBA: false
  });
  if (!workbook.SheetNames.length) throw new Error();
  const select = document.createElement('select');
  select.setAttribute('aria-label', labels.sheets);
  for (const title of workbook.SheetNames) select.add(new Option(title, title));
  if (select.length > 1) document.querySelector('nav').append(select);
  function show() {
    const sheet = workbook.Sheets[select.value];
    const main = document.querySelector('main');
    main.replaceChildren();
    if (!sheet['!ref']) return;
    const range = XLSX.utils.decode_range(sheet['!ref']);
    const full = XLSX.utils.decode_range(sheet['!fullref'] || sheet['!ref']);
    const table = document.createElement('table');
    table.setAttribute('aria-label', select.value || name);
    for (let r = range.s.r; r <= Math.min(range.e.r, range.s.r + maxRows - 1); ++r) {
      const tr = table.insertRow();
      const th = document.createElement('th');
      th.scope = 'row';
      th.textContent = String(r + 1);
      tr.append(th);
      for (let c = range.s.c; c <= Math.min(range.e.c, range.s.c + maxCols - 1); ++c) {
        const cell = sheet[XLSX.utils.encode_cell({r, c})];
        tr.insertCell().textContent = cell ? XLSX.utils.format_cell(cell) : '';
      }
    }
    main.append(table);
    if (full.e.r - range.s.r >= maxRows || full.e.c - range.s.c >= maxCols) {
      const note = document.createElement('p');
      note.textContent = labels.limit;
      main.append(note);
    }
  }
  select.addEventListener('change', show);
  show();
}

async function receive(event) {
  const labels = event.data;
  if (event.source !== parent || !(labels?.file instanceof Blob) || typeof labels.name !== 'string') return;
  window.removeEventListener('message', receive);
  const message = document.getElementById('message');
  document.documentElement.lang = labels.language || 'en';
  document.title = labels.name;
  message.textContent = labels.loading;
  try {
    if (labels.file.size > 16 * 1024 * 1024) throw new Error();
    if (/\.docx$/i.test(labels.name)) {
      await loadScript('jszip/jszip.min.js');
      await loadScript('docx-preview/docx-preview.min.js');
      await docx.renderAsync(labels.file, document.querySelector('main'), null, {
        renderAltChunks: false, useBase64URL: true, ignoreHeight: true
      });
      for (const link of document.querySelectorAll('main a[href]')) {
        if (!link.getAttribute('href').startsWith('#')) link.removeAttribute('href');
      }
    } else {
      await renderWorkbook(labels.file, labels.name, labels);
    }
    message.textContent = '';
  } catch {
    document.querySelector('main').replaceChildren();
    message.textContent = labels.unavailable;
  }
}
window.addEventListener('message', receive);
