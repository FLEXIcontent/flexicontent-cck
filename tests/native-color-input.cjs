const {chromium}=require('playwright'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true});try{
const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.setContent('<form style="color: rgb(12, 34, 56)"><div id="row"><div data-fc-color-input data-format="rgba"><input type="text" name="color" value="rgba(20, 40, 60, 0.3)"><input type="color"><input type="range" min="0" max="1" step="0.01"></div></div></form>');
await page.addScriptTag({path:path.join(__dirname,'../site/assets/js/color-input.js')});
const value=page.locator('#row input[type=text]'),picker=page.locator('#row input[type=color]'),alpha=page.locator('#row input[type=range]');
assert.equal(await value.inputValue(),'rgba(20, 40, 60, 0.3)');assert.equal(await picker.inputValue(),'#14283c');assert.equal(await alpha.inputValue(),'0.3');
for(const saved of ['transparent','inherit','#abc','rgb(10, 20, 30)','']){await value.fill(saved);assert.equal(await value.inputValue(),saved);await page.evaluate(()=>fc_initColorInputs(document));assert.equal(await value.inputValue(),saved);}
await value.fill('rgba(20, 40, 60, 0.3)');await picker.fill('#aabbcc');assert.equal(await value.inputValue(),'rgba(170, 187, 204, 0.3)');
await alpha.fill('0.75');assert.equal(await value.inputValue(),'rgba(170, 187, 204, 0.75)');
await page.evaluate(()=>{const row=document.getElementById('row').cloneNode(true);row.id='clone';document.querySelector('form').append(row);row.dispatchEvent(new CustomEvent('subform-row-add',{bubbles:true,detail:{row}}));window.changes=0;row.addEventListener('change',e=>{if(e.target.type==='text')changes++});row.dispatchEvent(new CustomEvent('joomla:updated',{bubbles:true}));});
await page.locator('#clone input[type=color]').fill('#112233');assert.equal(await page.locator('#clone input[type=text]').inputValue(),'rgba(17, 34, 51, 0.75)');assert.equal(await page.evaluate(()=>changes),1);assert.equal(await value.inputValue(),'rgba(170, 187, 204, 0.75)');
assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>typeof jQuery),'undefined');console.log('PASS: saved CSS values, alpha, picker edits, cloned rows, repeated initialization, isolated values');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
