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
   await page.route('https://fixture.invalid/**',r=>r.fulfill({contentType:'text/html',body:'<button id="open">Open</button><form id="adminForm"><div id="content" style="display:none"><label for="value">Value</label><input id="value" name="value" value="Preserve me"></div></form><div id="system-message-container"></div>'}));
   await page.goto('https://fixture.invalid/');
   await page.addScriptTag({path:path.join(joomla,'media/system/js/core.js')});
   await page.addScriptTag({type:'module',content:fs.readFileSync(path.join(joomla,'media/system/js/joomla-dialog.js'),'utf8')});
   await page.evaluate(()=>customElements.whenDefined('joomla-dialog'));
   for(const name of ['form-utils.js','flexi-lib.js'])await page.addScriptTag({path:path.join(root,'site/assets/js',name)});
   await page.addStyleTag({path:path.join(joomla,'media/templates/administrator/atum/css/template.css')});
   await page.locator('#open').click();
   for(let i=0;i<2;i++){
    await page.evaluate(()=>window.handle=fc_showAsDialog(document.getElementById('content'),1000,700,null,{title:'Test <literal>',closeOnEscape:true}));
    const dialog=page.locator('joomla-dialog.fc-joomla-dialog dialog[open]');
    await dialog.waitFor();assert.equal(await dialog.locator('h3').innerText(),'Test <literal>');
    assert.equal(await page.locator('#value').evaluate(el=>el.form.id),'adminForm');
    await page.locator('#value').fill('Round trip '+i);
    assert.equal(await page.evaluate(()=>new FormData(document.getElementById('adminForm')).get('value')),'Round trip '+i);
    const bounds=await dialog.boundingBox();assert(bounds.x>=-1 && bounds.x+bounds.width<=width+1);
    await page.keyboard.press('Escape');await dialog.waitFor({state:'detached'});
    await page.locator('#adminForm #content').waitFor({state:'attached'});
    assert.equal(await page.locator('#adminForm #content').count(),1);
    assert.equal(await page.locator('#value').getAttribute('form'),null);
    assert.equal(await page.evaluate(()=>document.activeElement.id),'open');
   }
   await page.evaluate(()=>{window.confirmed=null;FlexiForm.confirm('Delete <literal>?').then(v=>window.confirmed=v);});
   await page.locator('joomla-dialog-confirm').count();
   await page.locator('joomla-dialog.joomla-dialog-confirm [data-button-cancel]').click();
   await page.waitForFunction(()=>window.confirmed===false);
   await page.evaluate(()=>{window.alertClosed=false;FlexiForm.alert('Message <literal>').then(()=>window.alertClosed=true);});
   assert.match(await page.locator('.joomla-dialog-alert').innerText(),/Message <literal>/);
   await page.locator('.joomla-dialog-alert [data-button-ok]').click();
   await page.waitForFunction(()=>window.alertClosed);
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
