/* Browser regressions against the shipped standalone build and FLEXIcontent presets. */
const {chromium}=require('playwright'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true}), results=[];
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.setContent('<form id="adminForm"><input id="masked" class="has_inputmask" name="value"></form><div id="system-message-container"></div>');
  await page.evaluate(()=>window.Joomla={Text:{_:v=>v},renderMessages(){}});
  for(const name of ['site/assets/js/form-utils.js','site/librairies/inputmask/inputmask.min.js','site/assets/js/input-masks.js','site/assets/js/validate.js'])await page.addScriptTag({path:path.join(__dirname,'..',name)});
  const input=page.locator('#masked');
  const setup=async options=>{
   // Each preset represents a separate field; do not carry caret/RTL attributes
   // from a previously masked field into an unrelated alias.
   await page.evaluate(options=>{const old=document.getElementById('masked');old.inputmask?.remove();const n=document.createElement('input');n.id='masked';n.name='value';n.className='has_inputmask';old.replaceWith(n);n.setAttribute('data-inputmask',JSON.stringify(options).slice(1,-1));fc_initInputMasks();},options);
   await input.click();
  };
  const paste=async text=>{await input.focus();await input.press('Control+A');await input.evaluate((n,text)=>{const data=new DataTransfer();data.setData('text/plain',text);n.dispatchEvent(new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}));},text);await input.press('Tab');};
  for(const url of ['https://example.test/path?a=1&b=2','http://example.test','ftp://example.test/file','ftps://example.test/file']){
   await setup({alias:'url'});await input.pressSequentially(url);await input.press('Tab');assert.equal(await input.inputValue(),url);assert.equal(await input.evaluate(n=>n.inputmask.isComplete()),true);
   await paste(url);assert.equal(await input.inputValue(),url);results.push({name:'URL typing and paste '+url.split(':')[0],status:'pass'});
  }
  await setup({alias:'url'});await input.pressSequentially('https://');await input.press('Tab');assert.equal(await page.evaluate(()=>document.formvalidator.isValid(document.getElementById('adminForm'))),false);results.push({name:'Incomplete URL blocks save',status:'pass'});
  await setup({alias:'percentage'});await input.pressSequentially('87.5');await input.press('Tab');assert.equal(await input.inputValue(),'87.5 %');await paste('12.25');assert.equal(await input.inputValue(),'12.25 %');assert.equal(await input.evaluate(n=>n.inputmask.unmaskedvalue()),'12.25');results.push({name:'Percentage retains two decimal places on typing and paste',status:'pass'});
  const cases=[
   [{mask:'9999 999 999'},'0212345678','0212 345 678'],[{alias:'mobile'},'0912345678','0912 345 678'],
   [{alias:'email'},'qa@example.test','qa@example.test'],[{alias:'dd/mm/yyyy'},'20112026','20/11/2026'],
   [{alias:'hh:mm'},'1430','14:30'],[{alias:'integer'},'-42','-42'],[{alias:'decimal'},'12.50','12.50'],
   [{alias:'currency'},'249.95','$ 249.95'],[{alias:'ip'},'192.0.2.20','192.0.2.20'],
   [{mask:'AA-9999'},'QA0246','QA-0246'],[{regex:'FC-[0-9]{3}'},'FC-620','FC-620']
  ];
  for(const[options,value,expected]of cases){await setup(options);await input.pressSequentially(value);await input.press('Tab');assert.equal(await input.inputValue(),expected);assert.equal(await input.evaluate(n=>n.inputmask.isComplete()),true);await paste(expected);assert.equal(await input.inputValue(),expected);results.push({name:'Typing and paste '+(options.alias||options.mask||options.regex),status:'pass'});}
  for(const alias of ['integer','unsigned']){
   await setup({alias});await input.press('Tab');await input.press('Shift+Tab');
   await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
   await input.click();const value=alias==='integer'?'-42':'42';await input.pressSequentially(value);await input.press('Tab');assert.equal(await input.inputValue(),value);
   await paste('0');assert.equal(await input.inputValue(),'0');results.push({name:'Empty '+alias+' survives tab then click without adding a zero',status:'pass'});
  }
  for(const alias of ['currency','currency_euro'])for(const value of ['249.95','-1249.95','0','0.50']){
   await setup({alias});await input.click();await input.press('Control+A');await input.press('Delete');
   await input.pressSequentially(value);await input.press('Tab');
   assert.equal(Number(await input.evaluate(n=>n.inputmask.unmaskedvalue())),Number(value),'Full clear must preserve every currency digit');
   await input.click();await input.press('Control+A');await input.press('Delete');await input.press('Tab');
   assert.equal(await input.evaluate(n=>n.inputmask.unmaskedvalue()),'','Cleared currency remains empty');
   results.push({name:alias+' select-all/Delete/retype '+value,status:'pass'});
  }
  for(const alias of ['currency','currency_euro'])for(const key of ['Delete','Backspace']){
   await setup({alias});await paste('9876.54');
   for(const value of ['249.95','-1249.95','0','0.50']){
    await input.click();await input.press('Control+A');await input.press(key);await input.pressSequentially(value);await input.press('Tab');
    assert.equal(Number(await input.evaluate(n=>n.inputmask.unmaskedvalue())),Number(value),'Replace a populated/reopened currency amount without losing digits');
   }
   results.push({name:alias+' populated select-all/'+key+'/retype',status:'pass'});
  }
  for(const alias of ['currency','decimal','integer','percentage']){
   await setup({alias});await paste('42');await input.click();
   await page.evaluate(()=>{window.clearEvents=[];const n=document.getElementById('masked');for(const type of ['input','change'])n.addEventListener(type,()=>clearEvents.push(type));});
   await input.press('Control+A');await input.press('Delete');await input.press('Tab');
   assert.equal(await input.evaluate(n=>n.inputmask.unmaskedvalue()),'');
   const events=await page.evaluate(()=>clearEvents);assert(events.includes('input')&&events.includes('change'),'A user clear retains normal input/change notifications');
   await paste('42');await input.click();await input.press('End');await input.press('Backspace');await input.press('Tab');
   assert.notEqual(await input.evaluate(n=>n.inputmask.unmaskedvalue()),'','Deleting one character must not clear the complete number');
   results.push({name:alias+' full clear events and partial deletion',status:'pass'});
  }
  for(const[options,value]of [[{alias:'email'},'aaaa@'],[{alias:'dd/mm/yyyy'},'20'],[{alias:'hh:mm'},'14'],[{mask:'AA-9999'},'QA02'],[{regex:'FC-[0-9]{3}'},'FC-6']]){await setup(options);await input.pressSequentially(value);await input.press('Tab');assert.equal(await page.evaluate(()=>document.formvalidator.isValid(document.getElementById('adminForm'))),false);results.push({name:'Reject incomplete '+(options.alias||options.mask||options.regex),status:'pass'});}
  await setup({alias:'url'});assert.equal(await page.evaluate(()=>document.formvalidator.isValid(document.getElementById('adminForm'))),true);results.push({name:'Optional blank mask remains valid',status:'pass'});
  assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>typeof jQuery),'undefined');
 }finally{await browser.close();console.log(JSON.stringify(results,null,2));}
})().catch(e=>{console.error(e);process.exitCode=1;});
