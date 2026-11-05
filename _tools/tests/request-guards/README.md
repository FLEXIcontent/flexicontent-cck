# Search purge and AJAX-origin regression checks

Run from the repository root with PHP8.3 or newer:

```sh
php _tools/tests/request-guards/run.php
php -l admin/controllers/search.php
php -l site/tasks/core.php
```

The CLI suite executes the actual search controller and task class, replacing only their Joomla bootstrap preambles. In-memory framework and model doubles verify that rejected purge requests never obtain or mutate a model, while an authorized POST with its form token still purges once and retains the success redirect.

The origin matrix covers standard/nonstandard ports, implicit/explicit default ports, HTTP/HTTPS, case normalization, bracketed IPv6, configured TLS-terminating proxies, mismatched origins and malformed authorities. Forwarded scheme headers are honored only when Joomla's `behind_loadbalancer` setting is enabled; the proxy must supply a single normalized `http` or `https` value. No forwarded host/port headers are trusted.

There are no HTTP requests, real accounts, databases or destructive operations. These checks do not replace an installation/browser integration test of the existing POST toolbar and autocomplete bootstrap.
