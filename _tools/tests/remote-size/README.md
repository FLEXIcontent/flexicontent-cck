# Trusted URL size regressions

Run from the repository root with Python 3 and PHP with cURL:

```sh
python _tools/tests/remote-size/run.py --php /path/to/php
```

The runner starts three loopback-only HTTP fixtures on temporary ports, executes PHP with cURL and again with `-n` (without cURL), then stops the fixtures. It makes no external HTTP requests and does not use a Joomla database. Python's standard library is sufficient.

The real remote helper and complete Add URL / Save controller method bodies execute against small Joomla and persistence doubles. The controller's include/bootstrap preamble is replaced; its method bodies are not rewritten. Cache invalidation is stubbed. The tests inspect the data passed to record persistence and the fixture's request log, including rejected redirect destinations. They also load the real frontend dispatch guard for denied tasks.

Coverage includes disabled/missing configuration, empty host lists, explicitly listed hosts and ports, site-host exceptions, submitted sizes, frontend internal calls, authorization, redirects, HEAD/Range fallbacks, missing lengths, error responses, bounded request deadlines, and cURL absence. Existing recalculation/proxy helper policies remain covered separately from strict automatic sizing.

To check the corresponding files installed in a local Joomla site:

```sh
python _tools/tests/remote-size/run.py --php /path/to/php --installed /path/to/joomla
```

This is isolated controller/HTTP integration coverage, not a browser or full Joomla item-save test. Storage-specific TLS certificates, proxies and redirects should also be checked on the intended storage server.
