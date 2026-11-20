# Follow-up to the independent JavaScript audit — 20 November 2026

Current status: the [native completion pass](JOOMLA6-NATIVE-COMPLETION.md) supersedes the remaining builder, Firefox and Joomla 6 install/upgrade gaps recorded below. This report retains the earlier test snapshot.

All 18 reported defects have fixes and targeted verification. The two high-priority failures (administrator manager buttons and the frontend New item flow) now work without jQuery. This is ready for review in existing **draft PR #1421**, subject to the coverage limits below; it is not release certification.

The independent report tested `92ad14af6`. This follow-up started at `d4952ed3d`, preserving the frontend toolbar patch, and incorporates upstream master `a5012aafda32cf4a6e45dd50886fab657d8f0d8a`. The upstream SQL-mode change and image-plugin cleanup are retained. No PR was merged or release made.

The installed lab runs **Joomla 6.2.0 / web PHP 8.3.15**. Local PHP regression commands use **8.3.30**. Earlier audit prose incorrectly described the web runtime as 8.3.30. Browsers ran headlessly in isolated contexts. Tests that changed settings restored their exact original JSON.

## Reported defects

| ID | Change and root cause | Verification |
|---|---|---|
| 01 | Replace PHP-generated jQuery attribute/dialog handlers in Items, Types, Categories, Tags and File Manager; use native manual-order and child-frame callbacks. | Eight real manager/frontend action probes pass: expected Joomla dialog, no unintended navigation or jQuery exception; Fields ordering controls become visible. |
| 02 | Read the New button's attributes natively; load the type picker's language strings on the frontend. | Author clicks New, selects Input Masks and reaches the full, translated site editor without jQuery. |
| 03 | Remove the old inline submission when installing the Categories Copy configuration listener; close through the dialog helper. | Dialog stays open without an extra POST; Cancel closes it. |
| 04 | Preserve empty currency values while formatting on blur. Reset Inputmask's cached numeric positions when the entire populated value is deleted. Stale fractional tests caused new integer digits to enter fractional slots. | Actual saved/reopened `249.95` after Ctrl+A/Delete in Chromium and Firefox, administrator and frontend. Currency/euro, negative/zero/decimal, Delete/Backspace, partial deletion and input/change events pass in fixtures. |
| 05 | Release failed/completed single-value upload slots when replacing a selection; auto-run only queued files and retain explicit failed-upload retry. | A rejected WAV is followed by a successful MP3 in the same real control; server rejection/replacement and chunk-failure/manual-retry fixtures pass. |
| 06 | Use native ready/DOM handling in FAQ, grid and shared print templates. | FAQ/grid load without errors; automatic print fires once in FAQ, grid, Search, Tags and Favourites. Five frontend surfaces pass at 390/768/1440. |
| 07 | Share field-aware upload policy between frontend controls and the server. Image fields restrict uploads to supported image extensions and require image validation, irrespective of generic document permissions. | PDF rejected by browser and server; PDF renamed PNG rejected by server; no file records created. Valid images upload, save and reopen. |
| 08 | Raise an open Choices dropdown above TinyMCE while keeping it below Joomla dialogs. | Every visible main-category option passes hit testing; before/after screenshots show the corrected stacking. |
| 09 | Remove unconditional jQuery loading on modern frontend pages, including stored default/legacy select settings; optional plugins still request their own dependency. | Temporarily set `select_lib_type=0` and `loadfw_jquery=1`: four native pages load no jQuery; FancyBox and Multibox do load it and work. Settings restored byte-for-byte. |
| 10 | Remove the undefined, unused `$jssort` output from the template layout view. | Layout editor renders without the PHP warning. |
| 11 | Initialize tag-state labels/icons and use each row's published state. | Actual tag selection dialog renders without PHP warnings. |
| 12 | Build validation summaries from cloned labels after excluding both FLEXIcontent and Joomla inline feedback. | Repeated invalid submissions retain a clean field name; the inline Joomla help remains intact. Actual form and markup fixture pass. |
| 13 | Append item IDs after truncating relation-result titles in all three result paths. | Actual remote results with identical title prefixes remain distinguishable as `(#82)` and `(#83)`. |
| 14 | Bind Multibox Escape while open and remove its listener on close; update readable and minified assets. | Actual gallery closes on Escape; both assets pass viewport/navigation regression cases. |
| 15 | Delete generated image thumbnails only after their source file is removed. Resolve contained directories, match exact size/field prefixes, and protect originals still shared by another file row. | App deletion removes all 32 audit uploads and 76 generated thumbnails. Policy fixtures cover field-specific names, WebP collisions and unrelated files. |
| 16 | Make layout-editor GET read-only. Prepare missing LESS sources and compile only on Save, including CSS-only legacy templates. | All seven tracked grid CSS/LESS files remain byte-identical on GET. A disposable CSS-only template saves and generates its required LESS/configuration files correctly. |
| 17 | Give the native file chooser its available row width with a scoped selector. | The clipped 119px control now occupies 293/420px in the tested rows; before/after screenshots retained. |
| 18 | Track Bootstrap tooltip/popover visibility; dismiss on outside pointer/focus and Escape. Handle touch pointer sequencing without reopening on synthetic hover. | Actual mobile tap opens help, then tapping an input dismisses it and focuses the input. Real Bootstrap fixture covers touch, desktop hover, keyboard focus and Escape at three widths. |

## Further defects and dependency work

