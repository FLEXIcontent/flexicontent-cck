<?php
/**
 * The phpThumb endpoint refuses the request shapes that FLEXIcontent never issues: checksum lookups, sourceless
 * canvases, stream wrappers, network paths, parent folder segments and oversized output. Every URL that the
 * component builds stays acceptable. Runs the real guard and checks how the endpoint and its configuration use it.
 */
$root = dirname(__DIR__);
require $root . '/site/librairies/phpthumb/phpThumb.flexi_guard.php';

$checks = 0;
$failures = array();
$check = function ($condition, $message) use (&$checks, &$failures) {
	$checks++;
	if (!$condition) $failures[] = $message;
};

// 1. Requests the component issues
$accepted = array(
	array('src' => '/images/stories/flexicontent/l_photo.jpg', 'w' => '400', 'h' => '300', 'zc' => '1', 'q' => '95', 'ar' => 'x', 'f' => 'jpeg'),
	array('src' => 'images/photo one (2).png', 'w' => 100, 'h' => 0, 'zc' => 0),
	array('src' => '/sub.dir/site/images/a..b.jpg', 'w' => '6000'),
	array('src' => 'C:/laragon/www/site/images/photo.jpg', 'w' => '80'),
	array('src' => 'D:\\sites\\joomla\\images\\photo.jpg', 'w' => '80'),
	array('src' => 'https://example.org/remote.jpg', 'w' => '80'),
	array('src' => 'HTTP://example.org/remote.jpg'),
	array('src' => '/images/a.jpg', 'fltr' => array('gray', 'blur|5', 'usm|80|0.5|3', 'wmt|Copyright 2026|3|BR|ffffff', 'bord|2|0|0|000000')),
	array('src' => '/images/a.jpg', 'fltr' => array('wmi|/images/watermark.png|BR|50|5'), 'hash' => str_repeat('a', 64)),
	array('src' => '/images/a.jpg', 'sx' => '10', 'sy' => '10', 'sw' => '0.5', 'sh' => '0.5', 'far' => 'C', 'bg' => 'FFFFFF', 'aoe' => '1', 'iar' => '1'),
	array(),
);
foreach ($accepted as $i => $get) {
	$check(flexicontent_phpthumb_refusal($get) === null, 'accepted request ' . $i . ' is refused: ' . flexicontent_phpthumb_refusal($get));
}

// 2. Refused request shapes
$refused = array(
	'checksum lookup'                 => array('src' => 'http://127.0.0.1:8080/', 'md5s' => ''),
	'checksum lookup with a value'    => array('src' => '/images/a.jpg', 'md5s' => '0'),
	'sourceless canvas'               => array('new' => 'FFFFFF', 'w' => '100', 'h' => '100'),
	'file wrapper'                    => array('src' => 'file:///etc/hostname.png'),
	'file wrapper, one slash'         => array('src' => 'file:/var/www/secret/a.png'),
	'phar wrapper'                    => array('src' => 'phar://images/a.jpg/x.png'),
	'ftp source'                      => array('src' => 'ftp://example.org/a.jpg'),
	'php wrapper'                     => array('src' => 'php://filter/resource=a.jpg'),
	'data wrapper'                    => array('src' => 'data:image/png;base64,AAAA'),
	'upper case wrapper'              => array('src' => 'FILE:///C:/a.png'),
	'network path'                    => array('src' => '//fileserver/share/a.jpg'),
	'network path, backslashes'       => array('src' => '\\\\fileserver\\share\\a.jpg'),
	'parent segments'                 => array('src' => '/../../outside/a.jpg'),
	'parent segments, backslashes'    => array('src' => '..\\..\\outside\\a.jpg'),
	'parent segment in the middle'    => array('src' => '/images/../../outside/a.jpg'),
	'encoded parent segments'         => array('src' => '/images/%2e%2e/%2e%2e/outside/a.jpg'),
	'NUL byte'                        => array('src' => "/images/a.jpg\0.png"),
	'source list'                     => array('src' => array('/images/a.jpg')),
	'overlay from a wrapper'          => array('src' => '/images/a.jpg', 'fltr' => array('over|file:///var/secret/b.png')),
	'mask outside the site'           => array('src' => '/images/a.jpg', 'fltr' => array('mask|../../outside/b.png')),
	'watermark from a remote ftp'     => array('src' => '/images/a.jpg', 'fltr' => array('gray', 'wmi|ftp://example.org/b.png|BR')),
	'single filter with a wrapper'    => array('src' => '/images/a.jpg', 'fltr' => 'over|phar://x/b.png'),
	'nested filter list'              => array('src' => '/images/a.jpg', 'fltr' => array(array('gray'))),
	'too many filters'                => array('src' => '/images/a.jpg', 'fltr' => array_fill(0, 21, 'gray')),
	'oversized width'                 => array('src' => '/images/a.jpg', 'w' => '20000'),
	'oversized height'                => array('src' => '/images/a.jpg', 'h' => 6001),
	'oversized portrait height'       => array('src' => '/images/a.jpg', 'hp' => '1e9'),
	'size list'                       => array('src' => '/images/a.jpg', 'w' => array('100')),
	'signature list'                  => array('src' => '/images/a.jpg', 'hash' => array('x')),
);
foreach ($refused as $label => $get) {
	$reason = flexicontent_phpthumb_refusal($get);
	$check(is_string($reason) && $reason !== '', 'refused request is accepted: ' . $label);
}

