/** Recently added favourites; update only after the server accepts a change. */
(function () {
  'use strict';
  const boxes = () => document.querySelectorAll('.mod_fc_favlist, #mod_fc_favlist');
  const escape = value => String(value ?? '').replace(/[&<>"']/g, character =>
    ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));

  window.fcfavs_list_update = function (list, target) {
    const targets = target?.nodeType ? [target] : Array.from(target || boxes());
    for (const box of targets) {
      const template = box.nextElementSibling;
      if (!template?.classList.contains('mod_fc_favlist_favblock_template')) continue;
      box.replaceChildren();
      for (const item of Object.values(list.items())) {
        if (!item || !/^\d+$/.test(String(item.id))) continue;
        let url;
        try { url = new URL(item.url, document.baseURI); } catch (error) { continue; }
        if (!['http:', 'https:'].includes(url.protocol)) continue;
        const row = document.createElement('div');
        row.className = 'fcfav_item_' + item.id;
        const values = {__ITEM_ID__: item.id, __ITEM_TITLE__: item.title, __ITEM_URL__: url.href};
        row.innerHTML = template.innerHTML.replace(/__ITEM_(?:ID|TITLE|URL)__/g, key => escape(values[key]));
        for (const link of row.querySelectorAll('.fcitem_readon a')) link.href = url.href;
        // Compatibility with the old list template, whose remove button has no server handler.
        for (const button of row.querySelectorAll('.fcfav-delete-btn')) {
          button.addEventListener('click', () => { list.remove(item.id); fcfavs_list_update(list); });
        }
        box.prepend(row);
      }
    }
  };

  function update(id, on, source) {
    if (!/^\d+$/.test(String(id))) return;
    const list = new fclib_createCookieList('fcfavs_recent');
    if (on) {
      const box = source?.closest('.fcfavs-icon_box') || source?.parentElement;
      if (!box) return;
      list.add(id, {id: String(id), title: box.querySelector('.fav_item_title')?.textContent || '',
        url: box.querySelector('.fav_item_url')?.textContent || ''});
    } else list.remove(id);
    fcfavs_list_update(list);
  }

  // Preserve the exported callback for older template overrides.
  window.favicon_clicked = function () {
    const box = this.parentElement;
    update(box?.querySelector('.fav_item_id')?.textContent, !!box?.querySelector('.fcfav_icon_off'), this);
  };

  document.addEventListener('flexicontent:favourite-changed', event => {
    const {id, type, on, source} = event.detail;
    if (type === 'item') update(id, on, source);
  });
  function init() {
    fcfavs_list_update(new fclib_createCookieList('fcfavs_recent'));
    for (const box of boxes()) {
      if (window.Sortable && !Sortable.get(box)) Sortable.create(box, {animation: 150});
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
  else init();
})();