- Reviews, Media data, Apps manager, category selection, Author edit and Group edit now use native initialization/filter handlers. Each actual screen loads without jQuery or JavaScript/PHP errors. Review editing was explicitly enabled temporarily to test the real form, then restored.
- Search uses native filter/reset/collapse state and autocomplete. The real autocomplete returns suggestions without jQuery UI. Date/slider filter handlers and InlineBoxes links use native DOM calls; every optional filter/output configuration is not certified.
- The upload transport now honors the configured Crop mode: PHP supplied `upload_method` while the transport expected `upload_crop`. Actual canvas tests distinguish 400×200 → 80×40 scaling from 80×80 cropping.
- Address/map output uses native DOM state. Tab activation passes the map object instead of a string and handles tabs without an ID. Google/Leaflet adapter fixtures pass; external providers remain untested.
- Deleting a template also deletes its saved layout configuration. A browser cleanup test exposed the orphaned row: recreating a folder could otherwise reuse deleted settings. Actual deletion removes the row and folder while preserving all other configurations; regression tests cover protected templates and filesystem failure.

## Verification and cleanup

| Status | Scope |
|---|---|
| Passed | Chromium create/save/reopen/edit across all nine lab content types, frontend and administrator. Dynamic TinyMCE, CodeMirror, grouped/repeatable fields, subforms, conditions, Choices, relationships, uploads and stored values are checked. |
| Passed | Firefox administrator/frontend CRUD for Input Masks, Editors & Repeatable Fields, and Files, Images & Media; additional populated-currency save/reopen in both browsers and both forms. |
| Passed | 72 PHP/Node regression commands, 12 browser/Node suites and 278 syntax checks on the final code. Browser fixtures include optional legacy compatibility checks as well as native paths. |
| Passed | Types routing change persists and is restored; Configuration loads without jQuery/MiniColors; core screens and default-settings matrix pass. |
| Passed | Final frontend editor/mask/FAQ/grid/search checks at 390, 768 and 1440 pixels: no document overflow or JavaScript/PHP errors. Existing FAQ nested scroll containers remain. |
| Passed | Removed 33 disposable items, 32 uploads, 76 thumbnails and the disposable template/configuration via application actions. Existing item titles/checkouts are unchanged. Two generated grid stylesheets had only line-ending changes and were restored to exact backup bytes. |

The earlier frontend run that deliberately blocked every jQuery request encountered the intentionally legacy gallery on its post-save home-page redirect. Those errors are not counted as a clean end-to-end pass. The complete frontend matrix was rerun with normal asset policy, while asserting that each editor itself has no jQuery; that rerun has no page errors. Earlier review-form and validation probes were superseded by the enabled-review test and corrected validation retest. A first print probe incorrectly expected a manual button although the lab uses automatic printing; the corrected test verifies the configured behavior.

The persistent field catalogue, seven extra test types, 57 named fields and 20 sample items requested by the user remain. Gallery labels identify their actual engines. These are local lab fixtures, not extension installer data.

## Screenshots

| Surface | Before | After |
|---|---|---|
| Main category / TinyMCE stacking | [Before](audit-images/followup-before-category-dropdown.png) | [After](audit-images/followup-after-category-dropdown.png) |
| Media file chooser | [Before](audit-images/followup-before-upload-127.png) | [After](audit-images/followup-after-upload-127.png) |
| Validation summary | [Before](audit-images/followup-before-validation-summary.png) | [After](audit-images/followup-after-validation-summary.png) |
| Manager layout action | [Before](audit-images/followup-before-types-layout.png) | [After](audit-images/followup-after-types-layout.png) |
| Touch help | [Open](audit-images/followup-touch-help-open.png) | [Dismissed after tapping input](audit-images/followup-touch-help-dismissed.png) |

The [cumulative audit](JOOMLA6-NATIVE-AUDIT.md) retains earlier toolbar/gallery comparisons and coverage. The [code index](NATIVE-CODE-INDEX.md) lists the complete JavaScript rewrites and integration files; the [test instructions](NATIVE-EDITOR-WORKFLOWS.md) explain isolated fixture requirements.

## Remaining boundaries

The final inventory has 2,385 matching lines in 319 files (83 authored files). Counts include comments, retained packages and guarded bridges; a text match is not an executed dependency.

Choices is the core select enhancer. Joomla Dialog and Joomla messages are used by the tested core managers/editor actions. FancyBox, Elastislide, Galleriffic, Multibox, fcxSlide and explicitly requested legacy widgets may load jQuery on demand. Guarded compatibility bridges and vendor files remain intentionally. Some direct browser alerts remain in untested legacy paths.

No known critical defect remains in the exercised flows. This does not certify every possible field option, third-party override, builder canvas or external integration. Real Safari/WebKit stability, physical devices, external maps/geocoding/comments/media services and fresh-install/upgrade/older-Joomla matrices remain outside this follow-up. Firefox coverage is the three-type matrix above, not a complete replication of Chromium coverage. Current hosted CI status must be read on the PR's exact head commit.

API decisions were checked against installed Joomla 6.2 assets and primary documentation: [Joomla Dialog](https://manual.joomla.org/docs/general-concepts/javascript/js-library/joomla-dialog/), [Bootstrap tooltip behavior](https://getbootstrap.com/docs/5.3/components/tooltips/), and [Inputmask upstream history](https://github.com/RobinHerbots/Inputmask/blob/5.x/Changelog.md). Vendor patches have adjacent upgrade notes.
