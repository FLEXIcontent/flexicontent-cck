# Native editor workflow regression tests

Run `node tests/native-editor-workflows.cjs` with Playwright and its Chromium browser installed. The suite intercepts its fixture HTTP requests; it needs no Joomla database and does not contact a live site.

Set `FLEXI_TEST_JQUERY` to a Joomla installation's `media/vendor/jquery/js/jquery.min.js` to also run the legacy AJAX hook, grouped field callbacks, synthetic form and cascade events, and Select2 compatibility cases. Every other case asserts that `window.jQuery` is absent.

The cases exercise required and optional validation, checkbox and category limits, hidden editor synchronization, idle upload queues, large-form serialization, AJAX recovery, cancellation, toolbar POST data, record insertion and removal, drag ordering and group boundaries, batch field loading, native dialogs, ordered dialog scripts, form ownership and focus restoration. Further checks exercise dependent settings, Text value controls and cloning a live Choices widget. Tag checks cover keyboard selection, quick tags, removal and assignment errors. Cascade checks cover delayed responses, Choices option refreshes, radio chains and legacy multi-select changes.

These are browser tests of the shared helpers. Successful POST interception does not prove a Joomla database save, authorization, or file upload. Those operations also need an installed-site regression run with representative content types and field settings.

## Migration boundary

The validator keeps the same exported methods and `custom` registry. Internal routines such as `handleResponse` and `findLabel` were private in the previous implementation. Field plugins can use `validate`, `isValid`, `setHandler` and `attachToForm` to integrate with the validator.

This change moves shared submission/validation, record-list controls, dialogs, manager ordering, batch controls and common form initialization to native APIs. It also constructs uploader controllers before field-ready callbacks can invoke them. It preserves `dialog('close')` handles and optional jQuery AJAX/data bridges for existing integrations.

The shared administrator dependency engine and the Text and Text Select repeatable controls use native DOM events. Legacy jQuery events still reach dependent selects when Select2 or an older plugin sends them. Text Select uses Choices; legacy Select2 event adapters remain for external integrations.

The item editor and CSV import share a native tag picker. Cascaded selectors use native events and fetch. PrettyCheckable rendering and synthetic jQuery changes still have compatibility hooks when an older widget is enabled.

Field group add, remove, compact controls and drag ordering use native DOM APIs and Sortable. Grouped Text and Text Select fields receive DOM nodes. Grouped field plugins with older add or remove callbacks still receive jQuery collections when jQuery is available. The browser tests cover paired values, row-specific conditions, limits, reset of the final row, and a cancelled legacy add.

The native path also covers field repeaters, Plupload controllers, Inputmask, color inputs, Joomla subforms, version history, manager filters, layout parameter loading and CSV import. Choices mode does not load jQuery for these tested paths. Native color fields preserve CSS text and alpha values without Joomla's jQuery MiniColors widget. Joomla 5+ dialogs and messages are loaded through the Web Asset Manager; older installations retain the dialog fallback.

Optional gallery/carousel plugins and third-party overrides can still request jQuery. The `select2` loader name now resolves to Choices and the PrettyCheckable loader uses native inputs; compatibility hooks remain when an external integration provides an older widget. Recent favourites, module tab persistence and Flexibreak are native. This is not a declaration that every bundled feature or configuration is jQuery-independent. See the [Joomla 6 lab audit](JOOMLA6-NATIVE-AUDIT.md) for tested scope and outstanding work.

## Additional suites

Run the following with Playwright installed. Set `FLEXI_TEST_JOOMLA` to an installed Joomla 6 root for the real dialog assets. The browser CI workflow downloads a pinned Joomla 6.2.0 package and checks its SHA-256 before extracting those assets.

```sh
node tests/native-joomla-dialogs.cjs
node tests/native-maintenance.cjs
node tests/native-admin-workflows.cjs
node tests/native-input-masks.cjs
node tests/native-color-input.cjs
node tests/native-media-uploader.cjs
node tests/native-public-waveform.cjs
node tests/image-media-clone.cjs
node tests/native-legacy-helpers.cjs
node tests/native-public-widgets.cjs
php tests/manager-checkin-controls.php
php tests/favourites-controller.php
php tests/gallery-options.php
php tests/flexibreak-layouts.php
php tests/module-tab-state.php
php tests/optional-jquery-assets.php fancybox
php tests/optional-jquery-assets.php fancybox manual
```

The manager suite covers history pagination and failed requests, stale layout requests, parent-dialog submission, empty-selection alerts, and asynchronous file previews. Mask cases use fresh controls and actual keyboard/paste events, including immediate focus followed by typing and tab-then-click entry. The shipped Inputmask patch is documented alongside the vendor source. Browser fixtures test behavior; the installed-site audit separately verifies persisted database values.

The follow-up suites cover delayed Joomla editor-registry removal, restored Choices values, safe recent-favourites rendering, AJAX failure messages, module state isolation, pagebreak navigation, comparison-gallery settings/resizing, conditional legacy dependency loading and typed FancyBox options. The [code index](NATIVE-CODE-INDEX.md) links all changed JavaScript files.
