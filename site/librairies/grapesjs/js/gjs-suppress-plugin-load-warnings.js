/**
 * Legacy plugin scripts (preset-webpage 0.1.x, lory, ckeditor, bootstrap4) eagerly call
 * grapesjs.plugins.add() when they load. Since GrapesJS 0.23 that call logs a
 * console.error deprecation message (plugins still register fine), so we filter
 * just that message. Must be loaded before the plugin scripts.
 */
(function () {
  try {
    var origErr = console.error;
    var depMsg = 'grapesjs.plugins.add(...) is deprecated';
    console.error = function () {
      if (arguments.length && String(arguments[0]).indexOf(depMsg) === 0) return;
      return origErr.apply(console, arguments);
    };
  } catch (e) {}
})();