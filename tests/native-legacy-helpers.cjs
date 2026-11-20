'use strict';
const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
(async () => {
  const browser = await chromium.launch({headless: true});
  try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('https://fixture.invalid/**', route => route.fulfill({contentType: 'text/html', body: '<main></main>'}));
    await page.goto('https://fixture.invalid/');
    await page.route('**/editor-api.js', route => route.fulfill({contentType: 'text/javascript', body: 'await new Promise(resolve => setTimeout(resolve, 100)); export const JoomlaEditor = {unregister(id) { window.editorRegistry.delete(id); }};'}));
    await page.addScriptTag({type: 'importmap', content: JSON.stringify({imports: {'editor-api': 'https://fixture.invalid/editor-api.js'}})});
    await page.evaluate(() => { window.Joomla = {JText: {_: key => key}, Text: {_: key => key}, getOptions: () => ''}; });
    const load = file => page.addScriptTag({path: path.join(__dirname, '..', file)});
    await load('site/assets/js/form-utils.js');
    await load('site/assets/js/flexi-lib.js');
    await page.evaluate(() => {
      for (const broken of ['null', '[]', '{bad', '42']) {
        fclib_setCookie('recent', broken, 1);
        const list = fclib_createCookieList('recent');
        list.add('7', {title: 'Saved'});
        if (list.items()['7'].title !== 'Saved') throw Error('Cookie recovery failed');
      }
      document.cookie = 'recent=%E0%A4%A;path=/';
      if (fclib_getCookie('recent') !== '') throw Error('Malformed cookie encoding not handled');
      const list = fclib_createCookieList('recent');
      list.add('__proto__', {safe: true});
      if (Object.getPrototypeOf(list.items()) !== null) throw Error('Unsafe dictionary');
      list.remove('__proto__'); list.add('8', {title: 'Unicode 測試'});
      if (fclib_createCookieList('recent').items()['8'].title !== 'Unicode 測試') throw Error('Cookie round trip failed');
      list.clear();
      document.body.insertAdjacentHTML('beforeend', `<form id="saved"><fieldset id="group"><input id="global" type="checkbox"><input name="flags[]" type="checkbox" value="a" checked><input name="flags[]" type="checkbox" value="b"></fieldset>
        <input name="radio" type="radio" value="a" checked><input name="radio" type="radio" value="b">
        <input name='quote"name' value="original"><textarea name="body">Initial</textarea>
        <select id="multi" name="multi" multiple><option value="a" selected>Alpha</option><option value="b">Beta</option><option value="c">Gamma</option></select></form>
        <div id="heights" style="display:flex;align-items:flex-start"><div style="width:100px;padding:10px;border:2px solid;box-sizing:border-box">Short</div><div style="width:100px;padding:2px;box-sizing:content-box">Long<br>Long<br>Long<br>Long</div></div>`);
      const global = document.getElementById('global'); global.checked = true;
      fc_toggle_checkbox_group('group', global);
      if (global.disabled || !document.querySelector('[name="flags[]"]').disabled) throw Error('Use Global toggle failed');
      global.checked = false; fc_toggle_checkbox_group('group', [global]);
      fc_restore_form_field_values([document.forms.saved], {'flags[]': ['b'], radio: 'b', 'quote"name': 'Changed', body: 'New', multi: ['b','c']});
      const form = document.forms.saved;
      if (form.elements['flags[]'][0].checked || !form.elements['flags[]'][1].checked || form.elements.radio.value !== 'b' || form.elements['quote"name'].value !== 'Changed') throw Error('Restore grouped or quoted fields failed');
      if ([...form.elements.multi.selectedOptions].map(o => o.value).join() !== 'b,c') throw Error('Multiple values lost');
      fc_restore_form_field_values(form, {'flags[]': []});
      if ([...form.elements['flags[]']].some(e => e.checked)) throw Error('Unable to clear checkboxes');
      fc_restore_form_field_values(form, null);
      if (form.elements.body.value !== 'Initial' || !form.elements['flags[]'][0].checked) throw Error('Reset failed');
      fc_setEqualHeights(document.getElementById('heights'), 0, 1);
    });
    await page.waitForTimeout(30);
    const heights = await page.locator('#heights > div').evaluateAll(nodes => nodes.map(n => n.getBoundingClientRect().height));
    assert.ok(Math.abs(heights[0] - heights[1]) < 1);
    await load('site/librairies/choicesjs/choices.min.js');
    await page.evaluate(() => { window.fc_Choices = Choices; });
    // The helper also accepts controls enhanced by Choices.
    await page.evaluate(() => {
      const select = document.getElementById('multi'); select.fc_choices_instance = new Choices(select, {shouldSort: false});
      fc_restore_form_field_values(document.forms.saved, {multi: ['b', 'c']});
    });
    assert.deepEqual(await page.locator('#multi').evaluate(select => select.fc_choices_instance.getValue(true)), ['b', 'c']);
    await page.evaluate(() => {
      const area = document.createElement('textarea'); area.id = 'area[quoted]'; area.className = 'required custom'; document.body.append(area);
      const registry = new Map();
      window.tinymce = {majorVersion: '7', get: id => registry.get(id), init(options) {
        if (options.selector !== null || options.target !== area || options.plugins !== 'link') throw Error('Editor options lost');
        registry.set(area.id, {settings: options, save() { area.value = 'Edited content'; }, remove() { registry.delete(area.id); }});
      }};
      fc_attachTinyMCE([area], {plugins: 'link'});
      fc_removeAreaEditors([area], true);
      if (area.value !== 'Edited content') throw Error('Editor content lost');
      fc_restoreAreaEditors([area]);
      if (!tinymce.get(area.id) || !area.classList.contains('required')) throw Error('Editor restore failed');
      window.CodeMirror = {fromTextArea(target, options) {
        const wrapper = document.createElement('div'); wrapper.className = 'CodeMirror'; target.after(wrapper);
        wrapper.CodeMirror = {options, refresh() {}, toTextArea() {target.value='Code saved'; wrapper.remove();}};
        return wrapper.CodeMirror;
      }};
      fc_attachCodeMirror(area, {mode: 'htmlmixed'});
      fc_removeAreaEditors(area, true); fc_restoreAreaEditors(area);
      if (area.nextElementSibling.CodeMirror.options.mode !== 'htmlmixed' || !area.classList.contains('custom')) throw Error('CodeMirror restore failed');
    });
    await page.evaluate(() => {
      document.body.insertAdjacentHTML('beforeend', '<div class="js-editor-tinymce"><textarea id="joomla-area">Original</textarea></div>');
      const area = document.getElementById('joomla-area');
      window.editorRegistry = new Set([area.id]);
      let present = true;
      window.tinymce = {get: id => id === area.id && present ? {save() {area.value='Edited Unicode 測試';}, remove() {present=false;}} : null};
      Joomla.JoomlaTinyMCE = {setupEditor(target) {
        if (editorRegistry.has(target.id)) throw Error('Restored before Joomla registry was cleared');
        if (target.value !== 'Edited Unicode 測試') throw Error('Joomla editor value lost');
        present = true; window.joomlaEditorRestored = true;
      }};
      fc_removeAreaEditors(area, true); fc_restoreAreaEditors(area);
    });
    await page.waitForFunction(() => window.joomlaEditorRestored);
    assert.deepEqual(errors, []);
    assert.equal(await page.evaluate(() => typeof jQuery), 'undefined');
    console.log('PASS: malformed cookies, Unicode, grouped values, reset, Choices, Use Global, equal heights, editor remove/restore without jQuery');
  } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
