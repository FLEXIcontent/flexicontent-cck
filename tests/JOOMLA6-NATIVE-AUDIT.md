# Joomla 6 native workflow audit — 20 November 2026

> Current status: the [independent-audit follow-up](JOOMLA6-AUDIT-FOLLOWUP.md) supersedes the older dependency counts, Firefox gap and validation totals below. All 18 reported defects have targeted fixes and verification. Earlier sections retain their original test scope; they do not imply that every historical check was repeated on the latest commit.

The tested core workflows pass on the Laragon `f6.test` laboratory with Joomla 6.2.0, web PHP 8.3.15 (CLI tests: 8.3.30) and Choices enabled. Native workflows are tested with jQuery absent/blocked. Selected legacy plugins retain jQuery, loaded only when those plugins are requested. This follows the agreed compatibility policy; it is not package-wide removal of every jQuery reference.

The patch is ready for code review in **draft PR #1421**. The complete [code index](NATIVE-CODE-INDEX.md) links the JavaScript rewrites, CSS and integration files. The coverage limits below still prevent full release certification.

## Branch and environment

Work remains on `refactor/native-editor-workflows-20261119`, the existing PR branch. Upstream master `a5012aafda32cf4a6e45dd50886fab657d8f0d8a` is merged, retaining newer history `keep`, strict database and dashboard/charset behavior. No new PR, merge to master, or release is part of this audit.

Local runtime files were backed up, overlaid from the source worktree and SHA-256 verified. A private database/media backup precedes writes. The lab's missing history `keep` column was added without discarding versions, and Joomla's charset maintenance was completed through its interface. Those are lab upgrade operations, not new migration logic in this patch. Test credentials, configuration files, sessions, database dumps and raw token-bearing browser logs are excluded from the PR.

## Reproduced defects and changes

| Area | Reproduction and root cause | Result |
|---|---|---|
| Types routing | Changing “Allowed / Route / Redirect” submitted twice; the second submission lost the task | Return the Joomla list task result. All three values saved, reloaded and restored |
| Fields manager | Existing array values reached `preg_split`, causing a PHP error | Normalize arrays and strings in both paths; manager and field configurations load |
| Configuration | Generic framework loading and Joomla color fields pulled in jQuery, UI and MiniColors | Native asset selection and `fccolor` controls; Choices retained; alpha/CSS values saved and restored |
| Dialogs and alerts | Shared managers and editor workflows used jQuery UI or browser confirmations | Joomla dialogs/messages through WAM, with cancellation, focus and form association preserved; compatibility handles retained |
| Parent form submission | Dialog-triggered Trash reached an unconverted jQuery progress handler | Shared native progress overlay; no-selection alert, repeated submits and disabled animation tested |
| File Manager | Basic upload image/text selection threw `jQuery is not defined` | Native preview, sizing and visibility; stale FileReader callbacks ignored after replacement/clear |
| File confirmations | Delete and maintenance actions used browser `confirm` | Native Joomla confirmation/alert paths; actual bulk-delete cancellation and confirmation tested |
| History | Paging/keep/compare/restore depended on old handlers | Native requests preserve parameters, show failures and retain current content; installed-site keep/compare/restore exercised |
| Layout parameters | Dynamic settings relied on jQuery loading; stale responses could replace newer choices | Abort stale fetches, retain the prior form on errors, initialize Choices/colors/editors on inserted content |
| Input masks | Rapid click/tab followed by currency entry lost leading digits; empty integer placeholder could append zero | Drain deferred focus handlers before key/paste processing; blank integer placeholders. Typing, paste, validation and stored values verified |
| URL/percentage masks | Forced URL prefix and lost percentage precision changed valid input | Preserve HTTP/HTTPS/FTP/FTPS; restore two-decimal percentage support |
| Check-in controls | Foreign-locked records rendered a duplicate `cid[]` checkbox/id | One checkbox per record; existing permission rules retained; own/foreign/unlocked cases tested |
| CSV import | Native conversion needed the write controller response and separate progress endpoint | Two quoted/comma-containing rows imported with their custom field values; failure/stall behavior covered in fixtures |
| Public modules | Advanced search and an empty-category filter path used jQuery; category/map empty settings emitted warnings | Native actions; initialized optional variables; search/toggle/filter actions and empty configuration checked |
| Builder/media | Builder notification dependency and media retry handling affected native workflows | Joomla warnings, native media controls and playback retry; builder opening verified |
| Responsive layout | Repeatable/property tables, uploader minimum width, pagination and wide manager tables overflowed | Wrapping, bounded fields, stacked narrow layouts and keyboard-accessible table scrolling; matching LESS/CSS updated |

