<?php
/**
 * Joomla copies only the entries a plugin manifest names in <files>. A PHP file that sits next to the
 * manifest but is not named there is never installed, and an upgrade leaves an older copy in place.
 * Checks every plugin manifest against the files in its folder.
 */

$root = dirname(__DIR__);
$checks = 0;
$failures = array();

function check($condition, $message)
{
	global $checks, $failures;
	$checks++;
	if (!$condition) $failures[] = $message;
}

// Files that are not meant to be installed with the plugin
$not_installed = array(
	// Copied by hand into the cli/ folder of the site, see the file header
	'plugins/flexicontent_fields/image/CLI_rebuild_webp.php' => true,
);

$plugin_dirs = glob($root . '/plugins/*/*', GLOB_ONLYDIR);
check(count($plugin_dirs) > 30,'Plugin folders were not found');

foreach ($plugin_dirs as $dir)
{
	$rel = substr($dir, strlen($root) + 1);
	$manifest = null;
	foreach (glob($dir . '/*.xml') as $xml_file)
	{
		$xml = simplexml_load_file($xml_file);
		if ($xml && $xml->getName() === 'extension')
		{
			$manifest = $xml;
			break;
		}
	}

	// Folders that are copied by the component installer (e.g. the JSitemap data source) have no manifest
	if (!$manifest) continue;

	$listed = array();
	foreach ($manifest->files as $files)
	{
		$base = trim((string) $files['folder'], '/');
		foreach ($files->children() as $entry)
		{
			$path = trim(($base !== '' ? $base . '/' : '') . trim((string) $entry), '/');
			$listed[$path] = $entry->getName();

			// The installer aborts when a named entry is missing from the package
			$exists = $entry->getName() === 'folder' ? is_dir($dir . '/' . $path) : is_file($dir . '/' . $path);
			check($exists, $rel . ': manifest names "' . $path . '" which is not in the package');
		}
	}
	if (isset($manifest->scriptfile)) $listed[trim((string) $manifest->scriptfile)] = 'filename';

	foreach (glob($dir . '/*.php') as $php_file)
	{
		$name = basename($php_file);
		if (isset($not_installed[$rel . '/' . $name])) continue;
		check(isset($listed[$name]), $rel . ': ' . $name . ' is not named in <files>, so it is never installed or upgraded');
	}
}

// The File field issues preview URLs to this handler, so it has to reach the site with the plugin
$file_manifest = simplexml_load_file($root . '/plugins/flexicontent_fields/file/file.xml');
$file_entries = array();
foreach ($file_manifest->files->children() as $entry) $file_entries[] = $entry->getName() . ':' . trim((string) $entry);
check(in_array('filename:pdf_thumb.php', $file_entries, true), 'The File plugin manifest does not install pdf_thumb.php');
check(is_file($root . '/plugins/flexicontent_fields/file/pdf_thumb.php'), 'pdf_thumb.php is missing from the File plugin');
check(
	strpos(file_get_contents($root . '/site/classes/helpers/security.php'), "'plugins/flexicontent_fields/file/pdf_thumb.php?'") !== false,
	'The preview URL no longer points at plugins/flexicontent_fields/file/pdf_thumb.php'
);

if ($failures)
{
	fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n");
	exit(1);
}

echo $checks . " checks passed\n";
