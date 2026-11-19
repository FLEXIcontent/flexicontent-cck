'use strict';
const {chromium} = require('playwright'), assert = require('node:assert/strict'), path = require('node:path');
(async () => {
  const browser = await chromium.launch({headless: true});
  try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('https://fixture.invalid/**', route => route.fulfill({contentType: 'text/html', body: '<main></main>'}));
    await page.goto('https://fixture.invalid/');
    const load = file => page.addScriptTag({path: path.join(__dirname, '..', file)});
    await page.evaluate(() => { window.Joomla = {Text: {_: key => key}, getOptions: () => ''}; window.fcfav_toggle_info = 0; });
    await load('site/assets/js/form-utils.js'); await load('site/assets/js/flexi-lib.js');
    await page.evaluate(() => {
      document.body.insertAdjacentHTML('beforeend', `<span class="fcfavs-icon_box"><label class="favlink_item_7 fcfavs-toggle-btn"><input type="checkbox" class="fcfavs-toggle-input" data-fcfav-id="7" data-fcfav-type="item"><span class="fcfavs-heart-border"></span></label><span class="fav_item_title">A &amp; B &lt;img src=x&gt;</span><span class="fav_item_url">/item?id=7&amp;lang=en</span></span>
        <div class="mod_fc_favlist" id="recent"></div><div class="mod_fc_favlist_favblock_template" hidden><label class="fcfavs-delete-btn" onclick="FCFav(__ITEM_ID__, 'item', 0)">Remove</label><div class="fcitem_readon"><a data-href="__ITEM_URL__">__ITEM_TITLE__</a></div></div>
        <div class="mod_fc_favlist" id="second"></div><div class="mod_fc_favlist_favblock_template" hidden><div class="fcitem_readon"><a>__ITEM_TITLE__</a></div></div>`);
    });
    await load('site/assets/js/fcfav.js'); await load('site/librairies/choicesjs/sortable.min.js');
    await load('modules/mod_flexicontent/tmpl_common/js/favlist.js');
    let response = 'login', status = 200;
    await page.route('**/index.php?**', route => route.fulfill({status, contentType: 'text/plain', body: response}));
    await page.locator('.fcfavs-toggle-input').check();
    await page.waitForFunction(() => !document.querySelector('.fcfavs-toggle-input').checked);
    assert.equal(await page.locator('#recent > div').count(), 0);
    response = 'added'; await page.locator('.fcfavs-toggle-input').check();
    await page.locator('#recent .fcfav_item_7').waitFor();
    assert.equal(await page.locator('#recent a').textContent(), 'A & B <img src=x>');
    assert.equal(await page.locator('#recent img').count(), 0);
    assert.equal(await page.locator('#second .fcfav_item_7').count(), 1);
    assert.equal(await page.evaluate(() => !!Sortable.get(document.getElementById('recent'))), true);
    response = 'removed'; await page.locator('#recent .fcfavs-delete-btn').click();
    await page.locator('#recent .fcfav_item_7').waitFor({state: 'detached'});
    assert.equal(await page.locator('#second .fcfav_item_7').count(), 0);
    assert.equal(await page.locator('.fcfavs-toggle-input').isChecked(), false);
    await page.evaluate(() => { Joomla.renderMessages = value => { window.lastMessage = value; }; });
    status = 500; await page.locator('.fcfavs-toggle-input').check();
    await page.waitForFunction(() => window.lastMessage?.error);
    assert.equal(await page.locator('.fcfavs-toggle-input').isChecked(), false);
    assert.equal(await page.locator('#recent > div').count(), 0);
    assert.match(await page.evaluate(() => lastMessage.error[0]), /500/);
    status = 200;
    await page.evaluate(() => {
      const list = fclib_createCookieList('fcfavs_recent'); list.add(8, {id: 8, title: 'Unsafe URL', url: 'javascript:alert(1)'}); fcfavs_list_update(list);
    });
    assert.equal(await page.locator('#recent > div').count(), 0);
    await load('modules/mod_flexicontent/tmpl_common/js/tabs.js');
    await page.evaluate(() => {
      document.body.insertAdjacentHTML('beforeend', '<div id="oneTabs"><a href="#panel">Tab</a></div><div id="two" class="accordion"><div id="open" class="collapse show"></div><div id="closed" class="collapse"></div></div>');
      fclib_setCookie('fc_modules_data', 'null', 1);
      fc_bindModuleTabs({moduleId: 1, group: 'active_tagids_feat', id: 'one', tabs: true});
      fc_bindModuleTabs({moduleId: 2, group: 'active_tagids_std', id: 'two', tabs: false});
      document.querySelector('#oneTabs a').dispatchEvent(new Event('shown.bs.tab', {bubbles:true}));
      document.getElementById('closed').dispatchEvent(new Event('hidden.bs.collapse', {bubbles:true}));
      let cookie = JSON.parse(fclib_getCookie('fc_modules_data'));
      if (cookie[1].active_tagids_feat.one !== 'panel' || cookie[2].active_tagids_std.two !== 'open') throw Error('Module state isolation failed');
      document.getElementById('open').classList.remove('show');
      document.getElementById('open').dispatchEvent(new Event('hidden.bs.collapse', {bubbles:true}));
      if (JSON.parse(fclib_getCookie('fc_modules_data'))[2].active_tagids_std.two !== null) throw Error('Closed accordion not remembered');
    });
    await page.evaluate(() => {
      document.body.innerHTML = '<div id="articleTOC"><ul><li><a class="tocPaginated">First</a></li><li><a class="tocPaginated">Second</a></li><li><a class="tocAll">All</a></li></ul></div><div id="Page-1" class="articlePage active">First content</div><div id="Page-2" class="articlePage">Second content</div><div class="tocNav"><button class="tocPrev" onclick="flexibreak.previous()">Previous</button><span class="tocPrevNextCnt"></span><button class="tocNext" onclick="flexibreak.next()">Next</button></div>';
    });
    await load('plugins/content/flexibreak/flexibreak/flexibreak.js');
    await page.locator('.tocNext').click(); assert.equal(await page.locator('#Page-2').evaluate(n=>n.classList.contains('active')), true);
    assert.equal(await page.locator('.tocPrevNextCnt').textContent(), '[2/2]');
    await page.locator('.tocAll').click(); assert.equal(await page.locator('.articlePage.active').count(), 2);
    await page.locator('.tocPaginated').first().click(); assert.equal(await page.locator('.articlePage.active').count(), 1);
    await page.evaluate(() => { location.hash = '#Page-2'; window.flexibreak = new FlexiBreak('articleTOC'); });
    assert.equal(await page.locator('#Page-2').evaluate(n=>n.classList.contains('active')), true);
    await page.evaluate(() => { flexibreak.options.wrap=true; flexibreak.next(); });
    assert.equal(await page.locator('#Page-1').evaluate(n=>n.classList.contains('active')), true);
    await page.evaluate(() => {
      document.body.innerHTML = '<div id="comparisons" style="width:400px"><div class="fc-juxtapose" id="compare-1" data-mode="vertical" data-showlabels="false"><img src="before.png"><img src="after.png"></div><div class="fc-juxtapose" id="compare-2" data-makeresponsive="false"><img src="before.png"><img src="after.png"></div></div>';
      window.comparisons = [];
      window.juxtapose = {JXSlider: function(selector, images, options) {
        const slider = {selector, images, options, sizes: [], setWrapperDimensions() {this.sizes.push(document.querySelector(selector).style.width);}};
        comparisons.push(slider); options.callback(slider);
      }};
    });
    await load('site/assets/js/juxtapose.js');
    await page.waitForFunction(() => comparisons[0].sizes.length);
    await page.evaluate(() => { document.getElementById('comparisons').style.width='250px'; document.dispatchEvent(new Event('joomla:updated')); });
    await page.waitForFunction(() => comparisons[0].sizes.includes('250px'));
    const comparisons = await page.evaluate(() => window.comparisons.map(s => ({mode:s.options.mode, labels:s.options.showLabels, count:s.images.length, sizes:s.sizes})));
    assert.equal(comparisons.length,2); assert.equal(comparisons[0].mode,'vertical'); assert.equal(comparisons[0].labels,false); assert.equal(comparisons[0].count,2); assert.deepEqual(comparisons[1].sizes,[]);
    assert.equal(await page.evaluate(() => typeof jQuery), 'undefined'); assert.deepEqual(errors, []);
    console.log('PASS: favourite success/failure and two modules, safe cookie rendering, Sortable, Bootstrap state isolation, pagebreak navigation/show-all/hash/wrap without jQuery');
  } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode=1;});