## Follow-up: optional dependencies and remaining authored helpers

| Area | Reproduced problem | Fix and verification |
|---|---|---|
| Optional gallery assets | Choices mode skipped required dependencies or replaced FancyBox with a limited fallback | The selected plugin requests jQuery core once, without jQuery UI; per-widget manual loading remains respected. FancyBox uses its actual configured plugin |
| PhotoSwipe | Its legacy adapter calls removed jQuery `.live()` | Initialize the bundled native API; open, next and close pass without jQuery |
| Juxtapose | FLEXIcontent's resize wrapper threw `jQuery is not defined`; vendor HTML scanning overwrote vertical mode | Native explicit initialization, unique IDs for image pairs, typed settings and parent resize observation; horizontal/vertical drag and aspect-ratio resizing pass |
| Galleriffic | Missing stored dimensions collapsed images; zero-height clipping hid pause/play; icon HTML broke title markup | Preserve natural image dimensions, expose overlay controls and create attributes through the DOM. Pause/next and popup-disabled operation pass |
| FancyBox settings | Toolbar `false` was truthy text; thumbnail autostart was unused | JSON-encode typed settings, actual `buttons`, thumbnails and disabled effects; preserve the existing misspelled transition setting for compatibility. Hidden toolbar, custom/empty buttons and thumbnail autostart pass |
| Content carousel | Removing jQuery UI exposed an undeclared easing dependency | Request its easing plugin directly. Next-page navigation and stop pass with jQuery core and no UI |
| News/Swiper/carousel state | Old `shown`/`hidden` events did not match Bootstrap 5; closed panels were forgotten | Native Bootstrap events, scoped cookies and closed-state restoration; malformed nested cookie data normalized. Actual selection/reload/open/close checks pass |
| Recent favourites | Cookie parsing and module updates required jQuery; failed requests could create optimistic entries | Native rendering, safe cookie recovery, Sortable and success-only events. Add/reload/remove pass signed in, with two-module and failure fixtures |
| Favourites endpoint | Frontend editing registered the administrator item model, which lacks `getFavoured()`; AJAX returned HTTP 500 | Use existing favourites DB helpers with explicit item/category/user IDs; preserve token validation and counters. Live signed-in round-trip and controller tests pass |
| Exported helpers | Use Global, equal heights, form-value restore and editor adapters still required jQuery | Native DOM implementations retain DOM/collection compatibility, checkbox/radio/multi-select values and Choices state |
| TinyMCE restoration | Removing TinyMCE left Joomla's editor registry populated, preventing restoration | Await registry removal before `setupEditor`; a real Joomla editor retains Unicode content, and a delayed-registry regression covers the race |
| Flexibreak | Native pages hit jQuery calls; tabs/sliders called removed Joomla helpers; PHP emitted dynamic-property warnings | Native navigation/show-all/hash handling, Joomla tabs/Bootstrap accordion rendering, declared state and reset per article. All five display modes pass |
| Failure messages | Favourites network errors used a blocking browser alert | Joomla messages loaded through WAM; error state rolls back and the failure remains visible |

## Installed-site coverage

All rows marked Passed refer to actual browser interaction, not source inspection alone. Requests for off-site services were blocked except explicitly permitted static builder and Juxtapose assets. No external provider credentials or private content were transmitted by these checks.

