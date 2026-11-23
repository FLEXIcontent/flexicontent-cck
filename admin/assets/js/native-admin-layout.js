/** Preserve real table semantics while allowing wide lists to scroll by keyboard. */
(function () {
  'use strict';
  function initialize() {
    document.querySelectorAll('#flexicontent table.fcmanlist').forEach(table => {
      if (table.closest('.fc-table-scroll')) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'fc-table-scroll';
      wrapper.tabIndex = 0;
      wrapper.setAttribute('role', 'region');
      wrapper.setAttribute('aria-label', Joomla.Text._('FLEXI_SCROLL_TABLE'));
      table.before(wrapper);
      wrapper.append(table);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
  else initialize();
})();
