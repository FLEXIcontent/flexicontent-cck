<?php
/**
 * Scripts and stylesheets that the component loads from a public CDN name a fixed version and carry the hash of the
 * file (Subresource Integrity), so that the browser refuses other content. Metadata of media providers is read as
 * data and never by running a provider script. Reads the sources; no network.
 */
error_reporting(E_ALL);

$root = dirname(__DIR__);
$checks = 0;
$failures = array();
$check = function ($condition, $message) use (&$checks, &$failures) {
    $checks++;
    if (!$condition) $failures[] = $message;
};
$source = function ($file) use ($root) {
    return str_replace("\r\n", "\n", file_get_contents($root . '/' . $file));
};
$hash = '(sha384-[A-Za-z0-9+/]{64})';

// 1. Juxtapose: a version, not "latest", and both files with their hash
$layout = $source('plugins/flexicontent_fields/image/tmpl/value_gallery_juxtapose.php');
$check(strpos($layout, '/latest/') === false, 'the Juxtapose gallery loads the "latest" files');
$check(preg_match('#<script src="https://cdn\.knightlab\.com/libs/juxtapose/(\d+\.\d+\.\d+)/js/juxtapose\.min\.js" integrity="' . $hash . '" crossorigin="anonymous"></script>#', $layout, $js) === 1, 'the Juxtapose script has no fixed version with a hash');
$check(preg_match('#<link rel="stylesheet" href="https://cdn\.knightlab\.com/libs/juxtapose/(\d+\.\d+\.\d+)/css/juxtapose\.css" integrity="' . $hash . '" crossorigin="anonymous">#', $layout, $css) === 1, 'the Juxtapose stylesheet has no fixed version with a hash');
$check($js && $css && $js[1] === $css[1] && $js[2] !== $css[2], 'the Juxtapose script and stylesheet are of different versions, or share a hash');
$check(substr_count($layout, 'cdn.knightlab.com') === 2, 'the Juxtapose gallery loads another file from the CDN');

// 2. Every script or stylesheet that PHP code adds from another host: fixed version and hash, or a known exception
$exceptions = array(
    // The Google Maps loader is generated per request and per key, it has no fixed content
    'https://maps.google.com/maps/api/js' => 'Google Maps loader',
    // The Filestack picker of the layout builder is published without a version in its path
    'https://static.filestackapi.com/v3/filestack.js' => 'Filestack picker',
);
$files = array();
$scan = function ($dir) use (&$scan, &$files, $root) {
    foreach (scandir($root . '/' . $dir) as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $dir . '/' . $name;
        if (is_dir($root . '/' . $path)) {
            // Bundled third party libraries are not code of the component
            if ($path !== 'site/librairies') $scan($path);
        } elseif (substr($name, -4) === '.php') {
            $files[] = $path;
        }
    }
};
foreach (array('admin', 'site', 'plugins', 'modules') as $dir) $scan($dir);
$check(count($files) > 500, 'the scan of the PHP sources found too few files: ' . count($files));

$found = 0;
foreach ($files as $file) {
    $code = $source($file);
    if (strpos($code, 'addScript') === false && strpos($code, 'addStyleSheet') === false) continue;
    foreach (explode("\n", $code) as $number => $line) {
        // Comment lines are not code
        if (preg_match('#^\s*(//|\*|/\*)#', $line)) continue;
        if (!preg_match('#->add(Script|StyleSheet)\(\s*(\$[a-z_]+|\'(https?:)?//[^\']+\')#i', $line, $m)) continue;
        $where = $file . ':' . ($number + 1);
        if ($m[2][0] === '$') {
            // A variable: only the layout builder passes URLs of another host this way
            if (!preg_match('/^\$url_grapjs_(css|js)$/', $m[2])) continue;
            $check(preg_match("#array\(\), array\('integrity' => '" . $hash . "', 'crossorigin' => 'anonymous'\)\);#", $line) === 1, $where . ': the layout builder file has no hash');
            $found++;
            continue;
        }
        $url = trim($m[2], "'");
        $found++;
        if (strpos($url, 'https://code.jquery.com/') === 0) {
            // The forced remote jQuery files: the version is a variable, the hash comes from the variables checked below
            $check(preg_match('/, array\(\), (\(array\) )?\$JQUERY_(VER|UI_VER|UI_CSS)_URL_ATTRS\)/', $line) === 1, $where . ': a jQuery file of the CDN is added without its hash');
            continue;
        }
        $excepted = false;
        foreach ($exceptions as $prefix => $name) $excepted = $excepted || strpos($url, $prefix) === 0;
        if ($excepted) continue;
        $check(preg_match("#array\(\), array\('integrity' => '" . $hash . "', 'crossorigin' => 'anonymous'\)\);#", $line) === 1, $where . ': a file of another host is added without a hash: ' . $url);
        $check(preg_match('#[/@]\d+\.\d+(\.\d+)?/#', $url) === 1, $where . ': a file of another host is added without a fixed version: ' . $url);
        $check(strpos($url, 'https://') === 0, $where . ': a file of another host is not loaded over https: ' . $url);
    }
}
$check($found >= 8, 'the scan found fewer external files than the component loads: ' . $found);

