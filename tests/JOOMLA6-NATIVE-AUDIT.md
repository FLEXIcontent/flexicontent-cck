# Joomla 6 native workflow audit — 20 November 2026

The tested core workflows pass on the Laragon `f6.test` laboratory with Joomla 6.2.0, PHP 8.3.30, Choices enabled and jQuery script requests blocked. This patch is suitable for code review in draft PR #1421. **The entire extension is not yet certified jQuery-independent or release-ready:** optional legacy integrations remain, and the coverage limits below matter.

## Branch and environment

Work remains on `refactor/native-editor-workflows-20261119`, the existing PR branch. Current upstream master `bda8b4c7172c6efc0274ee9f15107128201338be` was merged, retaining newer history `keep`, strict database and dashboard/charset behavior. No new PR, merge to master, or release is part of this audit.

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

## Installed-site coverage

All rows marked Passed refer to actual browser interaction, not source inspection alone. Requests for off-site services were blocked except the explicitly permitted static builder assets used for its opening check.

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
| Passed | Cleanup | Audit content IDs 48–67, five uploaded files and two tags deleted through managers; original 47 items retained; six temporarily published modules restored |
| Limited | GrapesJS | Builder opens without jQuery; saving every widget, external uploads and all canvas configurations were not certified |
| Untested | External integrations | Real map/geocoding, mail delivery, remote media/provider credentials and other unconfigured services |
| Untested | Platform matrix | Firefox, WebKit, physical touch devices, older Joomla versions and a fresh installer/upgrade package run |

The initial Kitchen Sink automation had a scroll/stability timeout during Choices interaction. The harness now explicitly scrolls to the control and retains Playwright actionability checks; the complete rerun passed. A separate mask-focus regression was reproduced and fixed in the shipped code. Test failures were not counted as passes based only on a successful HTTP status.

## Automated validation

- 42 PHP/Node/Python regression commands passed, including the existing release suite and the new check-in control test.
- Nine browser/Node suites passed: shared editor workflows (41 cases, including explicit legacy compatibility cases), real Joomla dialogs (three widths), maintenance (nine cases), manager workflows (seven cases), masks (25 cases), colors, uploader, public waveform and media cloning.
- 219 PHP and authored JavaScript syntax checks passed across the complete changed source set. The readable patched Inputmask vendor file is included in JavaScript syntax checking.
- `git diff --check` is required before committing; changed source hashes and raw test output are retained with the private lab evidence.
- `.github/workflows/native-browser-regressions.yml` adds the isolated suites using pinned Joomla 6.2.0 browser assets. Local success does not claim that a hosted Actions run has completed.

## Remaining dependencies and release limits

The inventory scans tracked and untracked PHP, JS, XML, JSON, CSS and LESS under administrator, site, plugins and modules. It found 2,612 matching lines across 350 files, of which 114 are authored files and the remainder vendor/minified files. These counts include comments and guarded compatibility hooks: a textual match is not automatically an executed dependency.

Remaining real legacy paths include:

- Content-module `carousel.php` and its fcxSlide engine; recent-favourites cookie/module helpers; optional `news.php`/`swiper.php` tab-state handlers.
- FancyBox, elastislide, galleriffic and multibox gallery variants; optional legacy InlineBoxes/media output and Joomla media overrides.
- `plugins/content/flexibreak`, optional PrettyCheckable/Select2/custom-scrollbar widgets and their vendor packages.
- Legacy exported helpers in `flexi-lib.js`, including the old editor adapters and “Use Global” checkbox helper. The tested editor path uses the newer native controllers; external callers of the legacy helpers are not certified.
- Builder canvas configuration and third-party template overrides that explicitly request jQuery/Bootstrap 4.

Guarded compatibility adapters remain intentionally for existing jQuery integrations. Native Choices is the tested default; enabling Select2 is a different configuration. The configuration's legacy library switches have not been removed while those integrations still exist.

Not every alert in every maintenance/legacy view has been migrated. The shared dialog bridge and tested manager/editor actions use Joomla Dialog; remaining direct `alert`/`confirm` calls must be reviewed with their workflows. The Inputmask focus-order patch is a vendor change with an adjacent `UPSTREAM-PATCHES.md`; it needs preservation or upstream reconciliation on upgrades.

These are explicit outstanding migration and coverage items. Keep the PR in draft; do not advertise complete jQuery removal or use this audit as full release certification.

## Screenshot comparisons

Screenshots show the same 390-pixel viewport before/after layout changes. Counts differ because disposable test content was created and removed during the audit.

| Surface | Before | After |
|---|---|---|
| Types | [Before](audit-images/types-before-390.png) | [After](audit-images/types-after-390.png) |
| File Manager | [Before](audit-images/files-before-390.png) | [After](audit-images/files-after-390.png) |
| Public listing | [Before](audit-images/listing-before-390.png) | [After](audit-images/listing-after-390.png) |

[Mobile rich-text controls](audit-images/editor-mobile.png) and [mobile mask controls](audit-images/masks-mobile.png) supplement the full-page width measurements.

## API references

- [Joomla Dialog](https://manual.joomla.org/docs/next/general-concepts/javascript/js-library/joomla-dialog/) and [AJAX/messages](https://manual.joomla.org/docs/next/general-concepts/javascript/ajax/).
- [Joomla 6 migration features](https://manual.joomla.org/migrations/54-60/new-features/); installed Joomla 6.2.0 sources were checked alongside documentation.
- [Inputmask upstream changelog](https://github.com/RobinHerbots/Inputmask/blob/5.x/Changelog.md); the local focus-order fix and its reason are documented next to the shipped vendor source.
