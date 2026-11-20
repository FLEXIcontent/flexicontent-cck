# Native JavaScript completion pass — 20 November 2026

The remaining core confirmations, builder persistence and current upstream media-card controls are patched and verified. This extends the [18-defect follow-up](JOOMLA6-AUDIT-FOLLOWUP.md), preserving its fixes and the earlier frontend toolbar work. Keep PR #1421 in draft for review; the limits below prevent full release certification.

This pass started at `2ad8dfe45`. It incorporates upstream through `9372a8744`, including the database/search fixes and the new Image and Shared Media cards. Those card layouts are retained while their controls use the native controllers. The installed lab is Joomla 6.2.0 / web PHP 8.3.15; CLI checks use PHP 8.3.30. Tests used isolated headless browsers.

## Fixes and verification

| Change | Root cause and result | Evidence |
|---|---|---|
| Joomla confirmations and alerts | Returning a Promise from an inline handler does not cancel browser navigation or submission. New action/link/form helpers return false immediately, await Joomla Dialog, prevent duplicate actions and retain the original submitter and form validation. | Actual Tag Verify, three Search index actions and log purge show Joomla confirmation; Cancel makes no POST. Fixtures check accept/cancel, duplicate clicks, targeted/modified links and validation changes while a dialog is open at 390/768/1440. |
| Other core messages | Manager selection errors, item restore, public approval/delete links, reviews, media controls and builder messages use the shared Joomla helpers. Standalone voting/favourites output loads the helper dependency. | Review delete/moderation fixtures verify cancellation, one request per acceptance and retry after failure. Error messages use text rather than unsafe HTML. |
| Builder saves | Storage and Save Layout read or wrote textContent/innerHTML on form controls, losing the current textarea value and leaving hidden JSON values stale. All form-value access now uses value. | Reproduced with the actual GrapesJS builder. Load, automatic storage, manual Save Layout, Joomla Save as Copy and reopen preserve HTML/components/styles in the database. Clear Canvas cancel/accept works through Joomla Dialog. No global jQuery or JavaScript errors. |
| Image cards | Reconcile upstream icon toolbar/panels with native repeater/uploader controls. Retain Clear, keep link properties reachable when media URLs are disabled, preserve a filled URL when closing its panel and show filename feedback outside hidden panels. | Actual uploads, selection, save/reopen, keyboard Edit, URL/link values and cloned rows pass. Initial uploader opening waits for its controller, stops for removed nodes and avoids duplicate toggles. |
| Shared Media cards | Keep upstream card width and 16:9 preview. Native URL/Edit controls track expanded state; cloned data-name/data-for/handlers target the new row. Use real keyboard-accessible buttons and connected labels, keep feedback visible and handle omitted dimensions. | Both forms pass upload/media CRUD in Chromium and Firefox after the merge. A controlled noembed response exercises Fetch, metadata editing, two independent cards, save/reopen, deletion of one row and a second save. This verifies the integration code, not the live provider. |
| Responsive controls | A 540px minimum media-data width produced document overflow at tablet width. Bound that minimum by the available width. A 248px textarea minimum also extended seven pixels beyond the Shared Media card; reset its minimum and inline margins within the card. Rebuild CSS from LESS. | Actual frontend and administrator image/shared-media cards fit 390, 768 and 1440 pixels. Textarea bounds now remain inside the card in all six cases; fixture regression and before/after screenshots retained. |

## Test results

