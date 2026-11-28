<?php
/**
 * Render the first page of a local PDF file as a PNG thumbnail,
 * used by the 'file' field preview in the item edit form.
 */

define('_JEXEC', 1);
header('Cache-Control: private, no-store');

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

require_once JPATH_ROOT . '/components/com_flexicontent/classes/helpers/security.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
$expires = filter_input(INPUT_GET, 'exp', FILTER_VALIDATE_INT);
$key = $_GET['k'] ?? '';
$now = time();
if (!$id || !$expires || $expires <= $now || $expires > $now + 900
	|| !is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key))
{
	http_response_code(403);
	exit('Invalid token');
}

$container = \Joomla\CMS\Factory::getContainer();
$secret = (string) $container->get('config')->get('secret');
$db = $container->get(\Joomla\Database\DatabaseInterface::class);
$query = $db->getQuery(true)
	->select($db->quoteName(array('id', 'filename', 'ext', 'url', 'secure', 'access', 'uploaded_by', 'published')))
	->from($db->quoteName('#__flexicontent_files'))
	->where($db->quoteName('id') . ' = ' . $id);
$file = $db->setQuery($query)->loadObject();

if ($secret === '' || !flexicontent_security::isPreviewablePdf($file)
	|| !hash_equals(flexicontent_security::pdfThumbnailSignature($file, $expires, $secret), $key))
{
	http_response_code(403);
	exit('Invalid token');
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

$root = realpath((int) $file->url === 2 ? JPATH_ROOT : JPATH_ROOT . '/' . trim((string) $base_path, '/'));
$filename = ltrim((string) $file->filename, '/');
$path = $root ? realpath($root . '/' . $filename) : false;
if (!$path || !flexicontent_security::isContainedPath($path, $root)
	|| !is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf')
{
	http_response_code(404);
	exit('PDF file not found');
}

// The name says ".pdf", the content decides what a decoder would do with it: only a file that starts as a PDF is rendered
if (!flexicontent_security::isPdfFile($path))
{
	http_response_code(415);
	exit('Not a PDF file');
}

if (!class_exists('Imagick'))
{
	http_response_code(500);
	exit('Imagick is not available');
}

try
{
	$image = new \Imagick();
	$image->setResolution(72, 72);

	// Name the PDF decoder, so that the content can not select another one
	$image->readImage('pdf:' . $path . '[0]');
	$image->setImageBackgroundColor('#ffffff');
	if (defined('Imagick::ALPHACHANNEL_REMOVE'))
	{
		$image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
	}
	$image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
	$image->setImageFormat('png');
	$image->thumbnailImage(400, 400, true, false);

	$blob = $image->getImagesBlob();

	header('Content-Type: image/png');
	header('X-Content-Type-Options: nosniff');
	echo $blob;
}
catch (\Throwable $e)
{
	http_response_code(500);
	exit('Could not render PDF');
}
