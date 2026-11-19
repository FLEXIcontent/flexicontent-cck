# Native editor workflow regression tests

Run `node tests/native-editor-workflows.cjs` with Playwright and its Chromium browser installed. The suite intercepts its fixture HTTP requests; it needs no Joomla database and does not contact a live site.

Set `FLEXI_TEST_JQUERY` to a Joomla installation's `media/vendor/jquery/js/jquery.min.js` to also run the legacy AJAX hook, synthetic change event and Select2 compatibility cases. Every other case asserts that `window.jQuery` is absent.

The cases exercise required and optional validation, checkbox and category limits, hidden editor synchronization, idle upload queues, large-form serialization, AJAX recovery, cancellation, toolbar POST data, record insertion and removal, drag ordering and group boundaries, batch field loading, native dialogs, ordered dialog scripts, form ownership and focus restoration. Further checks exercise dependent settings, Text value controls and cloning a live Choices widget.

These are browser tests of the shared helpers. Successful POST interception does not prove a Joomla database save, authorization, or file upload. Those operations also need an installed-site regression run with representative content types and field settings.

## Migration boundary

The validator keeps the same exported methods and `custom` registry. Internal routines such as `handleResponse` and `findLabel` were private in the previous implementation. Field plugins can use `validate`, `isValid`, `setHandler` and `attachToForm` to integrate with the validator.

This change moves shared submission/validation, record-list controls, dialogs, manager ordering, batch controls and common form initialization to native APIs. It also constructs uploader controllers before field-ready callbacks can invoke them. It preserves `dialog('close')` handles and optional jQuery AJAX/data bridges for existing integrations.

The shared administrator dependency engine and the Text and Text Select repeatable controls use native DOM events. Legacy jQuery events still reach dependent selects when Select2 or an older plugin sends them. Text Select uses the existing Choices integration and supports the legacy Select2 setting.

jQuery loaders remain enabled for field groups, other field-specific repeaters, cascaded value selectors, tag autocomplete, Plupload widgets, Inputmask initialization, legacy Select2, color and subform plugins, and PHP-generated scripts in editor and manager views. Third-party field plugins and site overrides are outside this patch.