// 3. The endpoint runs the guard before the signature check, the checksum lookup and any file access
$entry = file_get_contents($root . '/site/librairies/phpthumb/phpThumb.php');
$guard = strpos($entry, "flexicontent_phpthumb_refusal(\$_GET)");
$check($guard !== false, 'the endpoint calls the guard');
$check($guard !== false && $guard < strpos($entry, "if (!empty(\$phpThumb->config_high_security_enabled)) {"), 'the guard runs before the signature check');
$check($guard !== false && $guard < strpos($entry, "isset(\$_GET['md5s'])"), 'the guard runs before the checksum lookup');
$check($guard !== false && $guard > strpos($entry, "\$_SERVER['PATH_INFO']"), 'the guard runs after the request parameters are complete');
$check(strpos($entry, "hash_equals(hash_hmac('sha256'") !== false && strpos($entry, "\$_GET['hash'] != hash_hmac") === false, 'the signature is compared with hash_equals');

// 4. Requests are not read from PATH_INFO
$config = file_get_contents($root . '/site/librairies/phpthumb/phpThumb.config.php');
$check(preg_match("/\\\$PHPTHUMB_CONFIG\['disable_pathinfo_parsing'\]\s*=\s*true;/", $config) === 1, 'PATH_INFO parsing is disabled in the shipped configuration');

// 5. Every thumbnail URL the helper builds passes the guard
define('_JEXEC', 1);
define('JPATH_SITE', $root);
eval('namespace Joomla\CMS\Uri; final class Uri { public static function root($pathOnly = false) { return $pathOnly ? "/joomla" : "https://fixture.invalid/joomla/"; } }');
require $root . '/site/classes/helpers/images.php';
(new ReflectionProperty(flexicontent_images::class, 'phpthumb_config'))->setValue(null, array(
	'high_security_password' => str_repeat('test-key-123!', 4), 'high_security_enabled' => true, 'high_security_url_separator' => '&',
));
$built = array(
	array('src' => 'images/stories/flexicontent/l_a b.jpg', 'w' => 400, 'h' => 300, 'zc' => 1, 'q' => 95, 'ar' => 'x', 'f' => 'jpeg'),
	array('src' => '/joomla/components/com_flexicontent/uploads/photo.png', 'w' => 800, 'h' => 800, 'zc' => 1, 'q' => 95, 'f' => 'png', 'ar' => 'x'),
	array('src' => 'images/a.jpg', 'w' => 120, 'h' => 0, 'zc' => 0, 'q' => 85, 'ar' => 'x', 'f' => 'jpg', 'fltr' => array('gray')),
);
foreach ($built as $i => $params) {
	$url = flexicontent_images::phpThumbURL($params, false, true);
	parse_str((string) parse_url($url, PHP_URL_QUERY), $get);
	$check(isset($get['src'], $get['hash']) && flexicontent_phpthumb_refusal($get) === null, 'a URL built by the helper is refused: ' . $i);
}

if ($failures) {
	fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n");
	exit(1);
}
echo $checks . " checks passed\n";
