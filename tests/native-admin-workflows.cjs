/* Browser fixtures for version history and remotely loaded layout parameters. */
const assert=require('node:assert/strict'),path=require('node:path'),{chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
(async()=>{const browser=await chromium.launch({headless:true}),results=[];
async function test(name,html,run){const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
try{await page.route('https://fixture.invalid/',r=>r.fulfill({contentType:'text/html',body:html}));await page.goto('https://fixture.invalid/');await page.addScriptTag({path:path.join(root,'site/assets/js/form-utils.js')});await page.evaluate(()=>{window.messages=[];FlexiForm.message=(type,text)=>messages.push({type,text});window.fc_initTooltips=()=>{};});await run(page);assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>typeof jQuery),'undefined');results.push({name,status:'pass'});}finally{await page.close();}}
try{
await test('Items type shortcuts replace a multiple selection and keep the current tab until navigation', `
 <form id="adminForm" method="post" action="/items" target="results">
   <nav id="items_type_tabset"><span aria-current="page">2 Types</span>
     <button type="button" value="3" onclick="selectItemTypeFilter(this)">Article</button>
     <button type="button" value="" onclick="selectItemTypeFilter(this)">All</button>
   </nav>
   <select id="filter_type" name="filter_type[]" multiple>
     <option selected>1</option><option selected>2</option><option>3</option>
   </select>
   <input name="limitstart" value="20"><input type="checkbox" checked id="filter_assockey" name="filter_assockey" value="group">
 </form><iframe name="results"></iframe>`, async page => {
 const source = require('node:fs').readFileSync(path.join(root, 'admin/views/items/tmpl/default.php'), 'utf8');
 await page.evaluate(() => { window.Joomla = { Text: { _: () => 'Submitting the form' } }; });
 await page.addScriptTag({path: path.join(root, 'site/assets/js/admin.js')});
 await page.addScriptTag({content: source.slice(source.indexOf('function delFilter(name)'), source.indexOf('function toggleItemAssignments'))});
 const submissions = [];
 await page.route('**/items', async route => {
   submissions.push(new URLSearchParams(route.request().postData()));
   await route.fulfill({contentType:'text/html', body:'Results'});
 });
 for (const label of ['Article', 'All']) {
   const button = page.getByRole('button', {name:label, exact:true});
   const request = page.waitForResponse('**/items');
   await button.focus();
   await page.keyboard.press('Enter');
   await request;
   assert.deepEqual(submissions.at(-1).getAll('filter_type[]'), label === 'All' ? [] : ['3']);
   assert.equal(submissions.at(-1).get('limitstart'), '0');
   assert.equal(submissions.at(-1).has('filter_assockey'), false);
   assert.equal(await page.locator('[aria-current="page"]').textContent(), '2 Types');
   assert.deepEqual(await page.locator('#filter_type').evaluate(el => [...el.selectedOptions].map(o => o.value)), ['1', '2']);
   assert.equal(await page.locator('nav').evaluate(nav => nav.inert), true);
   assert.equal(await page.locator('.fc_blocker_mssg').textContent(), 'Submitting the form');
   await page.locator('nav button').first().evaluate(el => el.click());
   await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted:true})));
   assert.deepEqual(await page.locator('#filter_type').evaluate(el => [...el.selectedOptions].map(o => o.value)), ['1', '2']);
   assert.equal(await page.locator('nav').evaluate(nav => nav.inert), false);
   assert.equal(await page.locator('#filter_assockey').isChecked(), true);
 }
 assert.equal(submissions.length, 2);
});
const parentForm='<form id="adminForm"><input name="task"><input name="newstate"><input name="boxchecked" value="1"></form>';
await test('Builder form storage round-trips current textarea and hidden input values',
 '<form id="builder"><textarea id="layout_html" name="html">Old HTML</textarea><textarea id="layout_css" name="css">Old CSS</textarea><input id="layout_components" name="components" type="hidden" value="[]"><input id="layout_styles" name="styles" type="hidden" value="[]"><input id="layout_assets" name="assets" type="hidden" value="[]"></form>',async page=>{
 const source=require('node:fs').readFileSync(path.join(root,'site/helpers/html/fclayoutbuilder.php'),'utf8');
 // Execute the authored form-storage adapter and save command from the PHP
 // single-quoted script, with only the unrelated canvas engine substituted.
 const decode=s=>s.replace(/\\([\\'])/g,'$1');
 const start=source.indexOf("editor.StorageManager.add(\\'form-storage\\', {");
 const end=source.indexOf('\n\t\t\t});',start);
 const saveStart=source.indexOf("pn.addButton(\\'options\\', [{",source.indexOf('// Add to default Buttons panel'));
 const saveEnd=source.indexOf('\n\t\t\t}]);',saveStart);
 assert(start>0&&end>start&&saveStart>0&&saveEnd>saveStart);
 await page.evaluate(()=>{
  window.element_id='layout';window.editor_sfx='test';
  window.editor={StorageManager:{add:(name,adapter)=>window.storage=adapter},getHtml:()=>'<p>Builder & source</p>',getCss:()=>'.sample { color: red; }',getStyle:()=>[{selectors:['sample'],style:{color:'red'}}],getComponents:()=>[{tagName:'p',content:'Builder & source'}],AssetManager:{getAll:()=>[]}};
  window.pn={addButton:(name,buttons)=>window.saveBuilder=buttons[0].command};
 });
 await page.addScriptTag({content:decode(source.slice(start,end+7))});
 await page.addScriptTag({content:decode(source.slice(saveStart,saveEnd+8))});
 await page.locator('#layout_html').fill('<p>Edited & current</p>');
 await page.locator('#layout_components').evaluate(n=>n.value='[{"content":"Current"}]');
 const loaded=await page.evaluate(()=>new Promise(resolve=>storage.load(['fc-gjs-testhtml','fc-gjs-testcomponents'],resolve)));
 assert.equal(loaded['fc-gjs-testhtml'],'<p>Edited & current</p>');assert.equal(loaded['fc-gjs-testcomponents'],'[{"content":"Current"}]');
 await page.evaluate(()=>new Promise(resolve=>storage.store({'fc-gjs-testhtml':'<p>Stored & current</p>','fc-gjs-testcomponents':'[{"content":"Stored"}]'},resolve)));
 assert.deepEqual(await page.evaluate(()=>[new FormData(document.querySelector('form')).get('html'),new FormData(document.querySelector('form')).get('components')]),['<p>Stored & current</p>','[{"content":"Stored"}]']);
 await page.evaluate(()=>saveBuilder(editor,null));
 assert.deepEqual(await page.evaluate(()=>Object.fromEntries(new FormData(document.querySelector('form')))),{html:'<p>Builder & source</p>',css:'.sample { color: red; }',components:'[{"tagName":"p","content":"Builder & source"}]',styles:'[{"selectors":["sample"],"style":{"color":"red"}}]',assets:'[]'});
});
await test('File preview handles images, text, clearing and stale asynchronous reads','<input type="file" id="file"><img id="preview" style="display:none"><div id="message"></div>',async page=>{
 await page.evaluate(()=>{window.Joomla={Text:{_:k=>k},JText:{_:k=>k}};window.readers=[];window.FileReader=class{constructor(){readers.push(this)}readAsDataURL(){}};});await page.addScriptTag({path:path.join(root,'site/assets/js/flexi-lib.js')});
 const file=page.locator('#file'),preview=page.locator('#preview');const select=async(name,mimeType)=>{await file.setInputFiles({name,mimeType,buffer:Buffer.from('test')});await page.evaluate(()=>fc_loadImagePreview('file','preview','message',100,'25%'));};
 await select('first.png','image/png');await select('second.png','image/png');await page.evaluate(()=>{readers[1].onload({target:{result:'data:image/png;base64,c2Vjb25k'}});readers[0].onload({target:{result:'data:image/png;base64,Zmlyc3Q='}})});assert.match(await preview.getAttribute('src'),/c2Vjb25k$/);assert.equal(await preview.evaluate(n=>n.style.width),'100px');assert.equal(await preview.evaluate(n=>n.style.height),'25%');
 await select('document.txt','text/plain');assert.equal(await preview.evaluate(n=>n.style.display),'none');await select('last.png','image/png');await file.setInputFiles([]);await page.evaluate(()=>{fc_loadImagePreview('file','preview','message',100,0);readers[2].onload({target:{result:'data:image/png;base64,bGFzdA=='}})});assert.equal(await preview.evaluate(n=>n.style.display),'none');assert.equal(await page.locator('#message').textContent(),'');
});
async function loadSubmit(page){await page.evaluate(()=>{window.Joomla={Text:{_:key=>key},JText:{_:key=>key}};window.submissions=[];document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();submissions.push(Object.fromEntries(new FormData(e.target)))});});await page.addScriptTag({path:path.join(root,'site/assets/js/flexi-lib.js')});await page.addScriptTag({path:path.join(root,'site/assets/js/admin.js')});}
await test('Dialog parent submission creates one native progress overlay and preserves fields',parentForm,async page=>{
 await loadSubmit(page);await page.evaluate(()=>{fc_parent_form_submit('absent','adminForm',{task:'items.changestate',newstate:'T'},{is_list:true});fc_parent_form_submit('absent','adminForm',{task:'items.changestate',newstate:'P'},{is_list:true});});
 assert.deepEqual(await page.evaluate(()=>submissions.map(s=>[s.task,s.newstate])),[['items.changestate','T'],['items.changestate','P']]);assert.equal(await page.locator('#fc_filter_form_blocker').count(),1);assert.equal(await page.locator('.fc_blocker_mssg').innerText(),'FLEXI_LOADING');assert.equal(await page.locator('.fc_blocker_bar > div').evaluate(n=>n.style.width),'95%');assert.equal(await page.locator('input[type=submit]').count(),0);
});
await test('Dialog parent submission alerts on empty selection and respects disabled animation',parentForm,async page=>{
 await loadSubmit(page);await page.evaluate(()=>{window.alerts=[];FlexiForm.alert=text=>alerts.push(text);const form=document.getElementById('adminForm');form.boxchecked.value='0';fc_parent_form_submit('absent','adminForm',{},{is_list:true});form.boxchecked.value='1';form.doanimated_submit=0;fc_parent_form_submit('absent','adminForm',{task:'items.changestate'},{is_list:true});});assert.deepEqual(await page.evaluate(()=>alerts),['FLEXI_NO_ITEMS_SELECTED']);assert.equal(await page.evaluate(()=>submissions.length),1);assert.equal(await page.locator('#fc_filter_form_blocker').count(),0);
});
const panel='<div data-fc-versions-panel data-current-page="1" data-pagecount="3" data-ajax-url="/versions?item=5&amp;token=1" data-confirm-restore="Restore?"><div data-fc-versions-table><a class="fc-version-keep" data-keep-url="/keep?item=5&amp;version=1&amp;token=1" href="#">Keep</a></div><div id="fc_pager"></div></div>';
await test('History pages preserve request parameters and refresh keep controls',panel,async page=>{
 const requests=[];await page.route('**/versions?**',r=>{requests.push(new URL(r.request().url()));return r.fulfill({body:'<a class="fc-version-keep" data-keep-url="/keep?token=1" href="#">Keep</a><span>Loaded</span>'})});await page.route('**/keep?**',r=>r.fulfill({contentType:'application/json',body:'{"success":true}'}));
 await page.addScriptTag({path:path.join(root,'admin/assets/js/flexi_versions.js')});await page.getByRole('button',{name:'2',exact:true}).click();await page.waitForFunction(()=>document.querySelector('[aria-current="page"]')?.textContent==='2');await page.locator('.fc-version-keep').click();await page.waitForFunction(()=>!document.querySelector('[aria-busy]'));assert.equal(requests.length,2);assert(requests.every(u=>u.searchParams.get('page')==='2'&&u.searchParams.get('item')==='5'&&u.searchParams.get('token')==='1'));
});
await test('Failed history loads retain the current page and report an error',panel,async page=>{
 await page.route('**/versions?**',r=>r.fulfill({status:500,body:'failed'}));await page.addScriptTag({path:path.join(root,'admin/assets/js/flexi_versions.js')});await page.getByRole('button',{name:'2',exact:true}).click();await page.waitForFunction(()=>messages.length===1);assert.equal(await page.locator('[aria-current=page]').innerText(),'1');assert.equal(await page.locator('.fc-version-keep').count(),1);assert.equal(await page.locator('[aria-busy]').count(),0);
});
const layout='<ul><li><a href="#attrib-layout">Layout</a></li></ul><div id="attrib-layout">Original</div><select id="layout"><option value="slow" data-filename="slow.php">Slow</option><option value="fast" data-filename="fast.php">Fast</option><option value="bad">Bad</option></select>';
await test('Layout requests abort stale selections and keep the latest form',layout,async page=>{
 await page.evaluate(()=>{window.fc_initDynamicLayoutJsCss=(id,events,html)=>{document.getElementById(id).innerHTML=html;};window.options={remote:true,url:'/params?module=111&token=1',container:'layout',stripPrefix:'',label:'Layout'};});
 await page.route('**/params?**',async r=>{const u=new URL(r.request().url());assert.equal(u.searchParams.get('module'),'111');assert.equal(u.searchParams.get('token'),'1');if(u.searchParams.get('layout_name')==='slow.php')await new Promise(resolve=>setTimeout(resolve,150));await r.fulfill({body:'<input value="'+u.searchParams.get('layout_name')+'">'}).catch(()=>{});});
 await page.addScriptTag({path:path.join(root,'site/assets/js/layout-params.js')});await page.evaluate(()=>{const select=document.getElementById('layout');fc_loadLayoutParams(select,false,options);select.value='fast';return fc_loadLayoutParams(select,false,options);});await page.waitForTimeout(200);assert.equal(await page.locator('#attrib-layout input').inputValue(),'fast.php');assert.equal(await page.locator('[aria-busy]').count(),0);
});
await test('Layout HTTP failure preserves the existing form and permits retry',layout,async page=>{
 await page.evaluate(()=>{window.fc_initDynamicLayoutJsCss=(id,events,html)=>{document.getElementById(id).innerHTML=html;};window.options={remote:true,url:'/params',container:'layout',stripPrefix:'',label:'Layout'};});let calls=0;await page.route('**/params?**',r=>r.fulfill(++calls===1?{status:500,body:'Failure'}:{body:'<input value="Recovered">'}));await page.addScriptTag({path:path.join(root,'site/assets/js/layout-params.js')});await page.evaluate(()=>fc_loadLayoutParams(document.getElementById('layout'),true,options));assert.equal(await page.locator('#attrib-layout').innerText(),'Original');assert.equal(await page.evaluate(()=>messages.length),1);await page.evaluate(()=>fc_loadLayoutParams(document.getElementById('layout'),true,options));assert.equal(await page.locator('#attrib-layout input').inputValue(),'Recovered');
});
}finally{await browser.close();console.log(JSON.stringify(results,null,2));}})().catch(e=>{console.error(e);process.exitCode=1});