| Status | Coverage |
|---|---|
| Passed | All 74 PHP/Node/Python regression commands, 12 browser/Node suites and 292 syntax checks on the merged runtime code. The HTTP-fixture test requires local network access. |
| Passed | Firefox create/save/reopen/edit across all nine lab types in both forms. The prior follow-up supplies the complete Chromium matrix. After the Image merge, repeat Chromium on Kitchen Sink, Gallery Laboratory, Files/Images/Media and External Integration Fixtures, plus Files/Images/Media in Firefox. After the Shared Media merge, repeat Files/Images/Media in both browsers and both forms. |
| Passed | Actual populated-currency, editor, Choices, upload/retry, manager and gallery fixes from the [previous follow-up](JOOMLA6-AUDIT-FOLLOWUP.md) remain covered by the shared regression suites. Legacy galleries still request jQuery only when used; core forms assert its absence. |
| Passed | A clean Joomla 6.2 installation, actual FLEXIcontent uninstall/reinstall, and upgrade from package `5645f6eb4` in isolated installations. Baseline upgrade preserves type settings and all 39 field definitions. A repeated update preserves the actual component settings. These package checks used the Image-merged build `648709b22`; the later Shared Media-only UI changes were verified on f6 and by the suites above. |
| Passed | Database migration round-trip, FULLTEXT list/selector round-trip and upgraded field/favourites schema checks against disposable tables/database. |
| Passed | 289 overlaid/changed runtime files match the installed f6 files byte-for-byte. |
| Passed | Application cleanup removed 40 disposable items, 62 uploads, 160 thumbnails and the builder module copy. No residual owned records/relations/files remain; protected item titles/checkouts are unchanged. Temporary field settings restored exactly. The isolated database/grant, two Joomla roots and temporary server were removed. |
| Blocked | Playwright WebKit crashed during the backend Input Mask save. The data was saved; the frontend mask case passed. This does not establish real Safari support or identify the cause of the browser crash. |
| Untested | Real Safari/physical devices, older Joomla versions, live external maps/geocoding/comments/media/Filestack services and every third-party override/configuration combination. |

The persistent catalogue, seven additional types, named gallery fields and sample content remain on f6. The Article type is preserved alongside the nine tested lab types.

Failed harness attempts are retained in the local evidence. Three initial Firefox frontend cases blocked intentional jQuery on the post-save legacy-gallery home page; corrected runs assert jQuery absence on the editor and pass without page errors. A gallery driver initially sought the removed dropdown instead of the new direct file-selection button. The first Shared Media clone probe used the lab's single-value configuration; it was repeated with repeatability enabled temporarily and then restored. These failed attempts are not counted as passes. The initial component-settings snapshot selected an OSMap extension with the same element name; the corrected snapshot filters by component type and supports the repeated-update preservation claim only.

## Visual evidence

| Surface | Evidence |
|---|---|
| Tablet image card | [Before](audit-images/completion-before-image-card-768.png) / [After](audit-images/completion-after-image-card-768.png) |
| Mobile image cards | [Administrator](audit-images/completion-after-image-card-390.png) / [Frontend](audit-images/completion-after-front-image-card-390.png) |
| Image URL/link panels | [Independent cloned rows](audit-images/completion-merged-image-panels.png) |
| Builder confirmation | [Actual Joomla Dialog](audit-images/completion-builder-clear-joomla-confirm.png) |
| Shared Media cards | [Administrator mobile](audit-images/completion-sharedmedia-back-390.png) / [Frontend tablet](audit-images/completion-sharedmedia-front-768.png) |
| Shared Media textarea width | [Before](audit-images/completion-before-sharedmedia-back-390.png) / [After](audit-images/completion-sharedmedia-back-390.png) |

## Dependency boundary

The final textual inventory has 2,385 matching lines across 319 files (83 authored files); it includes comments, bundled vendor code and guarded compatibility hooks. It is not an executed-dependency count. Choices is the core select enhancer. Tested core workflows use Joomla Dialog/messages and native DOM/fetch APIs. FancyBox, Elastislide, Galleriffic, Multibox, fcxSlide and explicitly requested legacy integrations may still load jQuery on demand. Their vendor code is intentionally retained.

GrapesJS uses its own internal DOM wrapper; the tested builder did not load global jQuery. Its external asset/provider integrations are not certified. Legacy media email-share overrides remain compatibility code: their old direct route returned 403 on this Joomla 6 lab, so no successful email flow is claimed and no email was sent. Some guarded browser/dialog fallbacks remain for older Joomla integrations.

There is no known critical defect in the exercised flows. The patch is ready for code review within these boundaries, not a promise that every FLEXIcontent option is release-certified. See the [complete code index](NATIVE-CODE-INDEX.md), [test instructions](NATIVE-EDITOR-WORKFLOWS.md) and [cumulative audit](JOOMLA6-NATIVE-AUDIT.md). Local raw results, scripts, snapshots and screenshots are in `test-env/f6-native-completion-20261120`; credentials and private backups are excluded from Git.

API decisions follow installed Joomla assets and primary documentation: [Joomla Dialog](https://manual.joomla.org/docs/general-concepts/javascript/js-library/joomla-dialog/) and [requestSubmit](https://developer.mozilla.org/en-US/docs/Web/API/HTMLFormElement/requestSubmit).
