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
  const loadDependencies = page => page.addScriptTag({content: source('site/assets/js/flexi-lib.js')});
  await test('Image card panels preserve URLs and delayed uploaders open once', async page => {
    await form(page, '<div class="fcfieldval_container"><div id="custom_photo_0_fc_preview_msg"></div><div class="fcfield_preview_box">Preview</div><div id="upload_0" class="fc_file_uploader" data-tagid-prefix="upload_" style="display:none"></div><button type="button" class="fc_files_uploader_toggle_btn" onclick="window.uploadClicks++; document.getElementById(\'upload_0\').style.display=\'block\'">Upload</button><button type="button" id="url-button" class="fcfield-mediaurl-btn" data-for="custom_photo_0" onclick="fcfield_image.toggleMediaURL(\'custom_photo_0\')">URL</button><button type="button" id="edit-button" class="fc-image-edit-btn" data-for="custom_photo_0" onclick="fcfield_image.toggleFields(\'custom_photo_0\')">Edit</button><div class="fcimg_url_props" style="display:none"><div class="fcfield-image-mediaurl-box"><input id="custom_photo_0_mediaurl" value="https://example.test/video"></div></div><div class="fcimg_value_props" data-name="custom_photo_0" style="display:none"><input value="Caption"></div></div>');
    await page.evaluate(()=>{const input=document.createElement('input');input.id='custom_photo_0';input.type='hidden';document.querySelector('.fcfieldval_container').append(input);});
    for(const file of ['site/assets/js/media-field-controls.js','site/assets/js/media-metadata.js','plugins/flexicontent_fields/image/js/form.js'])await page.addScriptTag({content:source(file)});
    await page.evaluate(()=>{window.uploadClicks=0;fcfield_image.showUploader('photo_0');fcfield_image.showUploader('photo_0');window.setTimeout(()=>window.upload_={},100);});
    await page.waitForFunction(()=>window.uploadClicks===1);
    await page.locator('#url-button').click();assert.equal(await page.locator('#upload_0').isVisible(),false);assert(await page.locator('.fcimg_url_props').isVisible());
    await page.locator('#url-button').click();assert.equal(await page.locator('.fcimg_url_props').isVisible(),false);assert.equal(await page.locator('#custom_photo_0_mediaurl').inputValue(),'https://example.test/video');
    await page.locator('#edit-button').focus();await page.keyboard.press('Enter');assert(await page.locator('.fcimg_value_props').isVisible());assert.equal(await page.locator('#edit-button').getAttribute('aria-expanded'),'true');
    await page.keyboard.press('Space');assert.equal(await page.locator('.fcimg_value_props').isVisible(),false);
  });
  await test('Validation summary excludes inline error text from wrapping labels', async page => {
    await form(page, '<label for="email">Address<span class="form-control-feedback">This value is not valid</span><input id="email" name="email" class="validate-email" value="aaaa@" data-error-mssg="This value is not valid"></label>');
    await validate(page);
    for (let attempt = 0; attempt < 2; attempt++) {
      assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms.adminForm)), false);
      const summary = JSON.parse(await page.locator('#system-message-container').textContent());
      assert.deepEqual(summary.error, ['JLIB_FORM_FIELD_INVALIDAddress']);
      assert.equal(await page.locator('label .fc-field-invalid').count(), 1);
    }
  });
  await test('Native field groups keep paired values and restore an empty last row', async page => {
    const row = (index, left, right) => `<li class="fc-fieldgroup-collapsed" style="min-height:65px">
      <button type="button" class="fcfield-drag-handle">Drag</button>
      <button type="button" class="fcfield-delvalue" onclick="group.remove(this)">Remove</button>
      <button type="button" class="fc-toggle-group-down" onclick="fcfield_group.toggleDetails(this, true)">Expand</button>
      <button type="button" class="fc-toggle-group-up" onclick="fcfield_group.toggleDetails(this, false)" hidden>Collapse</button>
      <div class="control-fc_subgroup fcAlwaysVisibleField">
        <label class="fc_sub_label" for="custom_left_${index}">Name</label>
        <div class="fcfieldval_container_77"><input class="fcfield_textval" id="custom_left_${index}" name="custom[left][${index}]" value="${left}"></div>
      </div>
      <div class="control-fc_subgroup" style="display:none" data-showon-pattern='[{"field":"custom[left][__n__]","values":["show"],"sign":"="}]'>
        <label class="fc_sub_label" for="custom_right_${index}">Detail</label>
        <div class="fcfieldval_container_78"><input class="fcfield_textval" id="custom_right_${index}" name="custom[right][${index}]" value="${right}"></div>
      </div></li>`;
    await form(page, `<div id="sortables_outer_76" class="fc-fieldgroup-values"><ul class="fcfield-group" id="sortables_76">${row(0, 'Red', 'One')}${row(1, 'Blue', 'Two')}</ul></div>
      <button type="button" id="add-group" onclick="group.add(null, {scroll_visible:false, animate_visible:false})">Add group</button>`);
    await page.addStyleTag({content: source('plugins/flexicontent_fields/fieldgroup/css/form.css')});
    await loadDependencies(page);
    await validate(page);
    await page.addScriptTag({content: source('site/librairies/choicesjs/sortable.min.js')});
    await page.addScriptTag({content: source('site/assets/js/field-repeater.js')});
    await page.addScriptTag({content: source('plugins/flexicontent_fields/text/js/form.js')});
    await page.addScriptTag({content: source('plugins/flexicontent_fields/fieldgroup/js/form.js')});
    await page.evaluate(() => {
      Joomla.Showon = { initialise(row) { window.lastShowon = JSON.parse(row.querySelector('[data-showon]').dataset.showon); } };
      for (const [id, field] of [[77, 'left'], [78, 'right']]) {
        const child = FlexiFieldRepeater.get({fieldId:id, initialCount:2, id:'custom_' + field, name:'custom[' + field + ']', defaultValue:'', max:0}, fcfield_text);
        window['addField' + id] = (_, row, element, options) => child.add(null, element, options);
        window['deleteField' + id] = (_, row, element) => child.remove(null, element);
      }
      window.group = fcfield_group.repeater({id:76, initialCount:2, max:3, fields:[{id:77,name:'left'},{id:78,name:'right'}]});
    });
    const rows = page.locator('#sortables_76 > li');
    await rows.first().getByRole('button', {name:'Expand'}).click();
    assert.equal(await rows.first().locator('#custom_right_0').isVisible(), false);
    await rows.first().getByRole('button', {name:'Collapse'}).click();
    await page.getByRole('button', {name:'Add group'}).click();
    assert.equal(await rows.count(), 3);
    assert.equal(await page.locator('label[for="custom_left_2"]').count(), 1);
    assert.equal(await page.evaluate(() => lastShowon[0].field), 'custom[left][2]');
    await page.locator('#custom_left_2').fill('Green');
    await page.locator('#custom_right_2').evaluate(input => { input.value = 'Three'; });
    const active = await rows.last().getByRole('button', {name:'Drag'}).boundingBox();
    const destination = await rows.nth(1).boundingBox();
    await page.mouse.move(active.x + active.width/2, active.y + active.height/2);
    await page.mouse.down();
    await page.mouse.move(active.x + active.width/2, destination.y + destination.height/4, {steps:18});
    await page.mouse.up();
    const values = () => page.evaluate(() => [...new FormData(document.getElementById('adminForm'))].filter(([name]) => name.startsWith('custom[')).map(([,value]) => value));
    assert.deepEqual(await values(), ['Red','One','Green','Three','Blue','Two']);
    const dialog = page.waitForEvent('dialog');
    const limit = page.getByRole('button', {name:'Add group'}).click();
    assert.match((await dialog).message(), /3$/);
    await (await dialog).accept();
    await limit;
    assert.equal(await rows.count(), 3);
    await rows.first().getByRole('button', {name:'Remove'}).click();
    await rows.first().getByRole('button', {name:'Remove'}).click();
    await rows.first().getByRole('button', {name:'Remove'}).click();
    assert.equal(await rows.count(), 1);
    assert.deepEqual(await values(), ['', '']);
    assert.equal(await page.evaluate(() => lastShowon[0].field), 'custom[left][3]');
    assert.equal(await page.locator('[name^="_duplicated_"]').count(), 0);
  });
  if (process.env.FLEXI_TEST_JQUERY) await test('Field groups pass jQuery collections to legacy child hooks', async page => {
    await form(page, '<ul id="sortables_80"><li><div class="fcfieldval_container_81"><input name="custom[email][0]" id="custom_email_0" value="first@example.test"></div><button type="button" class="remove">Remove</button></li></ul>');
    await loadDependencies(page);
    await page.addScriptTag({content: source('plugins/flexicontent_fields/fieldgroup/js/form.js')});
    const result = await page.evaluate(() => {
      const config = {id:80, initialCount:1, max:0, fields:[{id:81,name:'email'}]};
      const calls = [];
      window.addField81 = (_, row, child, options) => {
        calls.push([!!row.jquery, !!child.jquery, options.remove_previous]);
        const input = child.find('input');
        input.attr({name:'custom[email][1]', id:'custom_email_1'}).val('');
      };
      window.deleteField81 = (_, row, child) => calls.push([!!row.jquery, !!child.jquery]);
      const group = fcfield_group.repeater(config);
      const row = group.add(null, {scroll_visible:false,animate_visible:false});
      group.remove(row.querySelector('.remove'));
      window.addField81 = () => 'cancel';
      const cancelled = group.add(null, {scroll_visible:false,animate_visible:false});
      return {
        calls, cancelled,
        rowCount: document.querySelector('#sortables_80').children.length,
        remaining:document.querySelector('input[name="custom[email][0]"]').value
      };
    });
    assert.deepEqual(result, {calls:[[true,true,2],[true,true]], cancelled:'cancel', rowCount:1, remaining:'first@example.test'});
  }, true);
  await test('Native tag picker handles quick tags, keyboard selection, creation and retries', async page => {
    await form(page, '<select id="jform_language"><option value="en-GB">English</option><option value="fr-FR">French</option></select><div id="tags"><input id="input-tags"></div><div id="fc_tagbox"><ul id="ultagbox"></ul><input type="checkbox" id="quick-tag-5" name="jform[tag][]" value="5"></div>');
    await page.addStyleTag({content: source('site/assets/css/flexicontent.css')});
    await page.addStyleTag({content: source('site/assets/css/tag-picker.css')});
    let delayed;
    await page.route(/\/tags\?/, async route => {
      assert.equal(route.request().headers()['x-requested-with'], 'XMLHttpRequest');
      const request = new URL(route.request().url());
      const term = request.searchParams.get('q');
      const language = request.searchParams.get('item_lang');
      if (term === 'slow') {
        await new Promise(resolve => { delayed = resolve; });
        await route.fulfill({json:[{id: 9, name: 'Outdated'}]}).catch(() => {});
        return;
      }
      const records = [{id: 5, name: 'Quick'}, {id: 6, name: '<Sample>', translated_text: language === 'fr-FR' ? 'Échantillon' : ''}];
      await route.fulfill({json: term ? records.filter(item => item.name.toLowerCase().includes(term.toLowerCase())) : records});
    });
    const posted = [];
    await page.route('https://fixture.invalid/assign', route => {
      assert.equal(route.request().method(), 'POST');
      const name = new URLSearchParams(route.request().postData()).get('name');
      posted.push(name);
      return route.fulfill({body: posted.length === 1 ? '0|Creation denied' : '8|New | tag'});
    });
    await page.addScriptTag({content: source('site/assets/js/tag-picker.js')});
    await page.evaluate(() => fc_initTagPicker({
      name: 'jform[tag][]', suggestions: '/tags?task=viewtags', assign: '/assign', keepOpen: true, itemLanguage: true,
      removeLabel: 'Remove tag', requestError: 'Lookup failed', assignError: 'Assignment failed'
    }));
    const input = page.locator('#input-tags');
    await input.focus();
    const options = page.getByRole('option', {name: 'Quick'});
    await options.waitFor();
    await input.press('ArrowDown');
    const active = await input.getAttribute('aria-activedescendant');
    assert.equal(await page.locator('#' + active).innerText(), 'Quick');
    await input.press('Enter');
    assert.equal(await page.locator('#quick-tag-5').isChecked(), true);
    assert.equal(await options.count(), 0);

    await page.selectOption('#jform_language', 'fr-FR');
    await input.fill('slow');
    await page.waitForFunction(() => document.activeElement.id === 'input-tags');
    for (let count = 0; !delayed && count < 30; count++) await new Promise(resolve => setTimeout(resolve, 25));
    assert(delayed, 'The delayed request started');
    await input.fill('Sample');
    const sample = page.getByRole('option', {name: '<Sample> (Échantillon)'});
    await sample.click();
    delayed();
    assert.equal(await page.getByText('Outdated').count(), 0);
    assert.equal(await page.locator('#ultagbox span').innerText(), '<Sample> (Échantillon)');
    await page.getByRole('link', {name: 'Remove tag'}).click();
    assert.equal(await page.locator('#ultagbox li').count(), 0);
    await input.fill('New | tag');
    await input.press('Enter');
    await page.getByText('Creation denied').waitFor();
    assert.equal(await input.inputValue(), 'New | tag');
    await input.press('Enter');
    await page.getByText('New | tag', {exact: true}).waitFor();
    assert.deepEqual(posted, ['New | tag', 'New | tag']);
    assert.equal(await page.locator('#ultagbox input').getAttribute('name'), 'jform[tag][]');
  });
  await test('Cascades replace Choices options and ignore stale responses without jQuery', async page => {
    await form(page, `
      <select id="parent"><option value="">None</option><option value="slow">Slow</option><option value="fast">Fast</option></select>
      <div class="control-group" id="dependent-row"><select id="child" class="use_select2_lib">
        <option value="">Choose</option><option value="old">Old</option>
      </select><span class="field_cascade_loading"></span></div>`);
    await loadDependencies(page);
    await page.addScriptTag({content: source('site/librairies/choicesjs/choices.min.js')});
    const calls = [];
    let delayed;
    await page.route(/\/index\.php\?option=com_flexicontent/, async route => {
      const body = new URLSearchParams(route.request().postData());
      assert.equal(body.get('extfunc'), 'getCascadedField');
      calls.push(body.get('valgrps'));
      if (body.get('valgrps') === 'slow') await new Promise(resolve => { delayed = resolve; });
      const option = body.get('valgrps') === 'slow' ? 'outdated' : 'new';
      await route.fulfill({body: '<option value="">Choose</option><option value="' + option + '">' + option + '</option>'}).catch(() => {});
    });
    await page.evaluate(() => {
      window.Joomla.JText = Joomla.Text;
      window.fc_use_choicesjs = true;
      fc_initSelect2(null, document);
      fcCascadedField(13, 42, 'select', 'select#parent, input.parent', 'child', 'Select a parent', true, 0);
      fcCascadedField(13, 42, 'select', 'select#parent, input.parent', 'child', 'Select a parent', true, 0);
    });
    await page.locator('#dependent-row').waitFor({state: 'hidden'});
    const slow = page.waitForRequest(request => request.postData()?.includes('valgrps=slow'));
    await page.selectOption('#parent', 'slow');
    await slow;
    await page.selectOption('#parent', 'fast');
    await page.locator('#child option[value="new"]').waitFor({state: 'attached'});
    delayed?.();
    assert.deepEqual(calls, ['slow', 'fast']);
    assert.equal(await page.locator('#child option[value="outdated"]').count(), 0);
    assert.equal(await page.locator('#child').evaluate(el => el.fc_choices_instance.dropdown.element.textContent.includes('new')), true);
    await page.selectOption('#parent', '');
    await page.locator('#dependent-row').waitFor({state: 'hidden'});
    assert.equal(await page.locator('#child option').textContent(), 'Select a parent');
  });
  await test('A cloned indexed field follows the controller in its own group', async page => {
    await form(page, `<ul class="fcfield-group">
      <li><select id="custom_parent_0"><option value="original">Original</option></select>
        <div class="control-group"><select id="custom_child_0"><option value="keep">Keep</option></select></div>
      </li>
      <li>
        <div class="control-group" id="cloned"><select id="custom_child_4"><option value="old">Old</option></select></div>
        <select id="custom_parent_9"><option value="current">Current</option></select>
      </li>
    </ul>`);
    const requests = [];
    await loadDependencies(page);
    await page.addScriptTag({content:source('site/assets/js/indexed-field.js')});
    await page.route(/\/index\.php\?option=com_flexicontent/, route => {
      const params = new URLSearchParams(route.request().postData());
      requests.push([params.get('valgrps'), params.get('valindex')]);
      return route.fulfill({body:'<option value="">Select</option><option value="new">New</option>'});
    });
    await page.evaluate(() => {
      fcCascadedField(21, 42, 'select', 'select#custom_parent_0, input.custom_parent_0', 'custom_child_0', 'Select', true, 0);
      fcfield_indexed.ready(document.getElementById('cloned'), { id:'custom_child_4', index:4 }, {
        fieldId:21, cascade:{ sourceBase:'custom_parent', itemId:42, fieldType:'select', prompt:'Select', promptEnabled:true },
      });
    });
    await page.locator('#custom_child_4 option[value="new"]').waitFor({state:'attached'});
    assert.deepEqual(requests, [['current','4']]);
    assert.equal(await page.inputValue('#custom_child_0'), 'keep');
  });
  await test('Chained cascades survive replaced radio groups without jQuery', async page => {
    await form(page, `
      <select id="parent"><option value="">None</option><option value="region">Region</option></select>
      <div class="control-group"><div id="child" class="fc_input_set"></div><span class="field_cascade_loading"></span></div>
      <div class="control-group" id="end-row"><select id="end" name="end"><option value="">Select</option></select><span class="field_cascade_loading"></span></div>`);
    await loadDependencies(page);
    const values = [];
    await page.route(/\/index\.php\?option=com_flexicontent/, route => {
      const body = new URLSearchParams(route.request().postData());
      values.push([body.get('field_id'), body.get('valgrps')]);
      const html = body.get('field_id') === '1'
        ? '<input type="radio" class="child" id="radio" name="child" value="zone"><label for="radio">Zone</label>'
        : '<option value="district">District</option>';
      return route.fulfill({body: html});
    });
    await page.evaluate(() => {
      fcCascadedField(1, 42, 'radio', 'select#parent, input.parent', 'child', 'Select region', false, 0);
      fcCascadedField(2, 42, 'select', 'select#child, input.child', 'end', 'Select zone', false, 0);
    });
    await page.selectOption('#parent', 'region');
    await page.check('#radio');
    await page.locator('#end option[value="district"]').waitFor({state: 'attached'});
    await page.selectOption('#parent', '');
    await page.locator('#end-row').waitFor({state: 'hidden'});
    await page.selectOption('#parent', 'region');
    await page.check('#radio');
    await page.locator('#end option[value="district"]').waitFor({state: 'attached'});
    assert.deepEqual(values, [['1', 'region'], ['2', 'zone'], ['1', 'region'], ['2', 'zone']]);
  });
  if (process.env.FLEXI_TEST_JQUERY) await test('Legacy synthetic cascade events send each selected value once', async page => {
    await form(page, '<select multiple id="parent"><option value="one">One</option><option value="two">Two</option></select><div class="control-group"><select id="child"></select><span class="field_cascade_loading"></span></div>');
    await loadDependencies(page);
    const payloads = [];
    await page.route(/\/index\.php\?option=com_flexicontent/, route => {
      payloads.push(new URLSearchParams(route.request().postData()).getAll('valgrps[]'));
      return route.fulfill({body: '<option value="descendant">Descendant</option>'});
    });
    await page.evaluate(() => {
      fcCascadedField(1, 42, 'select', 'select#parent, input.parent', 'child', '', true, 0);
      jQuery('#parent').val(['one','two']).trigger('change');
    });
    await page.locator('#child option[value="descendant"]').waitFor({state: 'attached'});
    assert.deepEqual(payloads, [['one', 'two']]);
  }, true);
  await test('Administrator dependencies initialize, refresh and rebind without jQuery', async page => {
    await form(page, `
      <select id="mode" class="fcform_toggler_element">
        <option value="simple" data-hide_list="configuration" data-setoff_list="#shortcut">Simple</option>
        <option value="advanced" data-show_list="configuration" data-seton_list="#shortcut"
          data-fcconfigs="{'preset':'two', 'flags':'b, c', 'note':'Example'}"
          data-fcreadonly="{'note':1}" data-refsh_list="#jform_params_preset">Advanced</option>
      </select>
      <div class="control-group" id="config-row"><label>Configuration</label><select id="jform_params_preset" class="fcform_toggler_element">
        <option value="one" data-hide_list="extra">One</option>
        <option value="two" data-show_list="extra">Two</option>
      </select><span class="configuration"></span></div>
      <div class="extra fc_toggle_current" id="extra">Extra</div>
      <div id="shortcut">Shortcut</div>
      <select id="jform_params_flags" multiple><option value="a">A</option><option value="b">B</option><option value="c">C</option></select>
      <input id="jform_note">
      <div class="fcform_toggler_element">
        <input id="off" type="radio" name="force" checked>
        <input id="on" type="radio" name="force" data-force_list="configuration">
      </div>`);
    await loadDependencies(page);
    await page.evaluate(() => {
      const form = document.forms[0];
      FlexiForm.data(form, 'skip_validation', 'previous');
      fc_bindFormDependencies(form, 2, '.control-group');
      fc_bindFormDependencies(form, 2, '.control-group');
    });
    await page.locator('#config-row').waitFor({state: 'hidden'});
    await page.locator('#extra').waitFor({state: 'hidden'});
    await page.locator('#shortcut').waitFor({state: 'hidden'});
    assert.equal(await page.evaluate(() => FlexiForm.data(document.forms[0], 'skip_validation')), 'previous');
    await page.selectOption('#mode', 'advanced');
    await page.locator('#config-row').waitFor({state: 'visible'});
    await page.locator('#shortcut').waitFor({state: 'visible'});
    await page.locator('#extra').waitFor({state: 'visible'});
    assert.equal(await page.locator('#jform_params_preset').inputValue(), 'two');
    assert.deepEqual(await page.locator('#jform_params_flags').evaluate(el => [...el.selectedOptions].map(option => option.value)), ['b', 'c']);
    assert.equal(await page.locator('#jform_note').inputValue(), 'Example');
    assert.equal(await page.locator('#jform_note').evaluate(el => el.readOnly), true);
    await page.selectOption('#mode', 'simple');
    await page.locator('#config-row').waitFor({state: 'hidden'});
    await page.check('#on');
    await page.locator('#config-row').waitFor({state: 'visible'});
  });
  if (process.env.FLEXI_TEST_JQUERY) await test('Conditional settings accept native and legacy synthetic change events exactly once', async page => {
    await form(page, `
      <select id="mode" class="fcform_toggler_element">
        <option value="show" data-show_list="detail" data-fcconfigs="{'description':'Visible'}">Show</option>
        <option value="hide" data-hide_list="detail" data-fcconfigs="{'description':'Hidden'}">Hide</option>
      </select>
      <div class="detail">Detail</div><input id="jform_description">`);
    await loadDependencies(page);
    await page.evaluate(() => {
      window.updates = 0;
      document.querySelector('#jform_description').addEventListener('blur', () => updates++);
      fc_bindFormDependencies('#adminForm', 0, '');
      fc_bindFormDependencies('#adminForm', 0, '');
      updates = 0;
    });
    await page.evaluate(() => jQuery('#mode').val('hide').trigger('change'));
    await page.locator('.detail').waitFor({state: 'hidden'});
    assert.equal(await page.evaluate(() => updates), 1);
    await page.selectOption('#mode', 'show');
    await page.locator('.detail').waitFor({state: 'visible'});
    assert.equal(await page.evaluate(() => updates), 2);
  }, true);
  await test('Text repeaters add, reorder, enforce limits, and keep an empty last row without jQuery', async page => {
    await form(page, `
      <ul id="sortables_42"><li class="fcfieldval_container" style="height:70px">
        <span class="fcfield-drag-handle" style="display:inline-block;width:55px">Move</span>
        <label for="custom_text_0" data-for="custom_text_0">Text</label>
        <input class="fcfield_textval required" id="custom_text_0" name="custom[text][0]" value="First">
        <button type="button" class="remove" onclick="textRows.remove(this)">Remove</button>
      </li></ul><button type="button" id="add" onclick="textRows.add(this, null, {scroll_visible: false})">Add</button>`);
    await validate(page);
    await loadDependencies(page);
    await page.addScriptTag({content: source('site/librairies/choicesjs/sortable.min.js')});
    await page.addScriptTag({content: source('site/assets/js/field-repeater.js')});
    await page.addScriptTag({content: source('plugins/flexicontent_fields/text/js/form.js')});
    await page.evaluate(() => {
      window.textRows = FlexiFieldRepeater.get({fieldId: 42, initialCount: 1, id: 'custom_text', name: 'custom[text]', defaultValue: '', max: 2}, fcfield_text);
      FlexiFieldRepeater.attachOrdering(42);
    });
    await page.click('#add');
    assert.equal(await page.inputValue('#custom_text_1'), '');
    assert.equal(await page.locator('label[for="custom_text_1"]').getAttribute('data-for'), 'custom_text_1');
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    await page.fill('#custom_text_1', 'Second');
    const handles = page.locator('.fcfield-drag-handle');
    const start = await handles.first().boundingBox(), end = await handles.last().boundingBox();
    await page.mouse.move(start.x + 12, start.y + 8);
    await page.mouse.down();
    await page.mouse.move(end.x + 12, end.y + 45, {steps: 15});
    await page.mouse.up();
    assert.deepEqual(await page.evaluate(() => [...new FormData(document.forms[0])].filter(([name]) => name.startsWith('custom')).map(([, value]) => value)), ['Second', 'First']);
    const dialog = page.waitForEvent('dialog');
    const extraClick = page.click('#add');
    await (await dialog).accept();
    await extraClick;
    assert.equal(await handles.count(), 2);
    await page.locator('.remove').first().click();
    await page.locator('.remove').first().click();
    assert.equal(await handles.count(), 1);
    assert.equal(await page.inputValue('#custom_text_2'), '');
  });
  await test('Cloning Text Select preserves the original Choices widget and initializes the clone without jQuery', async page => {
    await form(page, `
      <ul id="sortables_43"><li class="fcfieldval_container">
        <input class="fcfield_textval" id="custom_text_0" name="custom[text][0]" value="Original">
        <select name="sample[]" class="use_select2_lib fcfield_textselval" onchange="fcfield_text.selectValue(this)">
          <option value="">Choose</option><option value="sample">A sample</option>
        </select>
      </li></ul><button type="button" id="add">Add</button>`);
    await validate(page);
    await loadDependencies(page);
    await page.addScriptTag({content: source('site/librairies/choicesjs/choices.min.js')});
    await page.addStyleTag({content: source('site/librairies/choicesjs/choices.min.css')});
    await page.addScriptTag({content: source('site/assets/js/field-repeater.js')});
    await page.addScriptTag({content: source('plugins/flexicontent_fields/text/js/form.js')});
    await page.evaluate(() => {
      window.Joomla.JText = Joomla.Text;
      window.fc_use_choicesjs = true;
      fc_attachSelect2('#sortables_43');
      window.originalChoice = document.querySelector('select').fc_choices_instance;
      window.textSelectRows = FlexiFieldRepeater.get({fieldId: 43, initialCount: 1, id: 'custom_text', name: 'custom[text]', defaultValue: '', max: 2}, fcfield_text);
      document.getElementById('add').addEventListener('click', event => textSelectRows.add(event.target, null, {scroll_visible: false}));
    });
    await page.click('#add');
    assert.equal(await page.locator('div.choices').count(), 2);
    assert.deepEqual(await page.evaluate(() => {
      const [first, second] = document.querySelectorAll('select');
      return [first.fc_choices_instance === originalChoice, second.fc_choices_instance.passedElement.element === second];
    }), [true, true]);
    await page.locator('div.choices').last().click();
    await page.locator('div.choices').last().locator('[data-choice][data-value="sample"]').click();
    assert.equal(await page.inputValue('#custom_text_1'), 'sample');
    assert.equal(await page.inputValue('#custom_text_0'), 'Original');
    assert.deepEqual(await page.evaluate(() => {
      const clone = document.getElementById('custom_text_1_select');
      textSelectRows.remove(null, clone.closest('li'));
      return [clone.isConnected, !!clone.fc_choices_instance, document.querySelector('select').fc_choices_instance === originalChoice];
    }), [false, false, true]);
  });
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
    assert.deepEqual(await page.locator('[name="boxes[]"]').evaluateAll(boxes => boxes.map(box => box.getAttribute('aria-invalid'))), ['true', 'true']);
    await page.locator('[value=a]').check();
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    await page.locator('[value=b]').check();
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), true);
    assert.deepEqual(await page.locator('[name="boxes[]"]').evaluateAll(boxes => boxes.map(box => box.getAttribute('aria-invalid'))), ['false', 'false']);
    assert.equal(await page.inputValue('#jform_catid'), '4');
    await page.selectOption('#jform_cid', []);
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
  });
  await test('Checkbox errors mark every enabled option and survive a disabled sibling', async page => {
    await form(page, '<fieldset class="fc_input_set"><input id="a" type="checkbox" name="choices[]" value="a" class="validate-cboxlimitations" data-min_values="2"><label for="a">A</label><input id="b" type="checkbox" name="choices[]" value="b" class="validate-cboxlimitations" data-min_values="2"><label for="b">B</label><input id="disabled-box" type="checkbox" name="choices[]" disabled checked></fieldset>');
    await validate(page);
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    assert.equal(await page.locator('fieldset.invalid input[aria-invalid=true]').count(), 2);
    assert.equal(await page.locator('#disabled-box').getAttribute('aria-invalid'), null);
    assert.equal(await page.locator('label.invalid').count(), 2);
    assert.equal(await page.locator('.fc-field-invalid').count(), 1);
    await page.locator('#a').check();
    assert.equal(await page.locator('input[aria-invalid=true]').count(), 2);
    await page.locator('#b').check();
    assert.equal(await page.locator('input[aria-invalid=true], .fc-field-invalid').count(), 0);
  });
  await test('Incomplete masked email stays editable and blank optional masks stay valid', async page => {
    await form(page, `<label for="email">Email</label><input id="email" class="has_inputmask" data-inputmask="'alias':'email'">
      <label for="ip">IP address</label><input id="ip" class="has_inputmask" data-inputmask="'alias':'ip'">`);
    await validate(page);
    await page.addScriptTag({content: source('site/librairies/inputmask/inputmask.min.js')});
    await page.addScriptTag({content: source('site/assets/js/input-masks.js')});
    const valid = () => page.evaluate(() => document.formvalidator.isValid(document.getElementById('adminForm')));
    assert.equal(await valid(), true);
    const email = page.locator('#email');
    await email.pressSequentially('aaaa@');
    assert.equal(await valid(), false);
    assert.match(await email.inputValue(), /^aaaa@/);
    assert.equal(await email.getAttribute('aria-invalid'), 'true');
    assert.equal(await page.locator('.fc-field-invalid').filter({hasText:'FLEXI_VALIDATION_EMAIL_FORMAT'}).count(), 1);
    assert.equal(await page.locator('#ip').getAttribute('aria-invalid'), 'false');
    await email.pressSequentially('example.test');
    assert.equal(await valid(), true);
    assert.equal(await page.locator('.fc-field-invalid').count(), 0);
  });
  await test('Hidden rich text is synchronized before required validation', async page => {
    await form(page, '<div class="fcfield_box required_box"><textarea id="body" name="body" class="mce_editable" style="display:none"></textarea></div>');
    await page.evaluate(() => { window.editorText = ''; window.tinyMCE = {get: () => ({getContent: () => editorText, setContent: value => { editorText = value; }})}; });
    await validate(page);
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), false);
    await page.evaluate(() => { editorText = '<p>Written in the editor</p>'; });
    assert.equal(await page.evaluate(() => document.formvalidator.isValid(document.forms[0])), true);
    assert.equal(await page.inputValue('#body'), '<p>Written in the editor</p>');
    await page.evaluate(()=>{tinyMCE.get=()=>({initialized:false,getContent(){throw Error('Editor is still starting');}});});
    assert.equal(await page.evaluate(()=>document.formvalidator.isValid(document.forms[0])),true);
    assert.equal(await page.inputValue('#body'),'<p>Written in the editor</p>');
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
    await form(page, '<label for="legacy-title">Title</label><input id="legacy-title" name="title" value="Legacy editor">', 'data-fc_force_serialization="1"');
    await validate(page);
    assert(await page.evaluate(()=>document.formvalidator.isValid(document.forms[0])));
    assert.equal(await page.evaluate(()=>jQuery('#legacy-title').data('label').text()),'Title');
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
  await test('Related items can be picked without a select enhancer or jQuery', async page => {
    await form(page, `<div class="container_fcfield">
      <input type="checkbox" class="fc_field_filter" name="filter[4][]" value="blue" checked>
      <input type="checkbox" class="fc_field_filter" name="filter[4][]" value="red">
      <div class="fcfield-relation-value_box" data-field_id="7" data-item_id="11">
        <select id="category" class="fcfield-relation-cat_selector" onchange="fcfield_relation.cat_selector_change(this)">
          <option value="">Select category</option><option value="8">Category</option>
        </select>
        <div class="fcfield-relation-item_selector_box">
          <select id="pick" class="fcfield-relation-item_selector" onchange="fcfield_relation.add_related(this)"><option value="">-</option></select>
        </div>
        <select id="related" multiple class="fcfield-relation-selected_items" name="custom[related][]"
          onchange="fcfield_relation.selected_items_modified(this)" data-fc_choices_search_remote="1"></select>
      </div>
    </div>`);
    const requests = [];
    await page.route('https://fixture.invalid/index.php?*', route => {
      requests.push({ url:new URL(route.request().url()), body:new URLSearchParams(route.request().postData()) });
      return route.fulfill({ json:{ error:'', options:[{item_id:9,item_title:'Cedar'}] } });
    });
    await page.addScriptTag({content:source('plugins/flexicontent_fields/relation/js/form.js')});
    await page.evaluate(() => { window.sessionToken7 = 'fixtureToken'; });
    await page.selectOption('#category','8');
    await page.waitForFunction(() => document.querySelector('#pick option[value="9"]'));
    await page.selectOption('#pick','9');
    assert.deepEqual(await page.evaluate(() => new FormData(document.forms[0]).getAll('custom[related][]')), ['9:8']);
    assert(await page.locator('#pick option[value="9"]').isDisabled());
    await page.selectOption('#related', []);
    assert.equal(await page.locator('#related option').count(), 0);
    assert.equal(await page.locator('#pick option[value="9"]').isDisabled(), false);
    assert.equal(requests[0].url.searchParams.get('fixtureToken'), '1');
    assert.equal(requests[0].body.get('item_id'), '11');
    assert.equal(requests[0].body.get('catid'), '8');
    assert.deepEqual(new URLSearchParams(requests[0].body.get('customfilts')).getAll('filter[4][]'), ['blue']);
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
  await test('Batch assets preserve Joomla import maps and inline module scripts',async page=>{
    await form(page,'<input id="module-field" value="kept">');
    await page.route('https://fixture.invalid/editor-api.js',route=>route.fulfill({contentType:'text/javascript',body:'export const readField = () => document.getElementById("module-field").value;'}));
    await page.route('https://fixture.invalid/editor-init.js',route=>route.fulfill({contentType:'text/javascript',body:'import {readField} from "fixture-editor-api"; window.externalModuleValue=readField();'}));
    await page.addScriptTag({content:source('admin/assets/js/batchprocess.js')});
    await page.evaluate(async()=>{
      await fc_batch_fields.loadAssets({importmap:{imports:{'fixture-editor-api':'/editor-api.js'}},scripts:[{src:'/editor-init.js',module:true}]});
      await fc_batch_fields.runInline({type:'module',code:'import {readField} from "fixture-editor-api"; window.inlineModuleValue=readField();'});
    });
    await page.waitForFunction(()=>window.inlineModuleValue==='kept');
    assert.deepEqual(await page.evaluate(()=>[externalModuleValue,inlineModuleValue]),['kept','kept']);
  });
  await test('Standalone Inputmask preserves FLEXIcontent presets and Joomla submit values', async page => {
    await form(page, [
      '<input class="has_inputmask" name="decimal" data-inputmask="\'alias\': \'decimal\'">',
      '<input class="has_inputmask" name="euro" data-inputmask="\'alias\': \'decimal_comma\'">',
      '<input class="has_inputmask" name="date" data-inputmask="\'alias\': \'dd/mm/yyyy\'">',
      '<input class="has_inputmask" name="dateTime" data-inputmask="\'alias\': \'datetime\'">',
      '<input class="has_inputmask" name="time" data-inputmask="\'alias\': \'hh:mm t\'">',
      '<input class="has_inputmask" name="phone" data-inputmask="\'mask\': \'9{4,4} 9{3,3} 9{3,3}\', \'removeMaskOnSubmit\': \'true\'">',
      '<input class="inputmask-regex" name="regex" data-inputmask-regex="[A-Z]{2}[0-9]{2}">',
    ].join(''));
    await page.route('https://fixture.invalid/save', route => route.fulfill({body: 'Saved'}));
    for (const file of ['site/librairies/inputmask/inputmask.min.js', 'site/assets/js/input-masks.js', 'site/assets/js/admin.js']) {
      await page.addScriptTag({content: source(file)});
    }
    for (const [name, entered, displayed] of [
      ['decimal', '1234.5', '1,234.5'],
      ['euro', '1234,5', '1.234,5'],
      ['date', '29022028', '29/02/2028'],
      ['dateTime', '191120261415', '19/11/2026 14:15'],
      ['time', '0245pm', '02:45 pm'],
      ['phone', '0912345678', '0912 345 678'],
      ['regex', 'A!B12', 'AB12']
    ]) {
      const input = page.locator('[name="' + name + '"]');
      await input.pressSequentially(entered);
      await input.press('Tab');
      assert.equal(await input.inputValue(), displayed, name);
    }
    await page.evaluate(() => {
      const row = document.createElement('div');
      row.innerHTML = '<input class="has_inputmask" name="added" data-inputmask="\'alias\': \'unsigned\'">';
      document.getElementById('adminForm').append(row);
      fc_initInputMasks(row);
    });
    const added = page.locator('[name=added]');
    await added.pressSequentially('-27');
    await added.press('Tab');
    assert.equal(await added.inputValue(), '27');
    const [request] = await Promise.all([
      page.waitForRequest(req => req.url().endsWith('/save') && req.method() === 'POST'),
      page.waitForURL('**/save', {waitUntil:'domcontentloaded'}),
      page.evaluate(() => Joomla.submitform('items.save', document.getElementById('adminForm')))
    ]);
    const fields = new URLSearchParams(request.postData());
    assert.equal(fields.get('decimal'), '1234.5');
    assert.equal(fields.get('euro'), '1234,5');
    assert.equal(fields.get('phone'), '0912345678');
    assert.equal(fields.get('date'), '29/02/2028');
    assert.equal(fields.get('added'), '27');
  });

  await test('Web Link metadata ignores stale results and supports providers that require JSONP', async page => {
    await form(page, '<input id="link_link"><input id="link_image"><div id="fcfield_message_box_link"></div>');
    await page.route('https://noembed.com/embed?**', async route => {
      const endpoint = new URL(route.request().url());
      const callback = endpoint.searchParams.get('callback');
      const clip = new URL(endpoint.searchParams.get('url')).pathname;
      if (callback) return route.fulfill({contentType:'text/javascript', body: callback + '({thumbnail_url:"https://fixture.invalid/thumb.png"})'});
      if (clip === '/old12345678') {
        await new Promise(resolve => setTimeout(resolve, 200));
        return route.fulfill({headers:{'Access-Control-Allow-Origin':'*'}, json:{thumbnail_url:'https://fixture.invalid/old.png'}}).catch(() => {});
      }
      return route.abort();
    });
    for (const file of ['site/assets/js/media-metadata.js', 'plugins/flexicontent_fields/weblink/js/form.js']) {
      await page.addScriptTag({content: source(file)});
    }
    await page.evaluate(async () => {
      const input = document.getElementById('link_link');
      input.value = 'https://youtu.be/old12345678';
      const first = fcfield_weblink.fetchData('link');
      input.value = 'https://youtu.be/dQw4w9WgXcQ';
      await Promise.all([first, fcfield_weblink.fetchData('link')]);
    });
    assert.equal(await page.locator('#link_image').inputValue(), 'https://fixture.invalid/thumb.png');
    assert.equal(await page.evaluate(() => Object.keys(window).filter(key => /^fcMediaMetadata\d+$/.test(key)).length), 0);
  });

  await test('Shared Media metadata fills native form values and clears the embed', async page => {
    const props = ['title','author','duration','description','thumb','api_type','media_id','embed_url','height','width'];
    await form(page, '<input id="clip_url"><div id="fcfield_message_box_clip"></div><table>' +
      props.map(key => '<tr><td><input id="clip_' + key + '" name="' + key + '"></td></tr>').join('') +
      '<tr><td id="clip_preview"></td></tr></table>');
    await page.route('https://www.youtube.com/embed/**', route => route.fulfill({body:''}));
    await page.route('https://www.googleapis.com/youtube/v3/videos?**', route => {
      assert.equal(new URL(route.request().url()).searchParams.get('id'), 'dQw4w9WgXcQ');
      return route.fulfill({headers:{'Access-Control-Allow-Origin':'*'}, json:{items:[{
        snippet:{title:'Fixture <b>title</b>', channelTitle:'Fixture author', description:'Fixture description', thumbnails:{medium:{url:'https://fixture.invalid/thumb.jpg'}}},
        contentDetails:{duration:'PT1M13S'}
      }]}});
    });
    for (const file of ['site/assets/js/media-metadata.js', 'plugins/flexicontent_fields/sharedmedia/js/form.js']) {
      await page.addScriptTag({content: source(file)});
    }
    await page.locator('#clip_url').fill('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    await page.evaluate(async () => {
      fcfield_sharemedia.use_native_apis.clip = 1;
      await fcfield_sharemedia.fetchData('clip', 'clip');
    });
    assert.equal(await page.locator('#clip_title').inputValue(),'Fixture <b>title</b>');
    assert.equal(await page.locator('#clip_duration').inputValue(),'73');
    assert.equal(await page.locator('#clip_media_id').inputValue(),'dQw4w9WgXcQ');
    assert.equal(await page.locator('#clip_preview iframe').getAttribute('src'),'https://www.youtube.com/embed/dQw4w9WgXcQ');
    await page.evaluate(() => fcfield_sharemedia.clearData('clip'));
    assert.equal(await page.locator('#clip_preview iframe').count(), 0);
    assert.equal(await page.locator('#clip_media_id').inputValue(), '');
  });

  await test('Shared Media card panels and clones keep independent controls', async page => {
    await form(page, `<div id="clip-row"><button type="button" class="fc-sm-url-btn" data-for="clip_0" onclick="fcfield_sharemedia.toggleURL('clip_0')">URL</button>
      <button type="button" class="fc-sm-edit-btn" data-for="clip_0" onclick="fcfield_sharemedia.toggleFields('clip_0')">Edit</button>
      <div class="sm_url_props" data-name="clip_0" style="display:none"><input class="sm_url" id="clip_0_url" value="https://example.test/first"></div>
      <div class="sm_value_props" data-name="clip_0" style="display:none">${['title','author','duration','description','thumb','api_type','media_id','embed_url'].map(key => `<input id="clip_0_${key}" name="custom[clip][0][${key}]"><label for="clip_0_${key}">${key}</label>`).join('')}</div>
      <div id="fcfield_message_box_clip_0"></div><div class="fc-video-preview-box"><div class="sm_preview" id="clip_0_preview"></div></div></div>`);
    await page.evaluate(() => {
      const wrapper=document.createElement('div');wrapper.id='flexicontent';document.body.append(wrapper);wrapper.append(document.getElementById('adminForm'));
      const card=document.getElementById('clip-row');card.className='card fc-video-card';card.style.width='250px';
      document.getElementById('clip_0_description').outerHTML='<textarea id="clip_0_description" name="custom[clip][0][description]" style="min-width:248px"></textarea>';
    });
    await page.addStyleTag({content:source('site/assets/css/flexi_form_fields.css')});
    for (const file of ['site/assets/js/field-repeater.js','site/assets/js/native-field-controls.js','site/assets/js/media-metadata.js','plugins/flexicontent_fields/sharedmedia/js/form.js']) await page.addScriptTag({content:source(file)});
    await page.locator('.fc-sm-url-btn').focus(); await page.keyboard.press('Enter');
    assert(await page.locator('.sm_url_props').isVisible());
    await page.locator('.fc-sm-edit-btn').click();
    await page.evaluate(() => {
      const clone=document.getElementById('clip-row').cloneNode(true);clone.id='clone-row';
      fcfield_sharedmedia_rows.prepare(clone,{id:'clip_1',name:'custom[clip][1]'});document.getElementById('adminForm').append(clone);
    });
    const second=page.locator('#clone-row');
    assert.equal(await second.locator('.sm_value_props').getAttribute('data-name'),'clip_1');
    assert.equal(await second.locator('.fc-sm-edit-btn').getAttribute('aria-expanded'),'false');
    assert.equal(await second.locator('.sm_url').inputValue(),'');
    await second.locator('.fc-sm-url-btn').click();
    await second.locator('.sm_url').fill('https://example.test/second');
    await second.locator('.fc-sm-url-btn').click();
    assert.equal(await second.locator('.sm_url_props').isVisible(),false);
    assert.equal(await page.locator('#clip_0_url').inputValue(),'https://example.test/first');
    await second.locator('.fc-sm-edit-btn').click();
    assert.equal(await second.locator('.sm_value_props').isVisible(),true);
    for (const width of [390,768,1440]) {
      await page.setViewportSize({width,height:960});
      assert(await second.locator('textarea').evaluate(el=>el.getBoundingClientRect().right<=el.closest('.fc-video-card').getBoundingClientRect().right+1));
    }
    await page.evaluate(()=>fcfield_sharemedia.clearData('clip_1'));
    assert.equal(await second.locator('.sm_value_props').isVisible(),false);
    assert.equal(await page.locator('#clip-row .sm_value_props').isVisible(),true);
  });

  await test('Google Maps accepts a place and updates form coordinates without jQuery', async page => {
    await form(page, '<input id="a_autocomplete"><select id="a_ac_type"><option value="geocode">Address</option><option value="establishment">Business</option></select>' +
      '<div id="a_messages" data-map-engine="googlemap"></div><div id="a_addressint_map"><div id="map_canvas_a"></div></div>' +
      ['country','city','addr_display','lat','lon','zoom','marker_tolerance'].map(key => '<input id="a_' + key + '">').join(''));
    await page.evaluate(() => {
      const events = { addListener(target, event, callback) {
        target[event] = callback;
        return {remove() { delete target[event]; }};
      }};
      window.google = {maps:{
        event:events,
        places:{Autocomplete:class {
          constructor(input, options) { this.options = options; }
          getPlace() { return window.testPlace; }
          setTypes(types) { this.types = types; }
        }},
        Map:class { constructor(canvas, options) { this.zoom = options.zoom; } getZoom() { return this.zoom; } },
        Marker:class { constructor(options) { this.position = options.position; } getPosition() { return this.position; } setPosition(pos) { this.position = pos; } setIcon(icon) { this.icon = icon; } },
        Geocoder:class { geocode(_, callback) { callback([window.testPlace], 'OK'); } },
        geometry:{spherical:{computeDistanceBetween:() => window.testDistance}},
        MapTypeId:{ROADMAP:'roadmap'}, Animation:{DROP:'drop'}, GeocoderStatus:{OK:'OK'}
      }};
      window.testPlace = {
        formatted_address:'1 Fixture Road, Taipei, Taiwan',
        geometry:{location:{lat:() => 25.031, lng:() => 121.567}},
        address_components:[
          {types:['country'], short_name:'TW', long_name:'Taiwan'},
          {types:['locality'], short_name:'Taipei', long_name:'Taipei'}
        ]
      };
    });
    await page.addScriptTag({content:source('plugins/flexicontent_fields/addressint/js/form.js')});
    await page.addScriptTag({content:source('plugins/flexicontent_fields/addressint/js/repeater.js')});
    await page.addScriptTag({content:source('plugins/flexicontent_fields/addressint/js/form_osm.js')});
    await page.evaluate(() => {
      const fields = fcfield_addrint;
      fields.allowed_countries.address = ['TW'];
      fields.map_zoom.address = 16;
      fields.map_type.address = 'ROADMAP';
      fcfield_address.start('a', 'address');
      fields.autoComplete.a.place_changed();
      fields.google_maps.a.zoom = 18;
      fields.google_maps.a.zoom_changed();
    });
    assert.equal(await page.locator('#a_addr_display').inputValue(), '1 Fixture Road, Taipei, Taiwan');
    assert.equal(await page.locator('#a_country').inputValue(), 'TW');
    assert.equal(await page.locator('#a_zoom').inputValue(), '18');
    await page.evaluate(() => {
      testDistance = 150;
      fcfield_addrint.posMarker.a.position = {lat:25.032, lng:121.568};
      fcfield_addrint.posMarker.a.dragend();
    });
    assert.equal(await page.locator('#a_lat').inputValue(), '25.032');
    assert.equal(await page.locator('#a_lon').inputValue(), '121.568');
    assert.match(await page.locator('#a_messages').textContent(), /WITHIN_TOLERANCE/);
    const testData = await page.evaluate(() => {
      const fields = fcfield_addrint;
      document.getElementById('a_ac_type').value = 'establishment';
      fields.changeAutoCompleteType('a');
      return fields.autoComplete.a.types;
    });
    assert.deepEqual(testData, ['establishment']);
  });

  await test('Item history loads the next page and opens compare without jQuery', async page => {
    await form(page, '<table id="version_tbl"><tbody><tr><td>Earlier version</td></tr></tbody></table>' +
      '<div id="fc_pager"></div><textarea id="versioncomment"></textarea>');
    await page.route('https://fixture.invalid/versions?**', route => {
      assert.equal(new URL(route.request().url()).searchParams.get('page'), '2');
      return route.fulfill({contentType:'text/html', body:
        '<tbody><tr><td><a class="modal-versions" href="/compare">Compare version 2</a></td></tr></tbody>'});
    });
    await page.addScriptTag({content:source('site/assets/js/item-history.js')});
    await page.evaluate(() => {
      window.fc_initTooltips = () => {};
      window.fc_showDialog = url => { window.comparison = url; };
      fc_initItemHistory('/versions', 1, 3);
    });
    await page.getByRole('button', {name:'2', exact:true}).click();
    await page.waitForFunction(() => document.querySelector('#fc_pager [aria-current=page]')?.textContent === '2');
    await page.getByRole('link', {name:'Compare version 2'}).click();
    assert.equal(await page.evaluate(() => window.comparison), 'https://fixture.invalid/compare');
  });
  await browser.close();
  console.log(JSON.stringify(results, null, 2));
  if (results.some(result => result.status !== 'pass')) process.exitCode = 1;
})().catch(error => { console.error(error); process.exitCode = 1; });