$helper = $source('site/classes/helpers/html.php');
$check(preg_match('/\$grapjs_vers = \'(\d+\.\d+\.\d+)\';/', $helper, $v) === 1 && $v[1] === '0.16.22', 'the layout builder version changed: its two hashes must be changed with it');
foreach (array(
    'https://cdn.ckeditor.com/4.14.1/standard-all/ckeditor.js', 'https://cdnjs.cloudflare.com/ajax/libs/OverlappingMarkerSpiderfier/1.0.3/oms.min.js',
    'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css', 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js',
) as $url) {
    $check(preg_match("#'" . preg_quote($url, '#') . "', array\(\), array\('integrity' => '" . $hash . "', 'crossorigin' => 'anonymous'\)\);#", $helper) === 1, 'no hash for ' . $url);
}
preg_match_all("#'integrity' => '" . $hash . "'#", $helper, $all);
$check(count($all[1]) === 7 && count(array_unique($all[1])) === 7, 'the helper does not carry seven different sha384 hashes');

// The forced remote jQuery and jQuery UI of Joomla 4 and later: fixed versions, a hash for the script and for each theme
$check(preg_match("/\\\$JQUERY_VER = FLEXI_J40GE \? '\d+\.\d+\.\d+' :/", $helper) === 1 && preg_match("/\\\$JQUERY_VER_URL_ATTRS = FLEXI_J40GE \? array\(\s*'integrity' => 'sha(256|384)-[A-Za-z0-9+\/=]{43,64}',\s*'crossorigin' => 'anonymous',\s*\)/", $helper) === 1, 'the remote jQuery has no fixed version with a hash');
$check(preg_match("/\\\$JQUERY_UI_VER = FLEXI_J40GE \? '1\.13\.1' :/", $helper) === 1, 'the jQuery UI version changed: its three hashes must be changed with it');
$check(preg_match("#\\\$JQUERY_UI_VER_URL_ATTRS = FLEXI_J40GE \? array\(\s*'integrity' => '" . $hash . "',\s*'crossorigin' => 'anonymous',\s*\) : array\(\);#", $helper) === 1, 'the remote jQuery UI script has no hash');
$check(preg_match("#\\\$JQUERY_UI_CSS_HASHES = array\(\s*'ui-lightness' => '" . $hash . "',\s*'smoothness'\s*=> '" . $hash . "',\s*\);#", $helper, $themes) === 1 && $themes[1] !== $themes[2], 'the remote jQuery UI themes have no hash each');
$check(preg_match("/if \(!in_array\(\\\$JQUERY_UI_THEME, array\('ui-lightness', 'smoothness'\)\)\)\s*\{\s*\\\$JQUERY_UI_THEME = 'ui-lightness';\s*\}\s*\\\$JQUERY_UI_CSS_URL_ATTRS = FLEXI_J40GE \? array\(\s*'integrity' => \\\$JQUERY_UI_CSS_HASHES\[\\\$JQUERY_UI_THEME\],/", $helper) === 1, 'the theme hash is not chosen after the theme name is limited to the two themes');
$check(strpos($helper, "'//code.jquery.com") === false && strpos($source('admin/views/stats/view.html.php'), "'//netdna") === false, 'a CDN file is loaded with the scheme of the page');

