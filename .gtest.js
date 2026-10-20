const fs = require('fs');
const OUT = '/tmp/gt.out';
const log = (...a) => fs.appendFileSync(OUT, a.join(' ') + '\n');
fs.writeFileSync(OUT, '');

try {
  const { JSDOM } = require('jsdom');
  const dom = new JSDOM('<!DOCTYPE html><html><body><div id="gjs"></div></body></html>', { pretendToBeVisual: true });
  global.window = dom.window;
  global.document = dom.window.document;
  global.navigator = dom.window.navigator;
  global.HTMLElement = dom.window.HTMLElement;
  global.Element = dom.window.Element;
  global.Node = dom.window.Node;
  global.getComputedStyle = dom.window.getComputedStyle.bind(dom.window);
  global.requestAnimationFrame = (cb) => setTimeout(cb, 0);
  global.MutationObserver = dom.window.MutationObserver;
  global.DOMParser = dom.window.DOMParser;
  global.XMLSerializer = dom.window.XMLSerializer;

  const grapesjs = require('grapesjs');
  const editor = grapesjs.init({
    container: '#gjs',
    headless: true,
    fromElement: false,
    storageManager: false,
    height: '80px',
  });

  const ACC = '<div class="accordion" id="fcAccordion"><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#fcAcc1" aria-controls="fcAcc1">A1</button></h2><div id="fcAcc1" class="accordion-collapse collapse show" data-bs-parent="#fcAccordion"><div class="accordion-body">body1</div></div></div></div>';

  editor.setComponents(ACC);
  editor.addComponents(ACC);
  log('=== HTML after adding twice ===');
  log(editor.getHtml());
  log('=== DONE ===');
} catch (e) {
  log('ERR ' + (e && e.stack || e));
}
