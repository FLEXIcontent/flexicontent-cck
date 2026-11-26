/* Run with Playwright installed: node tests/item-leave-guard.cjs */
const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');

async function run() {
  const browser = await chromium.launch({headless: true});
  try {
    const page = await browser.newPage();
    await page.setContent(`<button id="toolbar">Toolbar</button>
      <form id="adminForm">
        <input name="title" value="Workshop schedule">
        <input type="hidden" name="fcb_video_posters[1][landscape][image]" value="">
      </form>`);
    await page.addScriptTag({path: path.join(__dirname, '../site/assets/js/item-form.js')});
    await page.locator('#toolbar').click();

    const warnings = await page.evaluate(() => {
      const form = document.getElementById('adminForm');
      const poster = form.elements.namedItem('fcb_video_posters[1][landscape][image]');
      const warns = () => !window.dispatchEvent(new Event('beforeunload', {cancelable: true}));
      poster.remove();
      const blankPoster = warns();

      const queue = document.createElement('input');
      queue.type = 'hidden';
      queue.name = 'custom_gallery_uploader_0_count';
      queue.value = '0';
      form.append(queue);
      const emptyQueue = warns();
      queue.value = '1';
      const queuedUpload = warns();
      queue.value = '0';

      poster.value = 'selected.jpg';
      form.append(poster);
      const selectedPoster = warns();
      poster.remove();
      form.elements.title.value = 'Changed';
      const changedTitle = warns();
      form.elements.title.value = 'Workshop schedule';
      return {blankPoster, emptyQueue, queuedUpload, selectedPoster, changedTitle, revertedTitle: warns()};
    });

    assert.deepEqual(warnings, {
      blankPoster: false, emptyQueue: false, queuedUpload: true,
      selectedPoster: true, changedTitle: true, revertedTitle: false,
    });
    console.log('Leave guard passed for idle widgets, real edits, and a reverted edit.');
  } finally {
    await browser.close();
  }
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