// 3. Scripts that JavaScript adds: the Leaflet fallback of the address form, the chart libraries of the statistics
$osm = $source('plugins/flexicontent_fields/addressint/js/form_osm.js');
$check(preg_match("#link\.href = 'https://unpkg\.com/leaflet@1\.9\.4/dist/leaflet\.css';\s+(?://[^\n]*\n\s*)?link\.integrity = '" . $hash . "';\s+link\.crossOrigin = 'anonymous';#", $osm, $leafletCss) === 1, 'the Leaflet stylesheet of the address form has no hash');
$check(preg_match("#script\.src = 'https://unpkg\.com/leaflet@1\.9\.4/dist/leaflet\.js';\s+script\.integrity = '" . $hash . "';\s+script\.crossOrigin = 'anonymous';#", $osm, $leafletJs) === 1, 'the Leaflet script of the address form has no hash');
$check($leafletCss && $leafletJs && $leafletCss[1] !== $leafletJs[1], 'the Leaflet files share a hash');
$check(preg_match_all('#https?://[a-z0-9.-]+/[^\'"\s]+\.(?:js|css)\b#i', $osm, $urls) === 2, 'the address form loads another file from another host');

$view = $source('admin/views/stats/view.html.php');
$check(preg_match("/'scripts' => \\\$local_charts \? array\(\) : array\((.*?)\n\t\t\t\),/s", $view, $scripts) === 1 && preg_match("/'integrity' => \\\$local_charts \? array\(\) : array\((.*?)\n\t\t\t\),/s", $view, $integrity) === 1, 'the statistics view does not list scripts and hashes');
preg_match_all("#'(https://cdnjs\.cloudflare\.com/[^']+)'#", isset($scripts[1]) ? $scripts[1] : '', $listed);
preg_match_all("#'(https://cdnjs\.cloudflare\.com/[^']+)'\s*=> '" . $hash . "'#", isset($integrity[1]) ? $integrity[1] : '', $hashed);
$check(count($listed[1]) === 5 && $listed[1] === $hashed[1], 'a chart script of the statistics has no hash');
$check(count(array_unique($hashed[2])) === 5, 'chart scripts share a hash');
foreach ($listed[1] as $url) {
    $check(preg_match('#/\d+\.\d+\.\d+/#', $url) === 1, 'a chart script has no fixed version: ' . $url);
}
$stats = $source('site/assets/js/stats.js');
$check(strpos($stats, 'function loadScript(url, integrity) {') !== false && preg_match('/if \(integrity\) \{\s+script\.integrity = integrity;\s+script\.crossOrigin = \'anonymous\';\s+\}/', $stats) === 1, 'the statistics loader does not give the hash to the browser');
$check(strpos($stats, 'loadScript(url, (options.integrity || {})[url])') !== false, 'the statistics loader does not look the hash up');

// 4. Media metadata is fetched as data: no script element, no callback parameter, no global callback
$metadata = $source('site/assets/js/media-metadata.js');
$check(strpos($metadata, "createElement('script')") === false && strpos($metadata, 'createElement("script")') === false, 'the metadata requests create a script element');
$check(stripos($metadata, 'jsonp') === false || preg_match('/^\s*\/\/[^\n]*JSONP[^\n]*$/m', $metadata) === 1 && preg_match('/^(?!\s*\/\/)[^\n]*jsonp/mi', $metadata) === 0, 'the metadata requests still have a JSONP path');
$check(strpos($metadata, "searchParams.set('callback'") === false && strpos($metadata, 'window[callback]') === false, 'the metadata requests register a global callback');
$check(strpos($metadata, "await fetch(url, { signal: request.signal, credentials:'omit' })") !== false && strpos($metadata, 'await response.json()') !== false, 'the metadata requests no longer read the answer as data without credentials');
$check(strpos($metadata, 'async function load(input, url) {') !== false, 'the metadata request takes a switch again');
foreach (array('plugins/flexicontent_fields/image/js/form.js', 'plugins/flexicontent_fields/weblink/js/form.js', 'plugins/flexicontent_fields/sharedmedia/js/form.js') as $file) {
    $form = $source($file);
    $check(preg_match('/[mM]etadata\.load\(/', $form) === 1, $file . ' no longer asks for metadata');
    $check(preg_match('/[mM]etadata\.load\([^;]*,\s*true\s*\)/', $form) === 0, $file . ' still asks for the script fallback');
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n");
    exit(1);
}
echo $checks . " checks passed\n";
