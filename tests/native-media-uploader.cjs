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
      const request = {chunk: Number(field('chunk')), chunks: Number(field('chunks')), uploadId: field('upload_id')};
      requests.push(request);
      if (request.chunk === 1 && failSecondChunk) {
        failSecondChunk = false;
        await page.locator('.fc_uploader_status_box').evaluate(box => {
          box.insertAdjacentHTML('afterbegin', '<span class="fc-field-invalid">Please wait</span>');
        });
        return route.fulfill({status: 500, json: {jsonrpc: '2.0', error: {message: 'Temporary error'}}});
      }
      return route.fulfill({json: {jsonrpc: '2.0', data: request.chunk === 1 ? {id: 87, filename: 'test.txt'} : null}});
    });
    await page.goto('https://fixture.invalid/');
    await page.evaluate(() => {
      window.Joomla = {Text: {_: key => key}, renderMessages() {}};
      window.assigned = [];
    });
    for (const file of ['fc-upload-transport.js', 'fc-uploader.js']) {
      await page.addScriptTag({path: path.join(__dirname, '..', 'site', 'assets', 'js', file)});
    }
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
    assert.equal(await page.evaluate(() => typeof window.jQuery), 'undefined');
    await page.evaluate(() => owner.clearUploader(0));
    assert.equal(await input.count(), 0);
    assert.equal(await page.locator('#toggle').getAttribute('aria-expanded'), 'false');
    assert.deepEqual(throwable, []);
    console.log('Native controls, upload queue, failed assignment and retry isolation pass without jQuery.');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
