/** Item version history pagination and comparison dialog. */
function fc_initItemHistory(endpoint, current, total) {
  const pager = document.getElementById('fc_pager');
  const table = document.getElementById('version_tbl');
  const comment = document.getElementById('versioncomment');
  if (comment) {
    const resize = () => {
      comment.style.height = 'auto';
      comment.style.height = Math.min(250, Math.max(32, comment.scrollHeight)) + 'px';
    };
    comment.addEventListener('input', resize);
    resize();
  }
  if (!pager || !table) return;
  let pending;
  table.addEventListener('click', event => {
    const link = event.target.closest('a.modal-versions');
    if (!link) return;
    event.preventDefault();
    fc_showDialog(link.href, 'fc_modal_popup_container');
  });

  function display() {
    if (total < 2) return;
    const list = document.createElement('ul');
    list.className = 'pagination';
    const makeButton = (label, page, selected) => {
      const item = document.createElement('li');
      item.className = 'page-item';
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'page-link';
      button.textContent = label;
      if (selected) button.setAttribute('aria-current', 'page');
      button.disabled = selected || page < 1 || page > total;
      button.addEventListener('click', () => load(page));
      item.append(button);
      return item;
    };
    list.append(makeButton('?', current - 1));
    const start = Math.max(1, Math.min(current - 4, total - 9));
    for (let page = start; page <= Math.min(total, start + 9); page++)
      list.append(makeButton(page, page, page === current));
    list.append(makeButton('?', current + 1));
    pager.replaceChildren(list);
  }

  async function load(page) {
    pending?.abort();
    const request = pending = new AbortController();
    const url = new URL(endpoint, document.baseURI);
    url.searchParams.set('page', page);
    pager.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, {credentials:'same-origin', signal:request.signal});
      if (!response.ok || response.redirected) throw Error(response.statusText);
      const html = await response.text();
      if (request.signal.aborted) return;
      const parsed = document.createElement('table');
      parsed.innerHTML = html;
      parsed.querySelectorAll('script').forEach(script => script.remove());
      if (!parsed.rows.length) throw Error(FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED'));
      table.replaceChildren(...parsed.childNodes);
      fc_initTooltips(null, table);
      current = page;
      display();
    } catch (error) {
      if (!request.signal.aborted) Joomla.renderMessages({error:[error.message || FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED')]});
    } finally {
      if (pending === request) pager.removeAttribute('aria-busy');
    }
  }
  display();
}
