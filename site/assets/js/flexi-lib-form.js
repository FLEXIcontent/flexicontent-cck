/** Repeatable administrator field records and ordered field selectors. */
var fc_field_dialog_handle_fcrecord_list;
const fc_recordLoads = new WeakMap();
function fcfield_add2list(id, selector) {
  const input = document.getElementById(id), select = FlexiForm.element(selector);
  input.value += (input.value.trim() ? ', ' : '') + select.value;
  FlexiForm.change(input);
}
function fcfield_store_ordering(value) {
  const list = FlexiForm.element(value), input = document.getElementById(list.id.replace('sortable-', '').trim());
  input.value = Array.from(list.children, item => item.dataset.value.replace('field_', '')).join(',');
  FlexiForm.change(input);
}
function fcfield_del_sortable_element(value) {
  const item = FlexiForm.element(value).closest('li'), list = item.parentElement;
  item.remove(); fcfield_store_ordering(list);
}
function fc_recordButton(item, className, title, callback) {
  const button = document.createElement('button'); button.type = 'button';
  button.className = className; button.title = title; button.setAttribute('aria-label', title);
  button.style.cssText = 'border:0;padding:0;background-color:transparent;';
  button.addEventListener('click', callback); item.append(button); return button;
}
function fc_recordSelectReset(select) {
  const instance = window.fc_choices_instances?.[select.id || select.name];
  if (instance) instance.removeActiveItems();
  select.value = '';
  // Select2 remains optional for existing layouts which request it.
  if (!window.fc_use_choicesjs && window.jQuery?.fn.select2 && select.classList.contains('use_select2_lib')) window.jQuery(select).select2('val', '');
  FlexiForm.change(select);
}
function fcfield_add_sortable_element(value) {
  const select = FlexiForm.element(value), id = select.id.replace('_selector', '');
  if (!select.value) return true;
  const list = document.getElementById('sortable-' + id), item = document.createElement('li');
  item.className = 'fcrecord'; item.dataset.value = 'field_' + select.value;
  const label = document.createElement('span'); label.className = 'fcprop_box'; label.textContent = select.selectedOptions[0].textContent;
  item.append(label); fc_recordButton(item, 'delfield_handle', FlexiForm.text('FLEXI_REMOVE'), () => fcfield_del_sortable_element(item.firstChild));
  list.append(item); fcfield_store_ordering(list); fc_recordSelectReset(select); return true;
}
function fcfield_attach_sortable(selector = 'body') {
  FlexiForm.element(selector).querySelectorAll('ul.fcfields_list').forEach(list => {
    fcfield_store_ordering(list);
    if (!Sortable.get(list)) Sortable.create(list, {draggable: 'li', onEnd: () => fcfield_store_ordering(list)});
  });
}
function fcrecord_del_sortable_element(value) {
  const item = FlexiForm.element(value).closest('li'), list = item.parentElement;
  item.remove(); fcrecord_store_values(list);
}
function fcrecord_toggle_textarea_edit(value) {
  const area = FlexiForm.element(value).querySelector('textarea'); area.readOnly = !area.readOnly;
}
function fcrecord_direct_edit(value, in_modal = 0) {
  const list = FlexiForm.element(value), area = document.getElementById(FlexiForm.data(list, 'value_element_id'));
  area.readOnly = false;
  if (in_modal) {
    fc_field_dialog_handle_fcrecord_list = fc_showAsDialog(area.parentElement, null, null, fcrecord_toggle_textarea_edit, {visibleOnClose: 1, title: FlexiForm.text('FLEXI_EDIT')});
  } else {
    area.style.height = ''; area.style.width = ''; area.parentElement.style.height = ''; area.parentElement.style.width = '';
    FlexiForm.show(area.parentElement);
  }
  FlexiForm.show(list.parentElement, false);
}
function fcrecord_toggle_details_btns(value, visible) {
  const button = FlexiForm.element(value); if (!button) return;
  button.parentElement.querySelectorAll('.fcrecords_show_btn').forEach(el => FlexiForm.show(el, !visible));
  button.parentElement.querySelectorAll('.fcrecords_hide_btn').forEach(el => FlexiForm.show(el, !!visible));
}
async function fcrecord_ui_edit(value, in_modal = 0, btn_show) {
  const list = FlexiForm.element(value), box = list.parentElement, F = FlexiForm;
  if (!in_modal && F.visible(box)) { fcrecord_toggle_details_btns(btn_show, 0); F.show(box, false); return; }
  fc_recordLoads.get(list)?.abort();
  const controller = new AbortController(); fc_recordLoads.set(list, controller);
  list.replaceChildren(); F.show(list, false);
  const area = document.getElementById(F.data(list, 'value_element_id'));
  area.readOnly = true; area.style.height = '64px';
  const message = (selector, text) => { const el = box.querySelector(selector); if (el) { el.textContent = text; F.show(el, !!text); } };
  message('.fcrec_general_msg', Array.from(box.querySelectorAll('.fcrecord_label.fcrec_unused:not(.fcrec_cascaded_col)'), el => el.textContent + ' ' + F.text('FLEXI_INDEXED_FIELD_UNUSED_COL_DISABLED').replace(/\\/g, '')).join('\n'));
  if (in_modal) fc_field_dialog_handle_fcrecord_list = fc_showAsDialog(box, null, null, null, {title: F.text('FLEXI_EDIT')});
  else { box.style.height = ''; F.show(box); }
  fcrecord_toggle_details_btns(btn_show, !in_modal);
  const stateName = F.data(list, 'state_fieldname');
  const state = stateName && document.getElementById('jform_attribs_' + stateName)?.querySelector('input:checked');
  const enabled = !!Number(state?.value);
  F.data(list, 'use_elements_state', enabled ? '1' : null);
  message('.fcrec_state_msg', enabled ? '' : F.text('FLEXI_INDEXED_FIELD_STATE_COL_DISABLED').replace(/\\/g, ''));
  box.querySelectorAll('.fcrec_state_col').forEach(el => el.classList.toggle('fcrec_unused', !enabled));
  const masterName = F.data(list, 'master_fieldname');
  const masterId = Number(masterName && document.getElementById('jform_attribs_' + masterName)?.value) || 0;
  F.data(list, 'master_elements', null);
  message('.fcrec_cascaded_msg', masterId ? '' : F.text('FLEXI_INDEXED_FIELD_VALGRP_COL_DISABLED').replace(/\\/g, ''));
  box.querySelectorAll('.fcrec_cascaded_col').forEach(el => el.classList.toggle('fcrec_unused', !masterId));
  if (masterId) {
    const loading = document.createElement('span'); loading.className = 'fc_loading_msg'; loading.textContent = 'Loading...'; list.after(loading);
    try {
      const url = new URL((window.jbase_url_fc || '') + 'index.php', document.baseURI);
      url.search = new URLSearchParams({option:'com_flexicontent',task:'fields.getIndexedFieldJSON',format:'raw',field_id:masterId});
      const response = await fetch(url, {credentials:'same-origin',signal:controller.signal});
      if (!response.ok) throw Error('Could not load field values');
      const values = await response.json(); F.data(list, 'master_elements', values);
    } catch(error) {
      if (error.name === 'AbortError') return;
      message('.fcrec_cascaded_msg', error.message);
    } finally { loading.remove(); }
  }
  if (controller.signal.aborted) return;
  fcrecord_populate_list_elements(list); F.show(list);
}
function fcrecord_populate_list_elements(value) {
  const list = FlexiForm.element(value), data = key => FlexiForm.data(list,key);
  const values = document.getElementById(data('value_element_id')).value.split(new RegExp('\\s*'+data('record_sep')+'\\s*'));
  list.replaceChildren(); values.filter(Boolean).forEach(value => fcrecord_add_sortable_element_ext(list, 1, value, 1));
}
function fcrecord_add_sortable_element_ext(value, placement, record_value = '', shown = 1) {
  const target = FlexiForm.element(value), list = target.tagName === 'UL' ? target : target.closest('ul');
  const data = key => FlexiForm.data(list,key), text = FlexiForm.text;
  const cascade = data('cascaded_prop') === undefined || data('cascaded_prop') === '' ? -1 : Number(data('cascaded_prop'));
  const state = data('state_prop') === undefined || data('state_prop') === '' ? -1 : Number(data('state_prop'));
  const masters = data('master_elements'), useState = data('use_elements_state');
  const props = record_value.split(new RegExp('\\s*'+data('props_sep')+'\\s*'));
  const used = String(data('props_used')).split(/\s*,\s*/), widths = String(data('prop_widths')).split(/\s*,\s*/);
  const item = document.createElement('li'); item.className = 'fcrecord';
  if (data('is_elements')) used.forEach((inUse,index) => {
    const value = props[index] || '';
    const unused = !Number(inUse) || (index === cascade && !masters) || (index === state && !useState);
    let input;
    if ((index === cascade && masters) || (index === state && useState)) {
      input = document.createElement('select'); input.append(new Option('-', ''));
      if (index === cascade) {
        if (value && !masters.some(row => String(row.value) === value)) input.append(new Option(value+' [CURRENT]',value));
        masters.forEach(row => input.append(new Option(row.text, row.value))); input.classList.add('master_prop');
      } else {
        [['1','FLEXI_PUBLISHED'],['0','FLEXI_UNPUBLISHED'],['9','FLEXI_EXPIRED'],['2','FLEXI_ARCHIVED'],['-2','FLEXI_TRASHED']].forEach(([value,key]) => input.append(new Option(text(key),value)));
        input.classList.add('state_prop');
      }
    } else { input = document.createElement('input'); input.type = 'text'; input.readOnly = unused; if (unused) input.placeholder = text('FLEXI_NA'); }
    input.value = value; input.classList.add('fcrecord_prop'); if (unused) input.classList.add('fcrec_unused');
    if (widths[index]) input.style.setProperty('width', widths[index], 'important');
    input.addEventListener('change', () => fcrecord_store_values(list)); input.addEventListener('blur', () => fcrecord_store_values(list));
    item.append(input);
  });
  else { const label = document.createElement('span'); label.className='fcprop_box'; label.textContent='empty'; item.append(label); }
  fc_recordButton(item,'delfield_handle',text('FLEXI_REMOVE'),() => fcrecord_del_sortable_element(item.firstChild));
  if(data('add_after')) fc_recordButton(item,'addfield_handle fc_after',text('FLEXI_ADD'),() => fcrecord_add_sortable_element_ext(item,1));
  if(data('add_before')) fc_recordButton(item,'addfield_handle fc_before',text('FLEXI_ADD'),() => fcrecord_add_sortable_element_ext(item,0));
  if(data('is_elements')) { const handle=document.createElement('span'); handle.className='ordfield_handle'; handle.title=text('FLEXI_ORDER'); item.append(handle); }
  target === list ? list.append(item) : target[placement ? 'after' : 'before'](item);
  FlexiForm.show(item,!!shown);
  return item;
}
function fcrecord_add_sortable_element(value) {
  const select=FlexiForm.element(value), list=document.getElementById('sortable-'+select.id.replace('_selector',''));
  if(!select.value) return;
  const item=document.createElement('li'); item.className='fcrecord'; item.dataset.value=select.value;
  const label=document.createElement('span'); label.className='fcprop_box'; label.textContent=select.selectedOptions[0].textContent; item.append(label);
  fc_recordButton(item,'delfield_handle',FlexiForm.text('FLEXI_REMOVE'),() => fcrecord_del_sortable_element(label));
  list.append(item); fcrecord_store_values(list); fc_recordSelectReset(select);
}
function fcrecord_store_values(value) {
  const list=FlexiForm.element(value), data=key=>FlexiForm.data(list,key), values=[];
  for(const item of list.children) {
    if(data('is_elements')) {
      const props=Array.from(item.querySelectorAll('.fcrecord_prop'),el=>el.value.trim() || '_NA_');
      while(props.at(-1)==='_NA_') props.pop();
      if(props.length) values.push(props.join(data('props_sep')));
    } else values.push(item.dataset.value);
  }
  const input=document.getElementById(data('value_element_id'));
  input.value=values.join(data('record_sep')+(data('is_elements')?'\n':'')); FlexiForm.change(input);
}
function fcrecord_attach_sortable(selector='body') {
  FlexiForm.element(selector).querySelectorAll('ul.fcrecords_list').forEach(list => {
    if(!FlexiForm.data(list,'is_elements')) fcrecord_store_values(list);
    if(!Sortable.get(list)) Sortable.create(list,{draggable:'li',filter:'input,select,textarea,button',preventOnFilter:false,onEnd:()=>fcrecord_store_values(list)});
  });
}
FlexiForm.ready(() => { fcrecord_attach_sortable(); fcfield_attach_sortable(); });
