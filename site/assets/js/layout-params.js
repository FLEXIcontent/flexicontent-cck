/** Load a selected layout's parameter form, keeping Joomla/Choices initialization. */
async function fc_loadLayoutParams(select, initial, options) {
  if (!select || (initial && select.dataset.fcLayoutFirstRun)) return;
  const selected = select.selectedOptions[0];
  if (!selected) return;
  const filename = selected.dataset.filename || options.stripPrefix + select.value;
  if (!options.remote) {
    const base = document.querySelector('a[href="#attrib-' + options.container + '"]');
    if (base) base.parentElement.hidden = true;
    for (const name of options.layouts) {
      const tab = document.querySelector('a[href="#attrib-' + options.container + '_' + name + '"]');
      if (tab) tab.parentElement.hidden = name !== (selected.dataset.filename || select.value);
    }
    select.dataset.fcLayoutFirstRun = 'true'; return;
  }
  let header = document.querySelector('a[href="#attrib-' + options.container + '"]');
  let panel = document.getElementById('attrib-' + options.container);
  if (!header) {
    header = document.getElementById(options.container);
    const tabs = header?.closest('.tabberlive');
    if (tabs) {
      tabs.querySelectorAll(':scope > .fc_empty_box,:scope > .field-spacer').forEach(node=>node.remove());
      panel = tabs.children[Array.from(header.parentElement.parentElement.children).indexOf(header.parentElement)+1];
    }
  }
  if (!header || !panel) return;
  select.dataset.fcLayoutFirstRun = 'true';
  select.fcLayoutRequest?.abort();
  const controller = select.fcLayoutRequest = new AbortController();
  const url = new URL(options.url,document.baseURI); url.searchParams.set('layout_name',filename);
  panel.setAttribute('aria-busy','true');
  try {
    const response=await fetch(url,{credentials:'same-origin',signal:controller.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(!response.ok||response.redirected)throw Error(response.statusText||FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED'));
    const html=await response.text(); if(controller.signal.aborted)return;
    header.textContent=options.label+': '+(selected.dataset.displayname || select.value.replaceAll('_',' '));
    header.parentElement.style.display='';
    await fc_initDynamicLayoutJsCss(panel.id,['subform-row-add'],html);
  }catch(error){if(!controller.signal.aborted){delete select.dataset.fcLayoutFirstRun;FlexiForm.message('error',error.message);}}
  finally{if(select.fcLayoutRequest===controller)panel.removeAttribute('aria-busy');}
}
