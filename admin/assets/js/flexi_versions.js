/** Version history: preserve permanent backups, comparison dialogs and paging. */
FlexiForm.ready(() => {
  document.querySelectorAll('[data-fc-versions-panel]').forEach(panel => {
    if (panel.dataset.fcVersionsInit) return;
    panel.dataset.fcVersionsInit = 'true';
    let current = Number(panel.dataset.currentPage) || 1;
    const total = Number(panel.dataset.pagecount) || 1;
    const table = panel.querySelector('[data-fc-versions-table]');
    const pager = panel.querySelector('#fc_pager');
    let pending;
    const tools = () => fc_initTooltips(null, panel);
    function display() {
      if (!pager || total < 2) return;
      const list = document.createElement('ul'); list.className = 'pagination flex-wrap';
      const button = (label, page) => {
        const item = document.createElement('li'); item.className = 'page-item';
        const node = document.createElement('button'); node.type = 'button'; node.className = 'page-link';
        node.textContent = label; node.disabled = page < 1 || page > total || page === current;
        if (page === current) node.setAttribute('aria-current','page');
        node.addEventListener('click',()=>load(page)); item.append(node); list.append(item);
      };
      button('\u2039', current-1);
      const start = Math.max(1,Math.min(current-4,total-9));
      for(let n=start;n<=Math.min(total,start+9);n++)button(String(n),n);
      button('\u203a',current+1); pager.replaceChildren(list);
    }
    async function load(page) {
      if (!panel.dataset.ajaxUrl) return;
      pending?.abort(); const controller = pending = new AbortController();
      const url = new URL(panel.dataset.ajaxUrl, document.baseURI); url.searchParams.set('page',page);
      panel.setAttribute('aria-busy','true');
      try {
        const response = await fetch(url,{credentials:'same-origin',signal:controller.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});
        if (!response.ok || response.redirected) throw Error(response.statusText || FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED'));
        const html = await response.text();
        if (controller.signal.aborted) return;
        await FlexiForm.insertHTML(table, html, controller.signal);
        current=page; display(); tools();
      } catch(error) { if(!controller.signal.aborted) FlexiForm.message('error',error.message); }
      finally { if (pending===controller)panel.removeAttribute('aria-busy'); }
    }
    panel.addEventListener('click',async event=>{
      const restore=event.target.closest('.fc-version-restore');
      const keep=event.target.closest('a.fc-version-keep');
      if(restore){
        event.preventDefault();
        if(restore.dataset.restoreUrl && (!panel.dataset.confirmRestore || await FlexiForm.confirm(panel.dataset.confirmRestore)))location.href=restore.dataset.restoreUrl;
      }
      if(keep){
        event.preventDefault(); if(keep.getAttribute('aria-busy')==='true')return;
        keep.setAttribute('aria-busy','true');
        try {
          const response=await fetch(keep.dataset.keepUrl,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
          if(!response.ok)throw Error(response.statusText);
          const data=await response.json();
          if(!data?.success)throw Error(data?.message || FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED'));
          await load(current);
        }catch(error){FlexiForm.message('error',error.message);}
        finally{keep.removeAttribute('aria-busy');}
      }
    });
    const comment=document.getElementById('versioncomment');
    if(comment){const resize=()=>{comment.style.height='auto';comment.style.height=Math.min(250,Math.max(32,comment.scrollHeight))+'px';};comment.addEventListener('input',resize);resize();}
    display();tools();
  });
});
