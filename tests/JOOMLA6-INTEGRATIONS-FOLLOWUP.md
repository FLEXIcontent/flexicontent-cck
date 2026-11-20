# Upstream cards and integration follow-up — 20 November 2026

This extends [the native completion audit](JOOMLA6-NATIVE-COMPLETION.md) and incorporates upstream `3fbd08c7f`. PR #1421 remains draft, unmerged and unreleased.

## Changes

- Preserved the upstream Address and Media File card layouts while retaining native repeaters. Address skip controls now support keyboards, preserve values, synchronize Choices, and leave independently disabled controls disabled. New rows reset skipped state.
- Media File toolbar controls are real buttons. Metadata panels update their expanded state and cloned panels target only their own controls. Selection-only configurations omit the unavailable metadata button.
- Empty Address maps stay hidden until initialized. OpenStreetMap forms omit the Google-only type selector. Filename labels remain readable in Cassiopeia; card widths were checked at 390, 768 and 1440 pixels.
- Nominatim search now requires Search or Enter instead of querying while typing. Requests are serialized, spaced at least 1.1 seconds apart in each form, cached for that form session, and time out after 15 seconds. Changed input invalidates old suggestions. A field setting permits a replacement Nominatim-compatible endpoint.

The public Nominatim service forbids autocomplete and limits aggregate application traffic to one request per second. The browser queue is not a site-wide limiter: sites with concurrent traffic need a cached, rate-limited proxy or another provider. See the [provider policy](https://operations.osmfoundation.org/policies/nominatim/).

## Evidence and limits

- Native editor regression suite: 47 cases pass, including skip/restore, independent disabled controls, keyboard panel toggles, clone isolation, explicit geocoding search, caching and stale responses. The twelve browser suites and 74 PHP checks passed; 292 changed-source syntax checks passed.
- Actual administrator Chromium and frontend Firefox Media File uploads, metadata edits, save/reopen and second save passed. Actual Address/contacts CRUD passed in both forms without jQuery.
- Live noembed requests for a public YouTube example returned HTTP 200, populated metadata and embed URL, and survived save/reopen. This certifies that provider path, not every native media API or playback environment.
- One explicitly submitted live Nominatim lookup for Taipei 101 returned HTTP 200; nine map tiles loaded. Coordinates and the selected address survived both saves and reopening.
- Linux WebKit 27.2 / Playwright 1.65.1 completed two administrator Input Mask saves and reloads with no editor page errors and no jQuery. Windows WebKit reproduces a native page crash after the save redirect in both Playwright 1.60.0 and 1.65.1, including without request interception or probes and after removing TinyMCE. The server saves the item. Real Safari remains unverified.
- Google Maps keys, Disqus/Facebook configuration and Filestack credentials are absent from the lab. Those live integrations remain unverified; controlled-provider regression fixtures are not live-service certification.

Detailed scripts, screenshots, failed diagnostic attempts and raw results are retained locally in `test-env/f6-online-20261120/`. Earlier audit claims apply to their recorded source snapshots; this report covers the additional changes above.
