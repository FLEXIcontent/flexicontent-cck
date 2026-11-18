/* Run with Playwright installed: node tests/native-editor-workflows.cjs */
'use strict';
const {chromium} = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const source = file => fs.readFileSync(path.join(root, file), 'utf8');
(async () => {
  const browser = await chromium.launch({headless: true});
  const results = [];
  async function test(name, run, withJQuery = false) {
    const page = await browser.newPage();
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    try {
      await page.route('https://fixture.invalid/**', route => route.fulfill({contentType: 'text/html', body: '<div id="system-message-container"></div>'}));
      await page.goto('https://fixture.invalid/');
      await page.evaluate(() => {
        window.Joomla = {Text: {_: value => value}, renderMessages: messages => {
          document.getElementById('system-message-container').textContent = JSON.stringify(messages);
        }};
      });
      await page.addScriptTag({content: source('site/assets/js/form-utils.js')});
      if (withJQuery) await page.addScriptTag({path: process.env.FLEXI_TEST_JQUERY});
      await run(page);
      assert.deepEqual(errors, []);
      assert.equal(await page.evaluate(() => typeof window.jQuery), withJQuery ? 'function' : 'undefined');
      results.push({name, status: 'pass'});
    } catch (error) { results.push({name, status: 'fail', message: error.stack}); }
    finally { await page.close(); }
  }
  const form = async (page, fields, attrs = '') => page.evaluate(({fields, attrs}) => {
    document.body.insertAdjacentHTML('beforeend', '<form id="adminForm" action="/save" method="post" class="form-validate" ' + attrs + '><input name="task">' + fields + '</form>');
  }, {fields, attrs});
  const validate = async page => {
    await page.addScriptTag({content: source('site/assets/js/validate.js')});
    await page.waitForFunction(() => !!document.formvalidator);
  };
  await test('Required, optional, disabled and dynamically added controls', async page => {
    await form(page, '<label for="title">Title</label><input id="title" name="title" class="required"><input name="optional" class="validate-email"><fieldset disabled><input class="required" name="inactive"></fieldset>');
    await validate(page);
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    await page.fill('#title', 'An article');
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), true);
    await page.fill('[name=optional]', 'bad-email');
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    await page.fill('[name=optional]', 'editor@example.com');
    await page.evaluate(() => {
      const input = document.createElement('input'); input.name = 'repeated'; input.className = 'required';
      const box = document.createElement('div'); box.id = 'new-repeat'; box.append(input);
      document.forms[0].append(box); document.formvalidator.attachToForm(null, '#new-repeat');
    });
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    await page.fill('[name=repeated]', 'Second value');
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), true);
  });
  await test('Checkbox groups, multi-select limits and category assignment', async page => {
    await form(page, '<fieldset class="fc_input_set"><input type="checkbox" name="boxes[]" value="a" class="required validate-cboxlimitations" data-min_values="2"><input type="checkbox" name="boxes[]" value="b" class="required validate-cboxlimitations" data-min_values="2"></fieldset><select name="jform[catid]" id="jform_catid" class="validate-catid"><option value=""></option><option value="4">Cat</option></select><select name="jform[cid][]" id="jform_cid" multiple class="validate-sellimitations" data-exact_values="1"><option value="4" selected>Cat</option></select>');
    await validate(page);
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    await page.locator('[value=a]').check();
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    await page.locator('[value=b]').check();
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), true);
    assert.equal(await page.inputValue('#jform_catid'), '4');
    await page.selectOption('#jform_cid', []);
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
  });
  await test('Hidden rich text is synchronized before required validation', async page => {
    await form(page, '<div class="fcfield_box required_box"><textarea id="body" name="body" class="mce_editable" style="display:none"></textarea></div>');
    await page.evaluate(() => { window.editorText = ''; window.tinyMCE = {get: () => ({getContent: () => editorText, setContent: value => { editorText = value; }})}; });
    await validate(page);
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    await page.evaluate(() => { editorText = '<p>Written in the editor</p>'; });
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), true);
    assert.equal(await page.inputValue('#body'), '<p>Written in the editor</p>');
  });
  await test('Idle upload queues do not block saving; completed unassigned uploads retain their validation state',async page=>{
    await form(page,'<input class="validate-fcuploader fc_hidden_value" value="" id="upload-status">');
    await validate(page);
    assert.equal(await page.evaluate(()=>document.formvalidator.isValid(document.forms[0])),true);
    await page.fill('#upload-status','done');
    assert.equal(await page.evaluate(()=>document.formvalidator.isValid(document.forms[0])),false);
    await page.fill('#upload-status','uploading');
    assert.equal(await page.evaluate(()=>document.formvalidator.isValid(document.forms[0])),true);
  });
  await test('AJAX serialization submits repeated values, restores disabled state, confirms success', async page => {
    await form(page, '<input name="title" value="Saved"><input name="repeat[]" value="one"><input name="repeat[]" value="two"><input disabled name="disabled" value="excluded"><input name="token" value="1">', 'data-fc_force_serialization="1"');
    await page.evaluate(() => {
      window.confirmed = 0;
      document.forms[0].addEventListener('flexicontent:save-confirmed', () => confirmed++);
      window.fetch = async (url, options) => { window.posted = Object.fromEntries(options.body); return {ok: true, redirected: false, text: async () => '<div class="alert alert-success">Saved</div>'}; };
    });
    await page.addScriptTag({content: source('site/assets/js/admin.js')});
    await page.evaluate(() => Joomla.submitform('items.apply_ajax'));
    await page.waitForFunction(() => confirmed === 1);
    const result = await page.evaluate(() => ({posted, disabled: document.querySelector('[name=disabled]').disabled, active: document.querySelector('[name=title]').disabled, pending: document.forms[0].fc_ajax_saving, artifacts: document.querySelectorAll('[name=fcdata_serialized]').length}));
    const values = JSON.parse(result.posted.fcdata_serialized);
    assert.deepEqual(values.filter(el => el.name === 'repeat[]').map(el => el.value), ['one', 'two']);
    assert(values.some(el => el.name === 'task' && el.value === 'items.apply_ajax'));
    assert(!values.some(el => el.name === 'disabled'));
    assert.equal(result.disabled, true); assert.equal(result.active, false); assert.equal(result.pending, false); assert.equal(result.artifacts, 0);
  });
  await test('AJAX failures preserve entries, prevent duplicate requests and permit retry', async page => {
    await form(page, '<input name="title" value="Unsaved work">');
    await page.evaluate(() => { window.calls = 0; window.fetch = () => { calls++; return new Promise((resolve, reject) => { window.rejectSave = reject; }); }; });
    await page.addScriptTag({content: source('site/assets/js/admin.js')});
    await page.evaluate(() => { Joomla.submitform('items.apply_ajax'); Joomla.submitform('items.apply_ajax'); });
    await page.waitForFunction(() => calls === 1);
    await page.evaluate(() => rejectSave(new Error('Offline')));
    await page.waitForFunction(() => !document.forms[0].fc_ajax_saving);
    assert.equal(await page.inputValue('[name=title]'), 'Unsaved work');
    assert.equal(await page.locator('#adminForm').isVisible(), true);
    await page.evaluate(() => Joomla.submitform('items.apply_ajax'));
    await page.waitForFunction(() => calls === 2);
    await page.evaluate(() => rejectSave(new Error('Offline again')));
    await page.waitForFunction(() => !document.forms[0].fc_ajax_saving);
  });
  await test('Cancelled submission does not serialize or send', async page => {
    await form(page, '<input name="title" value="Keep">', 'data-fc_force_serialization="1"');
    await page.evaluate(() => { window.calls = 0; window.fetch = () => { calls++; }; document.forms[0].addEventListener('submit', event => event.preventDefault()); });
    await page.addScriptTag({content: source('site/assets/js/admin.js')});
    await page.evaluate(() => Joomla.submitform('items.apply_ajax'));
    assert.deepEqual(await page.evaluate(() => [calls, document.querySelector('[name=title]').disabled, document.querySelectorAll('[name=fcdata_serialized]').length]), [0, false, 0]);
  });
  for (const task of ['items.publish', 'items.unpublish', 'items.trash', 'items.remove']) {
    await test('Native toolbar POST for ' + task, async page => {
      await form(page, '<input type="checkbox" name="cid[]" value="27" checked><input name="csrf_test" value="1">');
      await page.addScriptTag({content: source('site/assets/js/admin.js')});
      const request = page.waitForRequest(request => request.url().endsWith('/save') && request.method() === 'POST');
      const navigation = page.waitForURL('https://fixture.invalid/save', {waitUntil: 'load'});
      await page.evaluate(task => Joomla.submitform(task), task);
      const post = new URLSearchParams((await request).postData());
      assert.equal(post.get('task'), task); assert.equal(post.get('cid[]'), '27'); assert.equal(post.get('csrf_test'), '1');
      await navigation;
    });
  }
  await test('Batch mode changes preserve which values will be submitted', async page => {
    await form(page, ['maincat','seccats','prefix','suffix','copynr','language','state','type_id','access','tags'].map(id => '<div id="row_' + id + '"><input name="' + id + '" id="' + id + '"></div>').join('') + '<input type="radio" id="keepseccats0" name="keepseccats" value="0"><input type="radio" id="keepseccats1" name="keepseccats" value="1"><input type="radio" id="keeptags2" name="keeptags" value="2">');
    await page.addScriptTag({content: source('admin/assets/js/batchprocess.js')});
    await page.evaluate(() => copyonly());
    assert.deepEqual(await page.evaluate(() => ['seccats','type_id','access','prefix','state'].map(id => document.getElementById(id).disabled)), [true,true,true,false,false]);
    await page.evaluate(() => moveonly());
    assert.deepEqual(await page.evaluate(() => ['seccats','type_id','access','prefix','state'].map(id => document.getElementById(id).disabled)), [false,false,false,true,false]);
    assert.equal(await page.inputValue('#state'), '');
  });
  if (process.env.FLEXI_TEST_JQUERY) await test('Legacy editor AJAX hooks receive the serialized request and success response', async page => {
    await form(page, '<input name="title" value="Legacy editor">', 'data-fc_force_serialization="1"');
    await page.route('https://fixture.invalid/save', route => route.fulfill({body:'<div class="alert alert-success">Item saved</div>',contentType:'text/html'}));
    await page.evaluate(() => {
      window.hooks=[];
      jQuery(document).ajaxSend((event,xhr,settings) => hooks.push(['send',settings.data]));
      jQuery(document).ajaxSuccess((event,xhr) => hooks.push(['success',xhr.responseText, document.querySelector('[name=title]').disabled, !!document.querySelector('#system-message-container .alert-success')]));
    });
    await page.addScriptTag({content:source('site/assets/js/admin.js')});
    await page.evaluate(() => Joomla.submitform('items.apply_ajax'));
    await page.waitForFunction(() => hooks.length === 2 && !document.forms[0].fc_ajax_saving);
    const hooks=await page.evaluate(() => window.hooks);
    const submitted=JSON.parse(new URLSearchParams(hooks[0][1]).get('fcdata_serialized'));
    assert(submitted.some(row => row.name==='title' && row.value==='Legacy editor'));
    assert(hooks[1][1].includes('alert-success'));
    assert.deepEqual(hooks[1].slice(2),[false,true]);
    assert.equal(await page.locator('#adminForm').isVisible(),true);
  },true);
  await test('Native dialogs preserve form ownership, values, focus and repeated openings', async page => {
    await form(page,'<button type="button" id="open">Open</button><div id="dialog-content" style="display:none"><input name="dialog_value" value="keep"></div>');
    await page.addScriptTag({content:source('site/assets/js/flexi-lib.js')});
    await page.focus('#open');
    await page.evaluate(() => { window.handle=fc_showAsDialog(document.getElementById('dialog-content'),600,400,null,{title:'Edit properties'}); });
    assert.equal(await page.locator('dialog').isVisible(),true);
    assert.equal(await page.evaluate(() => new FormData(document.getElementById('adminForm')).get('dialog_value')),'keep');
    await page.fill('[name=dialog_value]','changed');
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('dialog'));
    assert.equal(await page.inputValue('[name=dialog_value]'),'changed');
    assert.equal(await page.evaluate(() => document.activeElement.id),'open');
    await page.evaluate(() => { fc_showDialog('/frame','test-dialog',false,400,300); fc_showDialog('/frame','test-dialog',false,400,300); });
    assert.equal(await page.locator('dialog').count(),1);
    await page.evaluate(() => fc_closeDialog('test-dialog'));
    assert.equal(await page.locator('dialog').count(),0);
    assert.equal(await page.evaluate(() => document.body.classList.contains('fc-no-scroll')),false);
  });
  if (process.env.FLEXI_TEST_JQUERY) await test('Legacy Select2 stays interactive inside a native properties dialog',async page=>{
    await form(page,'<div id="properties"><select id="legacy"><option value="a">Alpha</option><option value="b">Beta</option></select></div>');
    await page.addStyleTag({content:source('site/librairies/select2/select2.css')});
    await page.addScriptTag({content:source('site/librairies/select2/select2.js')});
    await page.addScriptTag({content:source('site/assets/js/flexi-lib.js')});
    await page.evaluate(()=>{jQuery('#legacy').select2();window.dialogHandle=fc_showAsDialog(document.getElementById('properties'),500,400);jQuery('#legacy').select2('open');});
    await page.locator('#select2-drop .select2-result-label').filter({hasText:'Beta'}).click();
    assert.equal(await page.inputValue('#legacy'),'b');
    await page.evaluate(()=>dialogHandle.close());
    assert.equal(await page.evaluate(()=>jQuery('#legacy').data('select2').body[0]===document.body),true);
  },true);
  await test('HTML dialogs load their scripts in order without jQuery',async page=>{
    await page.route('https://fixture.invalid/dialog-body',route=>route.fulfill({contentType:'text/html',body:'<button id="loaded-button">Run</button><script src="/dialog-script.js"></script><script>document.getElementById("loaded-button").onclick=()=>{window.dialogResult=window.dialogValue+1;};</script>'}));
    await page.route('https://fixture.invalid/dialog-script.js',route=>route.fulfill({contentType:'application/javascript',body:'window.dialogValue=41;'}));
    await page.addScriptTag({content:source('site/assets/js/flexi-lib.js')});
    await page.evaluate(()=>fc_showDialog('/dialog-body','html-dialog',true,500,300));
    await page.waitForFunction(()=>!!document.getElementById('loaded-button')?.onclick);
    await page.click('#loaded-button');assert.equal(await page.evaluate(()=>window.dialogResult),42);
    await page.evaluate(()=>fc_closeDialog('html-dialog'));
  });
  await test('Repeated property values preserve escaping, empty columns, insertions and removal',async page => {
    await page.addStyleTag({content:'.addfield_handle,.delfield_handle {display:inline-block;width:20px;height:20px}'});
    await form(page, '<textarea id="records">a::First; b::_NA_::Last</textarea><div><ul id="list" class="fcrecords_list" data-value_element_id="records" data-is_elements="1" data-record_sep=";" data-props_sep="::" data-props_used="1,1,1" data-prop_widths="20%,40%,40%" data-add_after="1" data-add_before="1"></ul></div>');
    await page.addScriptTag({content:source('site/librairies/choicesjs/sortable.min.js')});
    await page.addScriptTag({content:source('site/assets/js/flexi-lib-form.js')});
    await page.evaluate(() => fcrecord_populate_list_elements(document.getElementById('list')));
    assert.equal(await page.locator('#list > li').count(),2);
    await page.locator('#list > li').first().locator('input').nth(1).fill('Quoted " <b>');
    await page.locator('#list > li').first().locator('input').nth(1).blur();
    assert.equal(await page.inputValue('#records'),'a::Quoted " <b>;\nb::_NA_::Last');
    await page.locator('#list > li').first().locator('.fc_after').click();
    assert.equal(await page.locator('#list > li').count(),3);
    await page.locator('#list > li').nth(1).locator('input').first().fill('inserted');
    await page.locator('#list > li').nth(1).locator('input').first().blur();
    await page.locator('#list > li').last().locator('.delfield_handle').click();
    assert.equal(await page.inputValue('#records'),'a::Quoted " <b>;\ninserted');
    assert.equal(await page.locator('#list b').count(),0);
  });
  await test('Administrator initializers work without optional legacy plugins',async page => {
    await form(page,'<fieldset class="radio btn-group"><input id="off" type="radio" name="state" value="0" checked><label for="off">Off</label><input id="on" type="radio" name="state" value="1"><label for="on">On</label></fieldset><select class="use_select2_lib"><option>A</option></select>');
    await page.addScriptTag({content:source('site/assets/js/flexi-lib.js')});
    await page.evaluate(() => { for (const init of [fc_initBootstrap,fc_initTooltips,fc_initCodeMirror,fc_initMinicolors,fc_initSubform,fc_initSelect2]) init(null,document.forms[0]); });
    assert.equal(await page.locator('label[for=off]').getAttribute('class'),'btn active btn-danger');
    await page.locator('label[for=on]').click();
    assert.equal(await page.locator('label[for=on]').getAttribute('class'),'btn active btn-success');
  });
  await test('Editor tabs with optional tooltip classes initialize without jQuery',async page=>{
    await form(page,'<div id="editor-tabs" class="fctabber"><div id="basic-tab" class="tabbertab"><h3 class="hasTooltip">Basic</h3><input name="basic"></div><div id="content-tab" class="tabbertab"><h3 class="hasPopover">Content</h3><input name="content"></div></div>');
    await page.addScriptTag({content:source('site/assets/js/tabber-minimized.js')});
    await page.evaluate(()=>tabberAutomatic(tabberOptions));
    assert.equal(await page.locator('#editor-tabs .tabbernav a').count(),2);
    await page.locator('#editor-tabs .tabbernav a').last().click();
    assert.equal(await page.locator('#editor-tabs .tabbertab').last().evaluate(el=>el.classList.contains('tabbertabhide')),false);
  });
  await test('Dragging rows updates submitted order and rejects crossing ordering groups',async page=>{
    await page.evaluate(()=>{ document.body.innerHTML='<table><tbody id="sortable_fcitems">'+['a','b','c'].map((id,index)=>'<tr id="'+id+'"><td><div class="fc_drag_handle" style="width:100px;height:50px">Drag '+id+'</div><input name="ord_grp[]" value="'+(index===2?'other':'same')+'"><input name="order[]" value="'+(index*10+10)+'"></td></tr>').join('')+'</tbody></table>'; });
    await page.addScriptTag({content:source('site/librairies/choicesjs/sortable.min.js')});
    await page.addScriptTag({content:source('admin/assets/js/flexi-lib.js')});
    const drag=async(from,to)=>{
      const start=await page.locator('#'+from+' .fc_drag_handle').boundingBox(),end=await page.locator('#'+to+' .fc_drag_handle').boundingBox();
      await page.mouse.move(start.x+20,start.y+20);await page.mouse.down();await page.waitForTimeout(120);
      await page.mouse.move(end.x+20,end.y+end.height-3,{steps:15});await page.waitForTimeout(200);await page.mouse.up();await page.waitForTimeout(150);
    };
    await drag('a','b');
    assert.deepEqual(await page.locator('#sortable_fcitems > tr').evaluateAll(rows=>rows.map(row=>[row.id,row.querySelector('[name="order[]"]').value])),[['b','10'],['a','20'],['c','30']]);
    await drag('a','c');
    assert.deepEqual(await page.locator('#sortable_fcitems > tr').evaluateAll(rows=>rows.map(row=>row.id)),['b','a','c']);
  });
  await test('Batch field cards load inputs and scripts, preserve format validation, and can be removed',async page=>{
    await form(page,'<select id="batch_field_picker"><option value=""></option><option value="45" data-multiple="1">Email field</option></select><div id="batch_field_cards" data-url="/field"></div><template id="batch_field_card_tmpl"><div class="batch-field-card"><span class="batch-field-label"></span><button type="button" class="batch-field-remove">Remove</button><select class="batch-field-mode" disabled><option value="replace">Replace</option><option value="add">Add</option><option value="clear">Clear</option></select><div class="batch-field-body"></div></div></template>');
    await page.route('https://fixture.invalid/field?field_id=45',route=>route.fulfill({contentType:'application/json',body:JSON.stringify({html:'<input name="custom[email]" class="required validate-email" required><script>window.cardScriptLoaded=true;</script>',assets:{}})}));
    await page.addScriptTag({content:source('site/assets/js/flexi-lib.js')});
    await page.addScriptTag({content:source('admin/assets/js/batchprocess.js')});
    await page.selectOption('#batch_field_picker','45');
    await page.waitForFunction(()=>window.cardScriptLoaded && !document.querySelector('.batch-field-mode').disabled);
    assert.equal(await page.locator('[name="custom[email]"]').getAttribute('class'),'validate-email');
    await page.selectOption('.batch-field-mode','clear');
    assert.equal(await page.locator('.batch-field-body').isVisible(),false);
    await page.click('.batch-field-remove');
    assert.equal(await page.locator('.batch-field-card').count(),0);
    assert.equal(await page.locator('#batch_field_picker option[value="45"]').isDisabled(),false);
  });
  await browser.close();
  console.log(JSON.stringify(results, null, 2));
  if (results.some(result => result.status !== 'pass')) process.exitCode = 1;
})().catch(error => { console.error(error); process.exitCode = 1; });
