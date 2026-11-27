/* FLEXI_TEST_JOOMLA=<Joomla 6 directory> node tests/native-joomla-dialogs.cjs */
const fs=require('node:fs'), path=require('node:path'), assert=require('node:assert/strict');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const joomla=process.env.FLEXI_TEST_JOOMLA;
if(!joomla)throw Error('Set FLEXI_TEST_JOOMLA to a Joomla 6 installation.');
(async()=>{
 const browser=await chromium.launch({headless:true}), results=[];
 try{
  for(const width of [1440,768,390]){
   const page=await browser.newPage({viewport:{width,height:900},hasTouch:true}), errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   await page.context().route('https://fixture.invalid/**',r=>r.fulfill({contentType:'text/html',body:'<button id="open">Open</button><form id="adminForm"><div id="content" style="display:none"><label for="value">Value</label><input id="value" name="value" value="Preserve me"></div></form><div id="system-message-container"></div>'}));
   await page.goto('https://fixture.invalid/');
   await page.addScriptTag({path:path.join(joomla,'media/system/js/core.js')});
   await page.addScriptTag({type:'module',content:fs.readFileSync(path.join(joomla,'media/system/js/joomla-dialog.js'),'utf8')});
   await page.evaluate(()=>customElements.whenDefined('joomla-dialog'));
   for(const name of ['form-utils.js','flexi-lib.js'])await page.addScriptTag({path:path.join(root,'site/assets/js',name)});
   await page.addStyleTag({path:path.join(joomla,'media/templates/administrator/atum/css/template.css')});
   await page.locator('#open').click();
   for(let i=0;i<2;i++){
    await page.evaluate(() => {
      const content = document.getElementById('content');
      const tooltip = document.createElement('span');
      tooltip.id = 'temporary-tooltip';
      content.after(tooltip);
      if (!document.getElementById('after-content')) {
        const sibling = document.createElement('span');
        sibling.id = 'after-content';
        tooltip.after(sibling);
      }
      window.closeCalls = 0;
      window.handle = fc_showAsDialog(content, 1000, 700, () => { window.closeCalls++; }, {title:'Test <literal>',closeOnEscape:true});
    });
    const dialog=page.locator('joomla-dialog.fc-joomla-dialog dialog[open]');
    await dialog.waitFor();assert.equal(await dialog.locator('h3').innerText(),'Test <literal>');
    assert.equal(await page.locator('#value').evaluate(el=>el.form.id),'adminForm');
    await page.locator('#value').fill('Round trip '+i);
    assert.equal(await page.evaluate(()=>new FormData(document.getElementById('adminForm')).get('value')),'Round trip '+i);
    const bounds=await dialog.boundingBox();assert(bounds.x>=-1 && bounds.x+bounds.width<=width+1);
    // Bootstrap removes the picker's former next sibling when its tooltip fades.
    await page.locator('#temporary-tooltip').evaluate(node => node.remove());
    if (i === 0) await dialog.locator('[data-button-close]').click();
    else await page.keyboard.press('Escape');
    await dialog.waitFor({state:'detached'});
    assert.deepEqual(errors, []);
    await page.locator('#adminForm #content').waitFor({state:'attached'});
    assert.equal(await page.locator('#adminForm #content').count(),1);
    assert.equal(await page.locator('#value').getAttribute('form'),null);
    assert.equal(await page.evaluate(()=>document.activeElement.id),'open');
    assert.deepEqual(await page.evaluate(() => [
      document.getElementById('content').nextElementSibling?.id, window.closeCalls,
      FlexiForm.data(document.body, 'fc_modal_count'), document.body.classList.contains('fc-no-scroll')
    ]), ['after-content', 1, 0, false]);
   }
   await page.evaluate(()=>{window.confirmed=null;FlexiForm.confirm('Delete <literal>?').then(v=>window.confirmed=v);});
   await page.locator('joomla-dialog-confirm').count();
   await page.locator('joomla-dialog.joomla-dialog-confirm [data-button-cancel]').click();
   await page.waitForFunction(()=>window.confirmed===false);
   await page.evaluate(()=>{window.alertClosed=false;FlexiForm.alert('Message <literal>').then(()=>window.alertClosed=true);});
   assert.match(await page.locator('.joomla-dialog-alert').innerText(),/Message <literal>/);
   await page.locator('.joomla-dialog-alert [data-button-ok]').click();
   await page.waitForFunction(()=>window.alertClosed);
   // Keys pressed inside an iframe do not bubble to the parent document.
   await page.locator('#open').focus();
   await page.evaluate(() => { fc_showDialog('/picker', 'fc-picker', 0, 500, 400, null, {closeOnEscape:true}); });
   const picker = page.frameLocator('#fc-picker_frame').locator('#open');
   await picker.focus();
   await picker.evaluate(button => {
    button.addEventListener('keydown', event => { if (event.key === 'Escape') event.preventDefault(); }, {once:true});
   });
   await picker.press('Escape');
   assert.equal(await page.locator('#fc-picker_frame').isVisible(), true);
   await picker.press('Escape');
   await page.locator('#fc-picker_frame').waitFor({state:'detached'});
   assert.equal(await page.evaluate(() => document.activeElement.id), 'open');

   // Async confirmation must cancel the original event, then preserve normal
   // navigation, validation and the clicked submitter on acceptance.
   const submissions=[];
   page.on('request', request => {
    if (request.isNavigationRequest() && request.method() === 'POST' && request.frame().name() === 'result') {
     submissions.push({path: new URL(request.url()).pathname, body: request.postData()});
    }
   });
   await page.evaluate(()=>{
    window.actions=0;
    document.body.insertAdjacentHTML('beforeend',`<button id="action" onclick="return FlexiForm.confirmAction(this, 'Proceed?', () => { window.actions++; })">Action</button>
     <iframe name="result" id="result"></iframe>
     <a id="confirmed-link" target="result" href="/accepted-link" onclick="return FlexiForm.confirmLink(this, 'Follow?', event)">Follow</a>
     <a id="alert-link" target="result" href="/alert-link" onclick="return FlexiForm.alertLink(this, 'Notice', event)">Notice</a>
     <form id="confirm-form" action="/wrong-action" method="post" target="result" onsubmit="return FlexiForm.confirmSubmit(this, 'Submit?', event)">
      <input id="required" name="required" required value="valid"><button id="submitter" name="operation" value="purge" formaction="/confirmed-submit">Submit</button>
     </form>`);
    window.acceptedSubmits = 0;
    document.addEventListener('submit', event => {
     if (event.target.id === 'confirm-form' && !event.defaultPrevented) window.acceptedSubmits++;
    });
   });
   const confirm=page.locator('.joomla-dialog-confirm');
   await page.locator('#action').click();
   await page.locator('#action').dispatchEvent('click');
   assert.equal(await confirm.count(),1);
   await confirm.locator('[data-button-cancel]').click();
   assert.equal(await page.evaluate(()=>window.actions),0);
   await page.locator('#action').click();await confirm.locator('[data-button-ok]').click();
   await page.waitForFunction(()=>window.actions===1);
   await page.locator('#confirmed-link').click();await confirm.locator('[data-button-cancel]').click();
   assert.equal(page.frames().filter(f=>f.url().endsWith('/accepted-link')).length,0);
   await page.locator('#confirmed-link').click();await confirm.locator('[data-button-ok]').click();
   await page.waitForFunction(()=>document.querySelector('#result').contentWindow.location.pathname==='/accepted-link');
   await page.locator('#alert-link').click();await page.locator('.joomla-dialog-alert [data-button-ok]').click();
   await page.waitForFunction(()=>document.querySelector('#result').contentWindow.location.pathname==='/alert-link');
   await page.locator('#confirmed-link').click({modifiers:['Control']});
   const popupReady=page.waitForEvent('popup');await confirm.locator('[data-button-ok]').click();
   const popup=await popupReady;await popup.waitForLoadState();assert(popup.url().endsWith('/accepted-link'));await popup.close();
   await page.locator('#submitter').click();await confirm.locator('[data-button-cancel]').click();assert.equal(submissions.length,0);
   assert.equal(await page.evaluate(() => window.acceptedSubmits), 0);
   await page.locator('#submitter').click();
   await page.locator('#required').evaluate(el=>el.value='');
   await confirm.locator('[data-button-ok]').click();
   assert.equal(submissions.length,0);assert.equal(await confirm.count(),0);
   assert.equal(await page.evaluate(() => window.acceptedSubmits), 0);
   await page.locator('#required').fill('valid');await page.locator('#submitter').click();
   await confirm.locator('[data-button-ok]').click();
   await page.waitForFunction(()=>document.querySelector('#result').contentWindow.location.pathname==='/confirmed-submit');
   assert.equal(await page.evaluate(() => window.acceptedSubmits), 1);
   assert.deepEqual(submissions.map(request => request.path), ['/confirmed-submit']);
   assert.match(submissions[0].body,/operation=purge/);assert.match(submissions[0].body,/required=valid/);
   await page.locator('#confirm-form').evaluate(el=>el.remove());
   if(width===1440){
    await page.addScriptTag({path:path.join(root,'site/assets/js/fcvote.js')});
    await page.evaluate(()=>{
     window.jroot_url_fc='https://fixture.invalid/';
     document.body.insertAdjacentHTML('beforeend','<div id="review-box"><form id="review-form"><input name="content_id" value="42"><button type="button" id="delete-form" class="fcvote_review_form_delete_btn" data-confirm="Delete review?" onclick="fcvote_delete_review(\'review-box\',this.form)">Delete form review</button></form></div><button id="delete-own" data-confirm="Delete own?" data-token="qa_token" onclick="return fcvote_review_delete_own(this,42)">Delete own</button><div class="fcvote-review-item"><button id="trash-review" data-confirm="Trash?" data-token="qa_token" onclick="return fcvote_review_moderate(this,\'trash\',42)">Trash review</button></div>');
    });
    for(const id of ['delete-form','delete-own','trash-review']){
     let requests=0,release;
     const ready=new Promise(resolve=>release=resolve);
     await page.route('https://fixture.invalid/index.php?**',async route=>{requests++;await ready;await route.fulfill({contentType:'application/json',body:JSON.stringify({error:true,html:'<div>Retry allowed</div>',msg:'Retry allowed'})});});
     const button=page.locator('#'+id);
     await button.click();await button.dispatchEvent('click');assert.equal(await confirm.count(),1);
     await confirm.locator('[data-button-cancel]').click();assert.equal(requests,0);
     await button.click();await confirm.locator('[data-button-ok]').click();
     await page.waitForFunction(id=>document.getElementById(id).disabled,id);
     await button.dispatchEvent('click');assert.equal(await confirm.count(),0);
     release();await page.waitForFunction(id=>!document.getElementById(id).disabled,id);assert.equal(requests,1);
     if(id!=='delete-form')await page.locator('.joomla-dialog-alert [data-button-ok]').click();
     await button.click();await confirm.locator('[data-button-cancel]').click();assert.equal(requests,1);
     await page.unroute('https://fixture.invalid/index.php?**');
    }
   }
   await page.route('https://fixture.invalid/bootstrap/**', route => route.fulfill({contentType:'text/javascript',body:fs.readFileSync(path.join(joomla,'media/vendor/bootstrap/js',path.basename(new URL(route.request().url()).pathname)))}));
   await page.evaluate(async () => { await import('/bootstrap/popover.js'); });
   await page.evaluate(() => {
    document.body.insertAdjacentHTML('beforeend','<div style="padding:80px"><button type="button" id="help" class="hasTooltip" title="Helpful text">Help</button><button type="button" id="second-help" class="hasTooltip" title="Another tip">Other help</button><input id="outside"></div>');
    fc_initTooltips(null, document.body);
   });
   const help=page.locator('#help');
   await help.tap();await page.locator('.tooltip.show').waitFor();
   await page.locator('#outside').tap();await page.locator('.tooltip.show').waitFor({state:'detached'});
   // A synthetic hover after a touch scroll must not open another control.
   await page.locator('#second-help').dispatchEvent('mouseover');
   assert.equal(await page.locator('.tooltip.show').count(),0);
   await help.tap();await page.locator('.tooltip.show').waitFor();
   await page.keyboard.press('Escape');await page.locator('.tooltip.show').waitFor({state:'detached'});
   await page.locator('#outside').click();await help.hover();await page.locator('.tooltip.show').waitFor();
   await page.mouse.move(1,1);await page.locator('.tooltip.show').waitFor({state:'detached'});
   await help.focus();await page.locator('.tooltip.show').waitFor();
   await page.locator('#outside').focus();await page.locator('.tooltip.show').waitFor({state:'detached'});
   assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>typeof jQuery),'undefined');
   results.push({width,status:'pass'});await page.close();
  }
 }finally{await browser.close();console.log(JSON.stringify(results,null,2));}
})().catch(e=>{console.error(e);process.exitCode=1;});
