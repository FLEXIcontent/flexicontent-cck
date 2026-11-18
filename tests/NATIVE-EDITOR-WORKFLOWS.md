# Native editor workflow regression tests

Run `node tests/native-editor-workflows.cjs` with Playwright and its Chromium browser installed. The suite intercepts its fixture HTTP requests; it needs no Joomla database and does not contact a live site.

Set `FLEXI_TEST_JQUERY` to a Joomla installation's `media/vendor/jquery/js/jquery.min.js` to also run the legacy AJAX-hook and Select2 compatibility cases. Without that variable, those two cases are omitted. Every other case asserts that `window.jQuery` is absent.

The cases exercise required and optional validation, checkbox and category limits, hidden editor synchronization, idle upload queues, large-form serialization, AJAX failure recovery, cancellation, toolbar POST data, record insert/remove, drag ordering and group boundaries, batch field loading, native dialogs, ordered dialog scripts, form ownership and focus restoration. The optional compatibility cases use the actual jQuery and bundled Select2 libraries.

These are browser tests of the shared helpers. Successful POST interception does not prove a Joomla database save, authorization, or file upload. Those operations also need an installed-site regression run with representative content types and field settings.

## Migration boundary

This change moves shared submission/validation, record-list controls, dialogs, manager ordering, batch controls and common form initialization to native APIs. It also constructs uploader controllers before field-ready callbacks can invoke them. It preserves `dialog('close')` handles and optional jQuery AJAX/data bridges for existing integrations.

jQuery loaders remain enabled. Field-specific repeaters, conditional form dependencies, Plupload's jQuery widgets, legacy Select2, legacy color/subform plugins, and PHP-generated initialization scripts still require further work. A native helper does not make the complete editor page independent of jQuery. Third-party field plugins and site overrides are outside this patch.