| Status | Scope | Evidence and limits |
|---|---|---|
| Passed | Administrator surface sweep | 13 manager/configuration surfaces; console, response and asset checks; desktop/tablet/mobile screenshots |
| Passed | Field configuration | Edit pages for all 47 installed unique field plugins loaded with no jQuery requests or page errors; this does not test every setting combination |
| Passed | Kitchen Sink | Frontend create/save, administrator reopen/edit/save, frontend reopen; 23 custom values, emoji, Choices selections, relationships, conditions, contacts, links, dates and three custom TinyMCE instances including a dynamically added editor |
| Passed | Input Mask | All 14 configured mask controls completed; database round-trip for formatted/unmasked values; administrator edit retained leading-zero phone, `249.95` currency and `-42` integer |
| Passed | Uploads and media | Image upload/replacement and persisted selection; MP3 upload; waveform play/pause/seek and retry after expected missing-preview response; basic uploader image/text preview |
| Passed | Subforms and repeaters | Add/remove, drag and keyboard ordering, save/reopen; administrator/mobile/frontend and dark administrator checks |
| Passed | Types/configuration | Routing values persisted and restored; 22 Choices controls observed in configuration; native RGBA color save/reload/restore |
| Passed | Other dialogs | Menu item selection/clear/cancel, template duplicate/delete/cancel, history keep/compare/restore, manager empty-selection alerts, File Manager deletion and maintenance cancellation |
| Passed | Public modules | Category navigation, content module, filters, latest items and advanced search; advanced-search toggle and Enter search; map's unconfigured empty state only |
| Passed | Responsive | Five frontend/editor/statistics surfaces at 390/768/1440: document width equals viewport; additional manager screenshots at those widths |
| Passed | Permissions smoke | Author/registered denied editing where expected; administrator/publisher authorized saves |
| Passed | Optional galleries | Nine modes (none, FancyBox, Galleriffic, elastislide, multibox, GLightbox, Swiper, PhotoSwipe, Juxtapose) at 390/768/1280; actual navigation and open/close; no console or local HTTP errors. GLightbox hides arrows below 769px, so narrow-width navigation uses the keyboard |
| Passed | Public follow-up | News/Swiper/carousel remembered state, carousel next/stop, signed-in favourite add/reload/remove, and all five Flexibreak modes. Temporary field/module/article/plugin settings restored |
| Passed | Cleanup | Audit content IDs 48–67, five uploaded files and two tags deleted through managers; original 47 items retained; six temporarily published modules restored |
| Limited | GrapesJS | Builder opens without jQuery; saving every widget, external uploads and all canvas configurations were not certified |
| Untested | External integrations | Real map/geocoding, mail delivery, remote media/provider credentials and other unconfigured services |
| Untested | Platform matrix | Firefox, WebKit, physical touch devices, older Joomla versions and a fresh installer/upgrade package run |

The initial Kitchen Sink automation had a scroll/stability timeout during Choices interaction. The harness now explicitly scrolls to the control and retains Playwright actionability checks; the complete rerun passed. A separate mask-focus regression was reproduced and fixed in the shipped code. Test failures were not counted as passes based only on a successful HTTP status.

## Automated validation

### Persistent field catalogue follow-up

The laboratory now has seven additional content types, 57 named custom fields
and 20 persistent sample items, linked from a field-test catalogue. The original
Kitchen Sink and Input Mask types remain available. Each gallery engine has its
own image field and sample page, so another engine cannot conceal its dependency
requirements. RokBox, JCE MediaBox, Widgetkit, comments and account integrations
are explicitly marked with their external setup prerequisites.

All seven new types passed administrator and frontend save/reopen checks and
frontend create-form checks with jQuery blocked in the editors. Added TinyMCE,
text and grouped Choices rows persisted, then were removed and the original
sample values restored exactly. Public sample pages were checked at desktop and
mobile widths. Ten bundled gallery modes, including Pannellum with a synthetic
2:1 panorama, were exercised at 390, 768 and 1280 pixels.

These fixtures exposed two further defects fixed in this patch:

- Phone country options used `+886` while saved values used `886`, clearing the
  selection on reopening and losing it on the next save. Options, selection,
  save normalization and telephone URLs now share numeric country codes. A
  disabled single-country select also submits its value through a hidden input.
  Actual save/reopen passed for `+1`, `+886` and `+1 441`; the PHP regression also
  covers legacy values, configured defaults and invalid input.
