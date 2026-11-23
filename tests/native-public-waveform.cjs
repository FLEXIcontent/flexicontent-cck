const assert = require('node:assert/strict');
const {join} = require('node:path');
const {chromium} = require('playwright');

const root = join(__dirname, '..');
const origin = 'http://media-fixture.test';

function tone() {
  const samples = 16000;
  const bytes = Buffer.alloc(44 + 2 * samples);
  bytes.write('RIFF');
  bytes.writeUInt32LE(bytes.length - 8, 4);
  bytes.write('WAVEfmt ', 8);
  bytes.writeUInt32LE(16, 16);
  bytes.writeUInt16LE(1, 20);
  bytes.writeUInt16LE(1, 22);
  bytes.writeUInt32LE(8000, 24);
  bytes.writeUInt32LE(16000, 28);
  bytes.writeUInt16LE(2, 32);
  bytes.writeUInt16LE(16, 34);
  bytes.write('data', 36);
  bytes.writeUInt32LE(samples * 2, 40);
  for (let i = 0; i < samples; i++) bytes.writeInt16LE(Math.round(16000 * Math.sin(i * Math.PI / 10)), 44 + i * 2);
  return bytes;
}

function layout(peaks) {
  return `
    <div id="fc_mediafile_controls_track_0">
      <button class="loadBtn" hidden>Load</button>
      <button class="playBtn">Play</button>
      <button class="pauseBtn" style="display:none">Pause</button>
      <button class="stopBtn" style="display:none">Stop</button>
    </div>
    <div id="fc_mediafile_audio_spectrum_box_track_0" class="fc_mediafile_audio_spectrum_box"
         data-fc_tagid="track-0" data-fc_fname="music">
      <div id="fcview_track-0_file-data-txt" data-wfpreview="tone.wav" data-wfpeaks="${peaks}"></div>
      <div class="fc_mediafile_audio_spectrum_progressbar"><div class="barText"></div><div class="bar"></div></div>
      <div id="fc_mediafile_audio_spectrum_track_0" style="width:400px;height:128px"></div>
    </div>`;
}

async function test() {
  const browser = await chromium.launch({headless: true});
  try {
    for (const peaks of ['valid.json', 'missing.json', '']) {
      const page = await browser.newPage();
      const errors = [];
      const requestedPeaks = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', route => {
        const url = new URL(route.request().url());
        if (url.origin !== origin) return route.abort();
        if (url.pathname === '/') return route.fulfill({contentType: 'text/html', body: layout(peaks)});
        if (url.pathname === '/tracks/tone.wav') return route.fulfill({contentType: 'audio/wav', body: tone()});
        requestedPeaks.push(url.pathname + url.search);
        if (url.pathname === '/tracks/valid.json') return route.fulfill({json: {data: [-1, 1, -0.6, 0.6, -1, 1]}});
        return route.fulfill({status: 404});
      });
      await page.goto(origin);
      for (const file of ['site/assets/js/form-utils.js', 'site/librairies/wavesurfer/wavesurfer.min.js', 'plugins/flexicontent_fields/mediafile/js/view.js']) {
        await page.addScriptTag({path: join(root, file)});
      }
      await page.evaluate(() => {
        audio_spectrum_conf.music = {waveColor: '#246', progressColor: '#48a'};
        fcview_mediafile_base_url.music = '/tracks';
        new fc_Waveform_LazyLoad(document.body);
      });
      await page.waitForFunction(() => window.audio_spectrum_arr.length === 1);
      if (peaks === 'valid.json') {
        await page.waitForFunction(() => audio_spectrum_arr[0].loaded && audio_spectrum_arr[0]._saved_peaks_.length > 2);
      }
      await page.getByRole('button', {name: 'Play'}).click();
      await page.waitForFunction(() => audio_spectrum_arr[0].isPlaying());
      await page.locator('#fc_mediafile_audio_spectrum_track_0').locator('canvas').first().waitFor({state: 'visible'});
      await page.getByRole('button', {name: 'Pause'}).click();
      assert.equal(await page.evaluate(() => audio_spectrum_arr[0].isPlaying()), false);
      assert.equal(await page.evaluate(() => typeof window.jQuery), 'undefined');
      assert.deepEqual(requestedPeaks, peaks ? ['/tracks/' + peaks + '?format=json'] : []);
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log('Passed playback with precomputed peaks, missing peaks and direct decoding');
  } finally {
    await browser.close();
  }
}

test().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
