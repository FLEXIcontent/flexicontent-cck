/** Remember each module's active Bootstrap tab or accordion panel. */
window.fc_bindModuleTabs = function (config) {
  function init() {
    const root = document.getElementById(config.id + (config.tabs ? 'Tabs' : ''));
    if (!root || root.dataset.fcTabsBound) return;
    root.dataset.fcTabsBound = '1';
    const object = value => value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    function save(id) {
      let saved;
      try { saved = object(JSON.parse(fclib_getCookie('fc_modules_data'))); } catch (error) { saved = {}; }
      const module = saved[config.moduleId] = object(saved[config.moduleId]);
      const group = module[config.group] = object(module[config.group]);
      group[config.id] = id;
      fclib_setCookie('fc_modules_data', JSON.stringify(saved), 7);
    }
    if (config.tabs) {
      root.addEventListener('shown.bs.tab', event => {
        const target = event.target.getAttribute('data-bs-target') || event.target.hash;
        if (target?.startsWith('#')) save(target.slice(1));
      });
    } else {
      root.addEventListener('shown.bs.collapse', event => {
        if (event.target.closest('.accordion') === root) save(event.target.id);
      });
      root.addEventListener('hidden.bs.collapse', event => {
        if (event.target.closest('.accordion') !== root) return;
        // Closing an old panel must not overwrite the newly opened panel's state.
        const active = [...root.querySelectorAll('.collapse.show, .collapsing')]
          .find(panel => panel !== event.target && panel.closest('.accordion') === root);
        save(active?.id || null);
      });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
  else init();
};
