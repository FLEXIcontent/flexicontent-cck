/** Related item selectors for Choices.js and the separate category/item pickers. */
window.fcfield_relation = (() => {
  const boxSelector = '.fcfield-relation-value_box';
  const itemsSelector = 'select.fcfield-relation-item_selector';
  const selectedSelector = 'select.fcfield-relation-selected_items';
  const categorySelector = 'select.fcfield-relation-cat_selector';
  const pickerRequests = new WeakMap();
  const searchRequests = new WeakMap();

  function usesRemote(select) {
    return window.fc_use_choicesjs && !window.skip_select2_js && select.multiple && select.hasAttribute('data-fc_choices_search_remote');
  }

  function hideLegacyPickers(scope = document) {
    const boxes = scope.matches?.(boxSelector) ? [scope] : scope.querySelectorAll(boxSelector);
    for (const box of boxes) {
      if (!usesRemote(box.querySelector(selectedSelector))) continue;
      for (const element of box.querySelectorAll('.fcfield-relation-item_selector_box, .fcfield-relation-order_hint')) {
        element.style.setProperty('display', 'none', 'important');
        element.hidden = true;
      }
    }
  }

  function refreshSelect(select) {
    if (select.fc_choices_instance) select.fc_choices_instance.refresh();
    else if (window.jQuery?.fn.select2) window.jQuery(select).trigger('change.select2');
  }

  function markSelected(box) {
    const selected = box.querySelector(selectedSelector);
    const picker = box.querySelector(itemsSelector);
    const ids = new Set([...selected.selectedOptions].map(option => option.value.split(':')[0]));
    for (const option of picker.options) option.disabled = ids.has(option.value);
    picker.value = '';
    refreshSelect(picker);
  }

  function addRelated(picker) {
    if (!(Number(picker.value) > 0)) return false;
    const box = picker.closest(boxSelector);
    const selected = box.querySelector(selectedSelector);
    const value = picker.value + ':' + box.querySelector(categorySelector).value;
    if (![...selected.selectedOptions].some(option => option.value.split(':')[0] === picker.value)) {
      selected.append(new Option(picker.selectedOptions[0].text, value, true, true));
      FlexiForm.change(selected);
    }
    return true;
  }

  function selectedItemsModified(select) {
    if (!usesRemote(select)) {
      for (const option of [...select.options]) {
        if (!option.selected) option.remove();
      }
      refreshSelect(select);
      markSelected(select.closest(boxSelector));
    }
    return true;
  }

  function serializeFilters(box) {
    const params = new URLSearchParams();
    const filters = box.closest('.container_fcfield')?.querySelectorAll('select.fc_field_filter, input.fc_field_filter') || [];
    for (const filter of filters) {
      if (!filter.name || filter.matches(':disabled, input[type=checkbox]:not(:checked), input[type=radio]:not(:checked)')) continue;
      if (filter.matches('select')) {
        for (const option of filter.selectedOptions) {
          if (!option.disabled && !option.closest('optgroup[disabled]')) params.append(filter.name, option.value);
        }
      } else {
        params.append(filter.name, filter.value);
      }
    }
    return params.toString();
  }

  async function requestItems(box, category, signal) {
    const { field_id, item_id, item_type, item_lang } = box.dataset;
    const url = new URL('index.php', window.jbase_url_fc || document.baseURI);
    url.search = new URLSearchParams({ option:'com_flexicontent', tmpl:'component', format:'raw' });
    const token = window['sessionToken' + field_id];
    if (token) url.searchParams.set(token, '1');
    const body = new URLSearchParams({
      task:'call_extfunc', omethod:'html', exttype:'plugins', extfolder:'flexicontent_fields',
      extname:'relation', extfunc:'getCategoryItems', field_id, catid:category, customfilts:serializeFilters(box),
    });
    if (Number(item_id)) body.set('item_id', item_id);
    else {
      body.set('type_id', item_type);
      body.set('lang_code', item_lang);
    }
    const response = await fetch(url, {
      method:'POST', body, signal, headers:{ 'X-Requested-With':'XMLHttpRequest' },
    });
    if (!response.ok) throw new Error('Related item request failed');
    return response.json();
  }

  async function searchItems(elementId, query, callback) {
    const select = document.getElementById(elementId);
    const box = select?.closest(boxSelector);
    if (!box) return callback?.([]);
    searchRequests.get(select)?.abort();
    const category = Number(box.querySelector(categorySelector).value);
    if (!category) return callback?.([]);
    const request = new AbortController();
    searchRequests.set(select, request);
    try {
      const data = await requestItems(box, category, request.signal);
      if (request.signal.aborted || !select.isConnected) return;
      const term = String(query || '').toLowerCase();
      const options = data?.error ? [] : (data?.options || [])
        .filter(option => String(option.item_title || '').toLowerCase().includes(term))
        .map(option => ({ value: option.item_id + ':' + category, label: option.item_title }));
      callback?.(options, data);
    } catch (error) {
      if (!request.signal.aborted && select.isConnected) callback?.([], null);
    }
  }

  function showPickerError(picker, message) {
    const error = document.createElement('span');
    error.className = 'add-on fc-relation-field-error';
    error.setAttribute('role', 'status');
    error.textContent = message;
    const display = picker.fc_choices_instance?.containerOuter.element || document.getElementById('s2id_' + picker.id) || picker;
    display.after(error);
  }

  async function loadPicker(box, category) {
    const picker = box.querySelector(itemsSelector);
    const wrapper = picker.closest('.fcfield-relation-item_selector_box');
    wrapper.querySelector('.fc-relation-field-error')?.remove();
    pickerRequests.get(picker)?.abort();
    const request = new AbortController();
    pickerRequests.set(picker, request);
    picker.replaceChildren(new Option('-', ''));
    refreshSelect(picker);
    if (!category) {
      wrapper.removeAttribute('aria-busy');
      return;
    }
    wrapper.setAttribute('aria-busy', 'true');
    try {
      const data = await requestItems(box, category, request.signal);
      if (request.signal.aborted || !picker.isConnected) return;
      if (data?.error) {
        showPickerError(picker, data.error);
        return;
      }
      const options = data?.options || [];
      picker.options[0].text = FlexiForm.text(options.length ? 'FLEXI_RIFLD_ADD_ITEM' : 'FLEXI_RIFLD_NO_ITEMS');
      for (const option of options) picker.add(new Option(option.item_title, option.item_id));
      markSelected(box);
    } catch (error) {
      if (!request.signal.aborted && picker.isConnected) showPickerError(picker, FlexiForm.text('FLEXI_RIFLD_ERROR'));
    } finally {
      if (pickerRequests.get(picker) === request) wrapper.removeAttribute('aria-busy');
    }
  }

  function categoryChanged(category) {
    const box = category?.closest(boxSelector);
    if (!box) return false;
    const selected = box.querySelector(selectedSelector);
    if (usesRemote(selected)) {
      searchRequests.get(selected)?.abort();
      selected.dispatchEvent(new Event('fc:relation-options'));
    } else {
      loadPicker(box, Number(category.value));
    }
    return true;
  }

  function filtersChanged(filter) {
    for (const category of filter.closest('.container_fcfield')?.querySelectorAll(categorySelector) || []) {
      if (Number(category.value)) categoryChanged(category);
    }
  }

  function prepare(row, ref, config) {
    const box = row.querySelector(boxSelector);
    if (!box) return false;
    box.dataset.elementid = ref.id;
    const category = box.querySelector(categorySelector);
    const picker = box.querySelector(itemsSelector);
    const selected = box.querySelector(selectedSelector);
    const defaults = config.singleCategory ? [category.options[1]?.value || ''] : category.dataset.defvals?.split('|||') || [''];
    picker.replaceChildren(new Option('-', ''));
    selected.replaceChildren();
    FlexiFieldRepeater.setControl(row, category, ref.id + '_cat_selector', ref.id + '_cat_selector', defaults);
    FlexiFieldRepeater.setControl(row, picker, ref.id + '_item_selector', ref.id + '_item_selector', '');
    FlexiFieldRepeater.setControl(row, selected, ref.id, ref.name + '[]', '');
    box.querySelector('label.cat_selector-lbl').id = category.id + '-lbl';
    box.querySelector('label.item_selector-lbl').id = picker.id + '-lbl';
    box.querySelector('label.selected_items-lbl').id = selected.id + '-lbl';
    box.querySelector('.fc-relation-field-error')?.remove();
    picker.closest('.fcfield-relation-item_selector_box').removeAttribute('aria-busy');
    const holder = row.querySelector('.fcfield_value_holder');
    if (holder) FlexiFieldRepeater.setControl(row, holder, config.holderId + '_' + ref.index, config.holderName + '[' + ref.index + ']', '-');
  }

  function ready(row, ref, config) {
    hideLegacyPickers(row);
    const box = row.querySelector(boxSelector);
    if (config.singleCategory && !usesRemote(box.querySelector(selectedSelector))) categoryChanged(box.querySelector(categorySelector));
  }

  window.fc_fetch_relation_choices = searchItems;
  document.addEventListener('DOMContentLoaded', () => hideLegacyPickers(), { once:true });
  return {
    prepare, ready, hideLegacyPickers, search_items:searchItems, add_related:addRelated,
    selected_items_modified:selectedItemsModified, cat_selector_change:categoryChanged, filters_change:filtersChanged,
  };
})();
