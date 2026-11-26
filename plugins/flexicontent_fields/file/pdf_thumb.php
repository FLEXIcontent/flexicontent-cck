<?php
/**
 * Render the first page of a local PDF file as a PNG thumbnail,
 * used by the 'file' field preview in the item edit form.
 */

define('_JEXEC', 1);

// The plugin directory may be a symlink into the repo, so walk up to the real Joomla root
$joomla_root = __DIR__;
for ($i = 0; $i < 8; $i++)
{
	if (file_exists($joomla_root . '/includes/defines.php'))
	{
		break;
	}
	$joomla_root = dirname($joomla_root);
}
if (!file_exists($joomla_root . '/includes/defines.php'))
{
	http_response_code(500);
	exit('Joomla root not found');
}

define('JPATH_BASE', $joomla_root);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$container = \Joomla\CMS\Factory::getContainer();

// Shared key derived from the Joomla site secret (built by the field)
$expected_key = md5($container->get('config')->get('secret') . 'fc_pdf_thumb');
if (empty($_GET['k']) || !hash_equals($expected_key, (string) $_GET['k']))
{
	http_response_code(403);
	exit('Invalid token');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$w  = isset($_GET['w']) ? max(100, min(800, (int) $_GET['w'])) : 400;
if (!$id)
{
	http_response_code(400);
	exit('Missing file id');
}

$db    = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
$query = $db->getQuery(true)
	->select($db->quoteName(array('filename', 'ext', 'url', 'secure')))
	->from($db->quoteName('#__flexicontent_files'))
	->where($db->quoteName('id') . ' = ' . (int) $id);
$db->setQuery($query);
$file = $db->loadObject();

if (!$file || strtolower((string) $file->ext) !== 'pdf' || (int) $file->url === 1)
{
	http_response_code(404);
	exit('PDF file not found');
}

// Only local files are rendered (no remote URL fetching)
$extQuery = $db->getQuery(true)
	->select($db->quoteName('params'))
	->from($db->quoteName('#__extensions'))
	->where($db->quoteName('type') . ' = ' . $db->quote('component'))
	->where($db->quoteName('element') . ' = ' . $db->quote('com_flexicontent'));
$db->setQuery($extQuery);
$params    = json_decode((string) $db->loadResult(), true) ?: array();
$base_path = (int) $file->secure
	? (!empty($params['file_path']) ? $params['file_path'] : 'components/com_flexicontent/uploads')
	: (!empty($params['media_path']) ? $params['media_path'] : 'components/com_flexicontent/medias');

$path = (int) $file->url === 2
	? JPATH_ROOT . '/' . ltrim((string) $file->filename, '/')
	: JPATH_ROOT . '/' . trim((string) $base_path, '/') . '/' . basename((string) $file->filename);

$path = realpath($path);
if (!$path || !is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf')
{
	http_response_code(404);
	exit('PDF file not found');
}

if (!class_exists('Imagick'))
{
	http_response_code(500);
	exit('Imagick is not available');
}

$modified = filemtime($path);
$etag     = md5($path . '|' . $w . '|' . $modified);
$cacheDir = JPATH_ROOT . '/cache/com_flexicontent';
$cacheFile = $cacheDir . '/pdfthumb_' . $etag . '.png';

$sendHeaders = function () use ($etag, $modified)
{
	header('Content-Type: image/png');
	header('Cache-Control: public, max-age=86400');
	header('ETag: "' . $etag . '"');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
};

if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag)
{
	http_response_code(304);
	exit;
}

// Serve an already generated thumbnail (regenerated only if the PDF changes)
if (is_file($cacheFile))
{
	$sendHeaders();
	readfile($cacheFile);
	exit;
}

try
{
	$image = new \Imagick();
	$image->setResolution(72, 72);
	$image->readImage($path . '[0]');
	$image->setImageBackgroundColor('#ffffff');
	if (defined('Imagick::ALPHACHANNEL_REMOVE'))
	{
		$image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
	}
	$image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
	$image->setImageFormat('png');
	$image->thumbnailImage($w, $w, true, false);

	$blob = $image->getImagesBlob();
	if (!is_dir($cacheDir))
	{
		@mkdir($cacheDir, 0755, true);
	}
	@file_put_contents($cacheFile, $blob, LOCK_EX);

	$sendHeaders();
	echo $blob;
}
catch (\Throwable $e)
{
	http_response_code(500);
	exit('Could not render PDF');
}
