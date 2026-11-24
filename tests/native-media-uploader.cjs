// Run with Playwright and Chromium installed: node tests/native-media-uploader.cjs
const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');

(async () => {
  const browser = await chromium.launch({headless: true});
  try {
    const page = await browser.newPage();
    const requests = [];
    let failSecondChunk = true;
    const throwable = [];
    page.on('pageerror', error => throwable.push(error.message));
    await page.route('https://fixture.invalid/**', async route => {
      if (route.request().method() !== 'POST') {
        return route.fulfill({contentType: 'text/html', body: `
          <form><section class="fcfieldval_container">
            <button type="button" id="toggle">Upload</button>
            <button type="button" id="properties">Properties</button>
            <div class="fcfield_preview_box">Preview</div>
            <div class="fcimg_value_props" style="display:none">Caption</div>
            <div><div class="fc_uploader_status_box"><input class="validate-fcuploader"></div><div id="fixture_0" style="display:none"></div></div>
          </section></form>`});
      }
      const payload = route.request().postDataBuffer().toString('latin1');
      const field = key => payload.match(new RegExp('name="' + key + '"\\r\\n\\r\\n([^\\r]+)'))?.[1];
      const request = {chunk: Number(field('chunk')), chunks: Number(field('chunks')), uploadId: field('upload_id'), name: field('name')};
      requests.push(request);
      if (request.name === 'rejected.txt') return route.fulfill({status: 415, json: {error: {message: 'Rejected type'}}});
      if (request.chunk === 1 && failSecondChunk) {
        failSecondChunk = false;
        await page.locator('.fc_uploader_status_box').evaluate(box => {
          box.insertAdjacentHTML('afterbegin', '<span class="fc-field-invalid">Please wait</span>');
        });
        return route.fulfill({status: 500, json: {jsonrpc: '2.0', error: {message: 'Temporary error'}}});
      }
      return route.fulfill({json: {jsonrpc: '2.0', data: request.chunk === request.chunks - 1 ? {id: 87, filename: request.name} : null}});
    });
    await page.goto('https://fixture.invalid/');
    await page.evaluate(() => {
      window.Joomla = {Text: {_: key => key}, renderMessages() {}};
      window.assigned = [];
    });
    for (const file of ['fc-upload-transport.js', 'fc-uploader.js']) {
      await page.addScriptTag({path: path.join(__dirname, '..', 'site', 'assets', 'js', file)});
    }
    const sizes = await page.evaluate(async () => {
      const canvas = document.createElement('canvas'); canvas.width = 400; canvas.height = 200;
      canvas.getContext('2d').fillRect(0, 0, 400, 200);
      const file = new File([await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))], 'image.png', {type:'image/png'});
      const result = [];
      for (const upload_method of [0, 1]) {
        const blob = await FlexiUploadTransport.resize(file, {resize_on_upload:1, upload_max_w:80, upload_max_h:80, upload_method, upload_quality:90});
        const bitmap = await createImageBitmap(blob); result.push([bitmap.width, bitmap.height]); bitmap.close();
      }
      return result;
    });
    assert.deepEqual(sizes, [[80, 40], [80, 80]], 'Honor the configured Scale and Crop processing methods');
    await page.evaluate(() => {
      window.owner = new FlexiUploader.Uploader({
        tag_id: 'fixture_', action: '/upload', upload_maxcount: 1, autostart_on_select: true,
        extensions: ['txt'], handle_FileUploaded: (uploader, file, result) => assigned.push(result.data.id),
      });
      document.getElementById('toggle').addEventListener('click', event => owner.toggleUploader(0, false, event.currentTarget));
      document.getElementById('properties').addEventListener('click', event => FlexiUploader.toggleImageProperties(event.currentTarget));
    });
    const input = page.locator('input[type=file]');
    await page.click('#toggle');
    assert.equal(await page.locator('#toggle').getAttribute('aria-expanded'), 'true');
    assert.equal(await page.locator('.fcfield_preview_box').isVisible(), false);
    await page.click('#toggle');
    assert.equal(await input.isVisible(), false);
    assert.equal(await page.locator('.fcfield_preview_box').isVisible(), true);
    await page.click('#properties');
    assert.equal(await page.locator('.fcimg_value_props').isVisible(), true);
    await page.click('#properties');
    assert.equal(await page.locator('.fcimg_value_props').isVisible(), false);
    await page.click('#toggle');
    await input.setInputFiles({name: 'test.txt', mimeType: 'text/plain', buffer: Buffer.alloc(1024 * 1024 + 3, 65)});
    await page.waitForFunction(() => document.querySelector('.fc-native-upload-status').textContent === 'Temporary error');
    assert.deepEqual(await page.evaluate(() => assigned), []);
    assert.equal(await page.locator('.validate-fcuploader').inputValue(), '');
    assert.equal(await page.locator('.fc_uploader_status_box .fc-field-invalid').count(), 0);
    await page.locator('.fc-native-upload-actions .btn-outline-primary').click();
    await page.waitForFunction(() => assigned.length === 1);
    assert.equal(requests.length, 4);
    assert.deepEqual(requests.map(request => request.chunk), [0, 1, 0, 1]);
    assert(requests.every(request => request.chunks === 2 && /^[0-9a-f]{32}$/.test(request.uploadId)));
    assert.equal(requests[0].uploadId, requests[1].uploadId);
    assert.equal(requests[2].uploadId, requests[3].uploadId);
    assert.notEqual(requests[0].uploadId, requests[2].uploadId);
    await input.setInputFiles({name: 'rejected.txt', mimeType: 'text/plain', buffer: Buffer.from('rejected')});
    await page.waitForFunction(() => document.querySelector('.fc-native-upload-status').textContent === 'Rejected type');
    const rejectedRequests = requests.length;
    // A new selection replaces the failed single-file slot without retrying it.
    await input.setInputFiles({name: 'replacement.txt', mimeType: 'text/plain', buffer: Buffer.from('valid')});
    await page.waitForFunction(() => assigned.length === 2);
    assert.equal(requests.length, rejectedRequests + 1);
    assert.equal(requests.at(-1).name, 'replacement.txt');
    assert.equal(await page.locator('.fc-native-upload-message').textContent(), '');
    assert.equal(await page.evaluate(() => typeof window.jQuery), 'undefined');
    await page.evaluate(() => owner.clearUploader(0));
    assert.equal(await input.count(), 0);
    assert.equal(await page.locator('#toggle').getAttribute('aria-expanded'), 'false');

    // A field must keep the selected PDF accessible after its upload callback destroys the queue.
    await page.addStyleTag({path: path.join(__dirname, '..', 'site', 'assets', 'css', 'fc-uploader.css')});
    await page.addStyleTag({path: path.join(__dirname, '..', 'admin', 'assets', 'css', 'j4x.css')});
    await page.emulateMedia({colorScheme: 'dark'});
    for (const css of ['glightbox/dist/css/glightbox.min.css', 'uppy/dist/dashboard.min.css']) {
      await page.addStyleTag({path: path.join(__dirname, '..', 'site', 'librairies', css)});
    }
    for (const asset of ['glightbox/dist/js/glightbox.min.js', 'uppy/dist/dashboard.min.js']) {
      await page.addScriptTag({path: path.join(__dirname, '..', 'site', 'librairies', asset)});
    }
    for (const file of ['form-utils.js', 'media-field-controls.js']) {
      await page.addScriptTag({path: path.join(__dirname, '..', 'site', 'assets', 'js', file)});
    }
    await page.evaluate(() => {
      const container = document.getElementById('fixture_0');
      container.className = 'fc_file_uploader fc_auto_uploader';
      container.dataset.tagidPrefix = 'fixture_';
      const card = container.closest('section');
      card.insertAdjacentHTML('afterbegin', '<input class="fc-file-id" type="hidden" value="17"><input id="file_name" class="fc_filedata_txt" value="previous.pdf"><input class="fc_filedata_title" value="previous.pdf"><button id="upload" type="button" class="fc_files_uploader_toggle_btn" data-rowno="0" aria-haspopup="dialog">Upload</button>');
      document.getElementById('upload').onclick = event => FlexiMediaField.beforeToggle(event.currentTarget, 'file');
      window.fixture_ = new FlexiUploader.Uploader({
        tag_id: 'fixture_', action: '/upload', upload_maxcount: 1, autostart_on_select: true,
        extensions: ['pdf'], handle_FileUploaded: (queue, file, result) =>
          FlexiMediaField.assignFile('file_name', result.data, 0, 'file')
      });
      FlexiMediaField.showUploader('file_name');
    });
    const drop = page.locator('.fc-upload-drop');
    assert.equal(await drop.locator('.fc-upload-cloud').count(), 1);
    assert.equal(await drop.isVisible(), false, 'A saved assignment hides the empty tile');
    await page.locator('#upload').click();
    await page.locator('.fc-upload-dialog[open] .uppy-Dashboard').waitFor();
    await page.keyboard.press('Escape');
    await page.locator('.fc-upload-dialog').waitFor({state: 'detached'});
    assert.equal(await drop.isVisible(), false);
    assert.equal(await page.locator('.fc_filedata_title').inputValue(), 'previous.pdf');
    await page.evaluate(() => FlexiMediaField.clearFile('#file_name', {}, 'file'));
    assert(await drop.isVisible(), 'Clearing the assignment restores the tile');
    await drop.locator('input').focus();
    await page.keyboard.press('Enter');
    const dashboard = page.locator('.fc-upload-dialog[open]');
    assert.equal(await dashboard.locator('[data-uppy-theme]').getAttribute('data-uppy-theme'), 'light');
    const background = el => getComputedStyle(el).backgroundColor;
    assert.equal(await dashboard.locator('.uppy-Dashboard-inner').evaluate(background), await drop.evaluate(background));
    await dashboard.locator('input[type=file]').first().setInputFiles({
      name: 'guide.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4')
    });
    await dashboard.locator('.uppy-Dashboard-Item-action--edit').click();
    const preview = dashboard.locator('iframe.fc-upload-media-preview');
    assert.equal(await preview.getAttribute('title'), 'guide.pdf');
    await dashboard.locator('.uppy-DashboardContent-save').click();
    await dashboard.locator('.uppy-StatusBar-actionBtn--upload').click();
    await dashboard.waitFor({state: 'detached'});
    assert.equal(await drop.isVisible(), false, 'A successful upload hides the empty tile');
    assert(await page.locator('#upload').isVisible(), 'Replace remains available');
    assert.equal(await page.locator('.fc_filedata_title').inputValue(), 'guide.pdf');
    const assignedPreview = page.locator('.fc-upload-result');
    await assignedPreview.click();
    const pdf = page.locator('.glightbox-container iframe[title="guide.pdf"]');
    await pdf.waitFor({state: 'attached'});
    await page.locator('.gslide.current').evaluate(el => Promise.all(el.getAnimations({subtree: true}).map(a => a.finished)));
    for (const viewport of [{width: 1920, height: 960}, {width: 390, height: 844}]) {
      await page.setViewportSize(viewport);
      const frame = await pdf.boundingBox();
      const close = await page.locator('.glightbox-container .gclose').boundingBox();
      assert.equal(frame.x, 0);
      assert.equal(frame.width, viewport.width);
      assert(frame.y >= close.y + close.height && frame.y <= 48);
      assert.equal(frame.y + frame.height, viewport.height);
      assert.equal(await page.locator('.gslide.current').evaluate(el => getComputedStyle(el).borderTopWidth), '0px');
    }
    await page.keyboard.press('Escape');
    await page.locator('.glightbox-container').waitFor({state: 'detached'});
    // Office previews read the selected bytes inside the component's isolated viewer.
    const officePath = '/components/com_flexicontent/librairies/office-preview/';
    await page.route('**' + officePath + '**', route => {
      const asset = new URL(route.request().url()).pathname.slice(officePath.length);
      return route.fulfill({path: path.join(__dirname, '..', 'site', 'librairies', 'office-preview', asset)});
    });
    const sheetjs = require('../site/librairies/office-preview/sheetjs/xlsx.core.min.js');
    const workbook = sheetjs.utils.book_new();
    for (const sheet of ['First', 'Second']) {
      sheetjs.utils.book_append_sheet(workbook, sheetjs.utils.aoa_to_sheet([[sheet + ' worksheet', 27]]), sheet);
    }
    const bytes = sheetjs.write(workbook, {type: 'array', bookType: 'xlsx'});
    await page.evaluate(data => {
      Joomla.getOptions = () => ({root: ''});
      const name = 'sample.xlsx';
      const blob = new Blob([Uint8Array.from(data)]);
      const url = URL.createObjectURL(blob);
      const preview = FlexiUploader.mediaElement(FlexiUploader.mediaType(name), url, name);
      preview.id = 'office-test';
      document.body.append(preview);
    }, Array.from(new Uint8Array(bytes)));
    const office = page.frameLocator('#office-test');
    await office.getByText('First worksheet', {exact: true}).waitFor();
    await office.locator('select').selectOption('Second');
    assert(await office.getByText('Second worksheet', {exact: true}).isVisible());
    assert.equal(await page.locator('#office-test').getAttribute('sandbox'), 'allow-scripts');

    const deck = require('node:fs').readFileSync(path.join(__dirname, 'fixtures', 'office-preview.pptx'));
    await page.evaluate(bytes => {
      const name = 'sample.pptx';
      const url = URL.createObjectURL(new Blob([Uint8Array.from(bytes)]));
      const preview = FlexiUploader.mediaElement(FlexiUploader.mediaType(name), url, name);
      preview.id = 'slides-test';
      document.body.append(preview);
    }, Array.from(deck));
    const slides = page.frameLocator('#slides-test');
    const canvas = slides.locator('canvas');
    await slides.locator('canvas[aria-description*="Coastal field notes"]').waitFor();
    const backgroundPixel = () => canvas.evaluate(el => Array.from(el.getContext('2d').getImageData(3, 3, 1, 1).data));
    assert.deepEqual(await backgroundPixel(), [19, 87, 110, 255]);
    const hasTitleInk = await canvas.evaluate(el => {
      const context = el.getContext('2d');
      const pixels = context.getImageData(0, el.height * .22, el.width, el.height * .42).data;
      for (let i = 0; i < pixels.length; i += 4) {
        if (Math.min(pixels[i], pixels[i + 1], pixels[i + 2]) > 220) return true;
      }
      return false;
    });
    assert(hasTitleInk, 'The white title should be painted over the blue background');
    await slides.locator('select').selectOption('1');
    await slides.locator('canvas[aria-description*="Estuary survey"]').waitFor();
    assert.deepEqual(await backgroundPixel(), [147, 63, 33, 255]);
    await page.locator('#office-test').evaluate(el => el.remove());

    // An assigned Gallery image occupies the empty tile and remains a replacement drop target.
    await page.evaluate(() => {
      const card = document.createElement('section');
      card.className = 'fc-image-card fcfieldval_container';
      card.innerHTML = '<div><div id="gallery_0" data-tagid-prefix="gallery_" class="fc_file_uploader fc_auto_uploader"></div></div><div class="fcfield_preview_box"><button type="button" class="fc-image-preview-button"><img class="fc_preview_thumb"></button></div>';
      document.body.id = 'flexicontent';
      document.body.append(card);
      const picture = card.querySelector('img');
      picture.src = FlexiMediaField.placeholder;
      window.gallery_ = new FlexiUploader.Uploader({
        tag_id: 'gallery_', action: '/upload', upload_maxcount: 1, autostart_on_select: true, extensions: ['png'],
        handle_FileUploaded: (queue, file, result) => {
          picture.src = result.preview;
          FlexiMediaField.clearUploader(card);
        }
      });
      gallery_.toggleUploader(0, 'show');
    });
    const gallery = page.locator('.fc-image-card');
    const picture = gallery.locator('.fc-image-preview-button');
    async function dropImage(target, name) {
      await target.evaluate(async (el, name) => {
        const canvas = document.createElement('canvas');
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        const files = new DataTransfer();
        files.items.add(new File([blob], name, {type: 'image/png'}));
        el.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: files}));
      }, name);
      await dashboard.waitFor({state: 'detached'});
      assert.equal(requests.at(-1).name, name);
    }
    assert(await gallery.locator('.fc-upload-drop').isVisible());
    await dropImage(gallery.locator('.fc-upload-drop'), 'first.png');
    assert(await picture.isVisible());
    assert.equal(await gallery.locator('.fc-upload-drop').isVisible(), false);
    assert.equal(await gallery.locator('.fc-upload-result').count(), 0);
    await dropImage(picture, 'second.png');
    await picture.locator('img').evaluate(el => { el.src = FlexiMediaField.placeholder; });
    assert(await gallery.locator('.fc-upload-drop').isVisible());
    assert.deepEqual(throwable, []);
    console.log('Native controls, upload queue, failed assignment and retry isolation pass without jQuery.');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
