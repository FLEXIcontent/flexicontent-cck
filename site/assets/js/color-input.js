/** A native picker supplements the saved CSS string; rendering never rewrites it. */
(() => {
  'use strict';
  const initialized = new WeakSet();
  function init(container) {
    container.querySelectorAll('[data-fc-color-input]').forEach(box => {
      if (initialized.has(box)) return;
      initialized.add(box);
      const value = box.querySelector('input[type=text]');
      const picker = box.querySelector('input[type=color]');
      const alpha = box.querySelector('input[type=range]');
      const sync = () => {
        if (!value.value || !CSS.supports('color', value.value)) return;
        const probe = document.createElement('span');
        probe.style.color = value.value; probe.hidden = true; box.append(probe);
        const channels = getComputedStyle(probe).color.match(/[\d.]+/g)?.map(Number);
        probe.remove();
        if (!channels || channels.length < 3) return;
        picker.value = '#' + channels.slice(0,3).map(n => Math.round(n).toString(16).padStart(2,'0')).join('');
        if (alpha) alpha.value = channels[3] ?? 1;
      };
      const update = () => {
        const channels = picker.value.match(/[a-f0-9]{2}/gi).map(n=>parseInt(n,16));
        value.value = alpha ? `rgba(${channels.join(', ')}, ${alpha.value})`
          : box.dataset.format === 'rgb' ? `rgb(${channels.join(', ')})` : picker.value;
        value.dispatchEvent(new Event('input',{bubbles:true}));
        value.dispatchEvent(new Event('change',{bubbles:true}));
      };
      picker.addEventListener('input',update); alpha?.addEventListener('input',update);
      value.addEventListener('input',sync); sync();
    });
  }
  window.fc_initColorInputs = init;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',()=>init(document),{once:true});
  else init(document);
  document.addEventListener('subform-row-add',event=>init(event.detail?.row || event.target));
  document.addEventListener('joomla:updated',event=>init(event.target));
})();
