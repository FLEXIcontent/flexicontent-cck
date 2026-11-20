/** Native initialization and container resizing for the optional Juxtapose gallery. */
(function (window, document) {
  'use strict';

  function initialize() {
    if (!window.juxtapose || !window.juxtapose.JXSlider) return;
    document.querySelectorAll('.fc-juxtapose').forEach(function (element) {
      if (element.dataset.fcInitialized) return;
      var images = Array.from(element.querySelectorAll('img')).slice(0, 2).map(function (img) {
        return {src: img.src, label: img.dataset.label, credit: img.dataset.credit, alt: img.alt};
      });
      if (images.length !== 2) return;
      element.dataset.fcInitialized = 'true';
      var responsive = element.dataset.makeresponsive !== 'false';
      element.replaceChildren();
      new window.juxtapose.JXSlider('#' + element.id, images, {
        mode: element.dataset.mode === 'vertical' ? 'vertical' : 'horizontal',
        startingPosition: element.dataset.startingposition || '50%',
        animate: element.dataset.animate !== 'false',
        showLabels: element.dataset.showlabels !== 'false',
        showCredits: element.dataset.showcredits !== 'false',
        makeResponsive: responsive,
        callback: function (slider) {
          if (!responsive) return;
          var parent = element.parentElement;
          var lastWidth = 0;
          function resize() {
            var width = parent.clientWidth;
            if (!width || width === lastWidth) return;
            lastWidth = width;
            element.style.width = width + 'px';
            slider.setWrapperDimensions();
          }
          resize();
          if (window.ResizeObserver) {
            var observer = new ResizeObserver(resize);
            observer.observe(parent);
          } else {
            window.addEventListener('resize', resize);
          }
        }
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
  else initialize();
  document.addEventListener('joomla:updated', initialize);
})(window, document);