- Multibox's close button could sit below Cassiopeia's header at tablet widths,
  and large images extended outside a mobile viewport. Modal stacking and image
  sizing now keep the viewer accessible. Both readable and minified scripts are
  tested with wide, tall and small images at three widths; the existing public
  widget test checks close-button and backdrop pointer hit testing.

The new regression commands are `php tests/phone-country-roundtrip.php` and
`node tests/multibox-layout.cjs` (the latter uses `FLEXI_TEST_JQUERY`). Both are
wired into the existing CI workflows. Laboratory content and template changes
are local fixtures and are not included in the extension package.

- **67 PHP/Node/Python regression commands passed**, including dependency load order/deduplication/manual opt-out, item/category favourites, gallery settings, phone country persistence, Flexibreak rendering and malformed module cookies. The local HTTP-size fixture initially failed inside the network sandbox; its rerun outside that sandbox passed all 53 HTTP and six no-cURL checks. It contacts loopback servers only.
- **12 browser/Node suites passed**: shared editors (41 cases), real Joomla dialogs (three widths), maintenance (nine cases), managers (seven cases), masks (25 cases), colors, uploader, public waveform, legacy helper migration, public widgets, Multibox layout and media cloning.
- **245 PHP and JavaScript syntax checks passed** across the cumulative changed source set. Both patched Multibox scripts are included; the changed Galleriffic vendor file was also exercised in the browser.
- `git diff --check` passed. Source hashes and raw output remain in private lab evidence; no credentials, configuration files or sessions are committed.
- CI runs the added browser and PHP regressions. The four hosted workflows at the previous audit commit `4c083f8e7` passed. Results for the follow-up commit must be assessed on that commit's checks.

## Frontend toolbar follow-up — 20 November 2026

Cassiopeia exposed three overlapping toolbar rules: `.btn-sm` shrank Save's
label inside a group, the old edit-view overrides removed its vertical padding,
and generic field-control rules padded the `joomla-toolbar-button` wrapper.
Save was 26 pixels high on a create form versus Cancel's 42 pixels. On a
390-pixel edit form its icon wrapped above its label, while the split toggle
and Cancel had different sizes and alignment.

The shared `flexi_toolbar.less` rules now give frontend actions consistent
typography, 44-pixel minimum height and icon spacing, with a joined split Save
control that grows correctly on a phone. Both LTR/RTL Joomla stylesheets import
and compile this source. Template button colours are retained. The shared PHP
helper marks decorative icons hidden from assistive technology and gives the
dropdown an explicit button type and Joomla's translated `JOPTIONS` label
(the administrator-only toggle translation is unavailable in the frontend).

Verification on the installed site:

- Create and edit forms at **390/768/1440**: equal button heights and fonts,
  icon spacing, joined split controls and no document overflow; screenshots
  inspected before/after. Keyboard Arrow Down opens the menu; Escape closes it
  and returns focus to the toggle.
- **Save/reopen in frontend and administrator**, plus frontend **Save and
  preview** and **Cancel**, passed with jQuery absent/blocked and no JavaScript
  errors. The media sample's custom values were unchanged. The contacts sample
  was already checked out by another contributor and was left untouched.
- Existing `native-editor-workflows.cjs` suite passed (36 cases in this run);
  PHP syntax, both LESS compilations and `git diff --check` passed. The earlier
  full regression totals above apply to the preceding audit snapshot; this
  narrow follow-up reran the affected editor/browser checks.

