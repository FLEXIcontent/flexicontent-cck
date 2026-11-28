# Request-boundary regressions

Private review branch based on `master` at `d637ee7d98ea589dce9813fbd8f1d6114560e989`.

These fixtures exercise the actual changed controller/helper/template code with dummy identities and records. They cover cache identity, permitted/denied batch items, numeric category inputs, exact file assignments and coupons, search maintenance, file metadata/chunk naming, proxy-aware origins, frontend file-manager admission and inline advanced-file permissions. The browser fixture parses PHP-rendered markup with inert text markers.

The source remains unchanged in the container. A scratch-local installation alias points to the read-only source; SQLite is in memory. Routing, context, model/table and cache adapters are doubles. Download tests stop before file streaming/archive creation. No site configuration, credentials, live accounts, outbound requests or real site database are used. These are focused regressions, not a full Joomla installation/update or editor-workflow certification.

## Offline execution

Use an existing PHP 8.3 CLI image with PDO SQLite, DOM, mbstring and intl, and existing Joomla libraries mounted read-only at `/joomla/libraries`. Browser checks additionally require a local image with Playwright Chromium, Node and PHP; its `NODE_PATH` and browser cache path must point to those already installed tools. Do not install dependencies or pull images during the check.

Export the tracked source to a disposable directory without `.git`, local configuration or credentials. Mount that export read-only at `/src`. The only writable target location is a bounded tmpfs at `/scratch`.

Example PowerShell invocation (provide absolute source/library directories and an already available image):

```powershell
$limits = @('run','--rm','--network','none','--read-only','--cap-drop','ALL',
  '--security-opt','no-new-privileges','--cpus','0.5','--memory','256m',
  '--memory-swap','256m','--pids-limit','32','--ulimit','fsize=8388608:8388608',
  '--ulimit','nofile=128:128','--user','82:82','--workdir','/scratch',
  '--tmpfs','/scratch:rw,size=32m,mode=0700,uid=82,gid=82',
  '--mount',('type=bind,source='+$SourceExport+',target=/src,readonly'),
  '--mount',('type=bind,source='+$JoomlaLibraries+',target=/joomla/libraries,readonly'))
docker @limits --entrypoint /usr/bin/env $PhpImage -i PATH=/usr/local/bin:/usr/bin:/bin HOME=/scratch TMPDIR=/scratch LANG=C.UTF-8 TZ=UTC timeout -s KILL 40 php /src/tests/security-boundaries/run.php
```

Also apply a 55-second host timeout and bounded stdout capture. The aggregate PHP runner launches each test in a fresh process with the same empty allowlisted environment. `lint.php` checks the changed PHP files and fixtures. The browser entrypoint is `browser.cjs`; use a bounded profile suitable for Chromium (1 CPU, 1536MiB, 192 processes, 32MiB file size, 1024 descriptors, 128MiB scratch, nonroot UID 1000), with the same read-only/no-network controls.

For negative controls, use the same test files with the pinned original application source and run `run.php --baseline`. Each original-source suite must fail its policy expectation; a fixture crash is not a passing negative control. Run the browser fixture separately against both source variants.

## Scope of the port

The branch includes new fixes for cache identity, batch authorization/category inputs, file assignment, custom-order maintenance and Address International URL handling. It also backports the relevant protections already published in public PRs #1336, #1338, #1343, #1348 and #1354. The origin helper and frontend admission guard preserve their tested upstream behavior. This is a narrow security port; unrelated public-master features and file-editor redesign/settings were not imported.

Textarea2's custom link picker and the site's pillar/articles layouts do not exist in this distribution repository. Their local repairs are deliberately separate. Existing private review PRs #1 and #2 are unchanged.