The styling follows the installed Joomla 6.2 toolbar markup and
[Bootstrap button-group structure](https://getbootstrap.com/docs/5.3/components/button-group/).

| Frontend editor | Before | After |
|---|---|---|
| Desktop, 1440 px | [Before](audit-images/toolbar-before-1440.png) | [After](audit-images/toolbar-after-1440.png) |
| Mobile, 390 px | [Before](audit-images/toolbar-before-390.png) | [After](audit-images/toolbar-after-390.png) |

## Remaining dependencies and release limits

The inventory scans tracked and untracked PHP, JS, XML, JSON, CSS and LESS under administrator, site, plugins and modules. It found **2,385 matching lines in 319 files**, including **83 authored files**; the remainder are vendor/minified files. Comments, retained packages and guarded compatibility hooks count as textual matches, not necessarily executed dependencies.

The following jQuery paths remain intentionally optional:

- FancyBox, elastislide, Galleriffic, multibox and the fcxSlide carousel. Their actual controls passed in the installed site; their dependencies load when requested, without jQuery UI.
- Explicitly requested image-picker, zTree, mousewheel and legacy custom-scrollbar widgets. Asset-loader behavior is tested; every third-party widget configuration is not certified. Choices mode uses native CSS scrolling for the scrollbar path.
- InlineBoxes links now use native handlers; optional popup engines and old Joomla media overrides, builder canvas content and third-party overrides that explicitly request jQuery/Bootstrap 4. These remain compatibility paths, with configuration-specific coverage outstanding.
- Guarded exported jQuery UI/data/event bridges for existing integrations. The rewritten helpers themselves run without jQuery; external custom callers are not comprehensively certified.

The earlier remaining-work list is superseded: recent favourites, module tab-state handlers, Flexibreak, Use Global, cookie/form restoration and editor adapters have now been migrated and tested. The current loader's `select2` compatibility name selects Choices; `prettyCheckable` does not load its vendor widget. Their retained package files/hooks do not mean normal configuration pages execute those libraries.

Joomla Dialog/messages are used by the tested core managers and editors, and Joomla messages handle the favourites failure path. Some direct browser alerts remain in legacy/untested views and compatibility fallbacks. This is not a claim that every alert throughout the package has been converted.

Inputmask, Galleriffic and Multibox have adjacent `UPSTREAM-PATCHES.md` notes for vendor changes. Reconcile those patches when updating the libraries. Juxtapose and Pannellum use external CDN dependencies; external availability is a release consideration.

Keep the PR in draft pending review and the untested external-provider, browser/platform and installer matrix described above. Optional jQuery plugins are supported by design; the patch does not claim full release certification or zero jQuery in every bundled file.

## Screenshot comparisons

Screenshots show the same 390-pixel viewport before/after layout changes. Counts differ because disposable test content was created and removed during the audit.

| Surface | Before | After |
|---|---|---|
| Types | [Before](audit-images/types-before-390.png) | [After](audit-images/types-after-390.png) |
| File Manager | [Before](audit-images/files-before-390.png) | [After](audit-images/files-after-390.png) |
| Public listing | [Before](audit-images/listing-before-390.png) | [After](audit-images/listing-after-390.png) |

Galleriffic controls at the same 1280-pixel viewport: [before](audit-images/galleriffic-before-1280.png), [after](audit-images/galleriffic-after-1280.png), and [mobile](audit-images/galleriffic-mobile.png).

Multibox at 768 pixels: [header covering the close control](audit-images/multibox-before-768.png), [corrected stacking](audit-images/multibox-after-768.png), and [image fitting at 390 pixels](audit-images/multibox-mobile.png).

[Mobile rich-text controls](audit-images/editor-mobile.png) and [mobile mask controls](audit-images/masks-mobile.png) supplement the full-page width measurements.

## API references

- [Joomla Dialog](https://manual.joomla.org/docs/next/general-concepts/javascript/js-library/joomla-dialog/) and [AJAX/messages](https://manual.joomla.org/docs/next/general-concepts/javascript/ajax/).
- [Joomla 6 migration features](https://manual.joomla.org/migrations/54-60/new-features/); installed Joomla 6.2.0 sources were checked alongside documentation.
- [Inputmask upstream changelog](https://github.com/RobinHerbots/Inputmask/blob/5.x/Changelog.md); the local focus-order fix and its reason are documented next to the shipped vendor source.

- [Joomla native tabs](https://manual.joomla.org/docs/next/general-concepts/bootstrap/tabs/) and [editor registry API](https://manual.joomla.org/docs/4.4/general-concepts/javascript/js-library/editors/).
- [Juxtapose source](https://github.com/NUKnightLab/juxtapose) and [FancyBox 3 options](https://github.com/fancyapps/fancybox/blob/master/docs/index.html).
- [CSS stacking contexts](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Positioned_layout/Stacking_context); Cassiopeia's installed header layer was verified in the browser.
