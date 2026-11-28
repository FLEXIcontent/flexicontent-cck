<?php
/**
 * Images of the folders that FLEXIcontent keeps away from the public (the secure file folder, the "original" folders
 * of image fields) are served by the phpThumb endpoint to signed requests only, also when high security is off.
 * The component signs its own URLs for them and creates the key when there is none, it does not sign such an image
 * when the path comes from content, and the signer of hand written URLs signs nothing but plain thumbnails of
 * public images. Runs the real guard and the real helper against a site built in the temporary folder.
 */
namespace Joomla\CMS\Uri {
    final class Uri
    {
        public static function root($pathOnly = false)
        {
            return $pathOnly ? '/joomla' : 'https://fixture.invalid/joomla/';
        }
    }
}

namespace Joomla\CMS\Component {
    final class ComponentHelper
    {
        public static $params = array();

        public static function getParams($option)
        {
            return new class(self::$params) {
                private $values;
                public function __construct(array $values) { $this->values = $values; }
                public function get($name, $default = null) { return array_key_exists($name, $this->values) ? $this->values[$name] : $default; }
            };
        }
    }
}

namespace {
    use Joomla\CMS\Component\ComponentHelper;

    final class flexicontent_remote
    {
        public static function getSiteHost() { return 'fixture.invalid'; }
        public static function getTrustedHosts() { return array(); }
    }

    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    $root = dirname(__DIR__);
    $library = $root . '/site/librairies/phpthumb';
    $base = str_replace('\\', '/', sys_get_temp_dir()) . '/fc-thumb-protected-' . getmypid();
    $site = $base . '/joomla';
    $scriptDir = $site . '/components/com_flexicontent/librairies/phpthumb';

    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('JPATH_SITE', $site);

    $checks = 0;
    $failures = array();
    $check = function ($condition, $message) use (&$checks, &$failures) {
        $checks++;
        if (!$condition) $failures[] = $message;
    };
    $put = function ($path, $content = 'image') {
        is_dir(dirname($path)) || mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
    };
    $remove = function ($dir) use (&$remove) {
        foreach (is_dir($dir) ? array_diff(scandir($dir), array('.', '..')) : array() as $name) {
            is_dir($dir . '/' . $name) && !is_link($dir . '/' . $name) ? $remove($dir . '/' . $name) : unlink($dir . '/' . $name);
        }
        is_dir($dir) && rmdir($dir);
    };
    $methodSource = function ($source, $name) {
        $start = strpos($source, 'function ' . $name . '(');
        $method = ''; $depth = 0; $opened = false;
        foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
            $method .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { $depth++; $opened = true; }
            if ($token === '}' && --$depth === 0 && $opened) break;
        }
        return $method;
    };

    $deny = "<FilesMatch \".*\">\n  Order Allow,Deny\n  Deny from all\n</FilesMatch>\n";
    $allow = "<FilesMatch \".*\">\n  Order Allow,Deny\n  Allow from all\n</FilesMatch>\n";

    try {
        // The site: a public image, the secure file folder, a second secure folder, originals of image fields
        $put($site . '/images/public.jpg');
        $put($site . '/images/watermark.png');
        $put($site . '/images/manual.pdf');
        $put($site . '/images/original/holiday.jpg');
        $put($site . '/components/com_flexicontent/uploads/secret.jpg');
        $put($site . '/components/com_flexicontent/uploads/.htaccess', $deny);
        $put($site . '/components/com_flexicontent/medias/media.jpg');
        $put($site . '/secure-docs/scan.png');
        $put($site . '/images/stories/flexicontent/item_12_field_5/original/photo.jpg');
        $put($site . '/images/stories/flexicontent/item_12_field_5/original/.htaccess', $deny);
        $put($site . '/images/stories/flexicontent/item_12_field_5/l_photo.jpg');
        $put($site . '/images/stories/flexicontent/item__2026_11_27_tmp_field_5/original/new.jpg');
        $put($site . '/images/open/item_3_field_7/original/photo.jpg');
        $put($site . '/images/open/item_3_field_7/original/.htaccess', $allow);
        // The endpoint configuration as shipped, so that the helper loads it exactly as on a site
        mkdir($scriptDir, 0777, true);
        copy($library . '/phpThumb.config.php', $scriptDir . '/phpThumb.config.php');
        copy($library . '/phpthumb.functions.php', $scriptDir . '/phpthumb.functions.php');

        require $library . '/phpThumb.flexi_guard.php';

        $key = str_repeat('test-key-123!', 4);
        $config = array('document_root' => $base, 'high_security_password' => $key, 'high_security_url_separator' => '&');
        $protected = array($site . '/components/com_flexicontent/uploads', $site . '/secure-docs');
        $refusal = function (array $get, $query = '', $with = null) use ($config, $protected, $scriptDir) {
            return flexicontent_phpthumb_protected_refusal($get, $query, $with ?: $config, $protected, $scriptDir);
        };
        $request = function (array $params, $with_key) {
            $parts = array();
            foreach ($params as $name => $value) {
                foreach ((array) $value as $item) $parts[] = $name . (is_array($value) ? '[]' : '') . '=' . rawurlencode($item);
            }
            $query = implode('&', $parts);
            $query .= $with_key === '' ? '' : '&hash=' . hash_hmac('sha256', $query, $with_key);
            parse_str($query, $get);
            return array($get, $query);
        };

        // 1. Unsigned requests for protected images are refused, however the folder is spelled
        $uploads = '/joomla/components/com_flexicontent/uploads/secret.jpg';
        $original = '/joomla/images/stories/flexicontent/item_12_field_5/original/photo.jpg';
        $refused = array(
            'secure file folder'                => array('src' => $uploads, 'w' => '100'),
            'no other parameter (pass through)' => array('src' => $uploads),
            'other letter case'                 => array('src' => '/joomla/components/com_flexicontent/UPLOADS/secret.jpg'),
            'repeated separators'               => array('src' => '/joomla//components/com_flexicontent///uploads/secret.jpg'),
            'backslashes'                       => array('src' => '\\joomla\\components\\com_flexicontent\\uploads\\secret.jpg'),
            'current folder segments'           => array('src' => '/joomla/./components/com_flexicontent/./uploads/secret.jpg'),
            'absolute path'                     => array('src' => $site . '/components/com_flexicontent/uploads/secret.jpg'),
            'URL of this site'                  => array('src' => 'http://fixture.invalid/joomla/components/com_flexicontent/uploads/secret.jpg'),
            'encoded URL of this site'          => array('src' => 'https://fixture.invalid/joomla/components/com_flexicontent/uploads/secret%2Ejpg?x=1'),
            'file that does not exist'          => array('src' => '/joomla/components/com_flexicontent/uploads/absent.jpg'),
            'configured secure folder'          => array('src' => '/joomla/secure-docs/scan.png', 'w' => '100'),
            'original of an image field'        => array('src' => $original, 'w' => '100'),
            'original without a rule file'      => array('src' => '/joomla/images/stories/flexicontent/item__2026_11_27_tmp_field_5/original/new.jpg'),
            'overlay from the secure folder'    => array('src' => '/joomla/images/public.jpg', 'fltr' => array('over|' . $uploads)),
            'watermark from an original'        => array('src' => '/joomla/images/public.jpg', 'fltr' => array('gray', 'wmi|' . $original . '|BR|50')),
            'mask, single filter'               => array('src' => '/joomla/images/public.jpg', 'fltr' => 'mask|' . $uploads),
        );
        foreach ($refused as $label => $get) {
            $check(is_string($refusal($get, http_build_query($get))), 'unsigned request is served: ' . $label);
        }

        // 2. Unsigned thumbnails of public folders are served as before
        $served = array(
            'public image'                 => array('src' => '/joomla/images/public.jpg', 'w' => '100', 'h' => '100', 'zc' => '1'),
            'thumbnail of an image field'  => array('src' => '/joomla/images/stories/flexicontent/item_12_field_5/l_photo.jpg'),
            'originals left public'        => array('src' => '/joomla/images/open/item_3_field_7/original/photo.jpg'),
            'ordinary folder "original"'   => array('src' => '/joomla/images/original/holiday.jpg'),
            'media folder'                 => array('src' => '/joomla/components/com_flexicontent/medias/media.jpg'),
            'folder with a similar name'   => array('src' => '/joomla/components/com_flexicontent/uploads-public/a.jpg'),
            'relative to the endpoint'     => array('src' => 'images/phpthumb.png'),
            'public watermark'             => array('src' => '/joomla/images/public.jpg', 'fltr' => array('wmi|/joomla/images/watermark.png|BR', 'usm|80|0.5|3')),
            'remote image'                 => array('src' => 'https://example.org/remote.jpg'),
            'no source'                    => array('w' => '100'),
            'nothing'                      => array(),
        );
        foreach ($served as $label => $get) {
            $check($refusal($get, http_build_query($get)) === null, 'unsigned request is refused: ' . $label . ' (' . $refusal($get) . ')');
        }

        // 3. A signature made with the site key opens the protected image, nothing else does
        foreach (array('secure file folder' => array('src' => $uploads, 'w' => '100'), 'original' => array('src' => $original, 'w' => '800', 'f' => 'jpeg'),
            'overlay' => array('src' => '/joomla/images/public.jpg', 'fltr' => array('over|' . $uploads))) as $label => $params) {
            list($get, $query) = $request($params, $key);
            $check($refusal($get, $query) === null, 'signed request is refused: ' . $label);
        }
        list($get, $query) = $request(array('src' => $uploads, 'w' => '100'), $key);
        $check(is_string($refusal($get, str_replace('w=100', 'w=101', $query))), 'a signature is accepted for other parameters');
        $check(is_string($refusal(array('src' => $original) + $get, str_replace(rawurlencode($uploads), rawurlencode($original), $query))), 'a signature is accepted for another image');
        $check(is_string($refusal(array('hash' => array($get['hash'])) + $get, $query)), 'a signature list is accepted');
        $check(is_string($refusal(array('hash' => '') + $get, $query)), 'an empty signature is accepted');
        list($get, $query) = $request(array('src' => $uploads, 'w' => '100'), str_repeat('other-key-456?', 4));
        $check(is_string($refusal($get, $query)), 'a signature made with another key is accepted');
        foreach (array('', 'weak') as $weak) {
            list($get, $query) = $request(array('src' => $uploads), $weak === '' ? 'x' : $weak);
            $get['hash'] = hash_hmac('sha256', 'src=' . rawurlencode($uploads), $weak);
            $query = 'src=' . rawurlencode($uploads) . '&hash=' . $get['hash'];
            $check(is_string($refusal($get, $query, array('high_security_password' => $weak) + $config)), 'a signature made with a missing or weak key is accepted');
        }

        // 4. The folders the endpoint protects without being told: the uploads folder of the component, and the configured ones
        $GLOBALS['PHPTHUMB_FLEXI_PROTECTED_DIRS'] = array('secure-docs', $site . '/elsewhere', '', 7);
        $dirs = array_map('flexicontent_phpthumb_path', flexicontent_phpthumb_protected_dirs());
        $check(in_array(flexicontent_phpthumb_path($root . '/site/uploads'), $dirs, true), 'the uploads folder of the component is not protected');
        $check(in_array(flexicontent_phpthumb_path(dirname($root) . '/secure-docs'), $dirs, true), 'a configured folder is not resolved against the site root');
        $check(in_array(flexicontent_phpthumb_path($site . '/elsewhere'), $dirs, true) && count($dirs) === 3, 'configured folders are not read as written');
        unset($GLOBALS['PHPTHUMB_FLEXI_PROTECTED_DIRS']);
        $check(is_string(flexicontent_phpthumb_protected_refusal(array('src' => $root . '/site/uploads/any.jpg'), '', $config)), 'the default folder list is not used');

        // 5. The endpoint asks before the signature check of high security, the checksum lookup and any file access
        $entry = file_get_contents($library . '/phpThumb.php');
        $call = strpos($entry, 'flexicontent_phpthumb_protected_refusal($_GET');
        $check($call !== false && $call < strpos($entry, "if (!empty(\$phpThumb->config_high_security_enabled)) {"), 'the endpoint does not check protected folders before high security');
        $check($call !== false && $call < strpos($entry, "\$phpThumb->setParameter(\$key, \$value);"), 'the endpoint does not check protected folders before the source is read');
        $check($call !== false && $call > strpos($entry, "\$phpThumb->setParameter(\$keyname, \$value);"), 'the endpoint checks protected folders before its configuration is loaded');
        $check(preg_match('/flexicontent_phpthumb_protected_refusal\(\$_GET, isset\(\$_SERVER\[\'QUERY_STRING\'\]\)/', $entry) === 1, 'the signature is not checked against the received query string');

        // 6. The helper: which images are protected, and which paths taken from content may be thumbnailed
        require $root . '/site/classes/helpers/images.php';
        $synced = new ReflectionProperty(flexicontent_images::class, 'phpthumb_protection_synced');
        foreach (array(
            'components/com_flexicontent/uploads/secret.jpg' => true, $uploads => true, '\\joomla\\components\\com_flexicontent\\uploads\\secret.jpg' => true,
            'https://fixture.invalid/joomla/components/com_flexicontent/uploads/secret.jpg?v=2' => true, '//fixture.invalid/joomla/components/com_flexicontent/uploads/secret.jpg' => true,
            $site . '/components/com_flexicontent/uploads/secret.jpg' => true, $original => true, 'images/stories/flexicontent/item_12_field_5/original/photo.jpg' => true,
            'images/stories/flexicontent/item_99_field_5/original/later.jpg' => true, '/joomla/images/item_99_field_5/original/later.jpg' => true,
            'images/public.jpg' => false, '/joomla/images/public.jpg' => false, 'images/open/item_3_field_7/original/photo.jpg' => false,
            '/joomla/images/open/item_3_field_7/original/photo.jpg' => false, 'https://fixture.invalid/joomla/images/open/item_3_field_7/original/photo.jpg' => false,
            'images/stories/flexicontent/item_12_field_5/l_photo.jpg' => false, 'secure-docs/scan.png' => false, 'https://example.org/remote.jpg' => false, '' => false,
        ) as $src => $expected) {
            $check(flexicontent_images::phpThumbProtectedSource($src) === $expected, 'protected source misjudged: ' . $src);
        }
        ComponentHelper::$params = array('file_path' => '/secure-docs/');
        $check(flexicontent_images::phpThumbProtectedFolders() === array('secure-docs'), 'the configured secure folder is not normalised');
        $check(flexicontent_images::phpThumbProtectedSource('secure-docs/scan.png') && flexicontent_images::phpThumbProtectedSource('/joomla/secure-docs/scan.png'), 'the configured secure folder is not protected');
        ComponentHelper::$params = array('file_path' => '');
        $check(flexicontent_images::phpThumbProtectedFolders() === array('components/com_flexicontent/uploads'), 'an empty setting does not fall back to the default folder');
        ComponentHelper::$params = array();
        foreach (array(
            'images/public.jpg' => true, '/joomla/images/public.jpg' => true, 'images/Photo One.PNG' => true, 'images/a.webp' => true, 'images/a.avif' => true,
            'components/com_flexicontent/uploads/secret.jpg' => false, $original => false, 'images/manual.pdf' => false, 'images/shell.php' => false, 'images/vector.svg' => false,
            'images/noextension' => false, 'images/../components/com_flexicontent/uploads/secret.jpg' => false, 'images/%2e%2e/configuration.jpg' => false,
            'http://fixture.invalid/joomla/images/public.jpg' => false, '//fixture.invalid/images/public.jpg' => false, 'file:///etc/a.png' => false,
            'C:/sites/joomla/images/public.jpg' => false, "images/a.jpg\0.png" => false, '' => false,
        ) as $src => $expected) {
            $check(flexicontent_images::phpThumbPublicImage($src) === $expected, 'content image misjudged: ' . $src);
        }
        $check(flexicontent_images::phpThumbPublicImage('http://fixture.invalid/joomla/images/public.jpg', true), 'a public URL of the site is refused');
        $check(!flexicontent_images::phpThumbPublicImage('https://example.org/remote', true) && !flexicontent_images::phpThumbPublicImage('https://fixture.invalid:8443/joomla/images/public.jpg', true)
            && !flexicontent_images::phpThumbPublicImage('https://fixture.invalid@example.org/joomla/images/public.jpg', true), 'a URL of another host, port or with a user part is accepted');
        $check(!flexicontent_images::phpThumbPublicImage('http://fixture.invalid/joomla/components/com_flexicontent/uploads/secret.jpg', true)
            && !flexicontent_images::phpThumbPublicImage('ftp://fixture.invalid/a.jpg', true) && !flexicontent_images::phpThumbPublicImage('https://fixture.invalid/joomla/images/../configuration.php', true), 'a URL into a protected folder, with parent segments or of another scheme is accepted');

        // 7. No key yet: public thumbnails stay unsigned, the first protected image creates the key and is signed
        $check(flexicontent_images::phpThumbKey() === '' && !is_file(flexicontent_images::phpThumbManagedFile()), 'the fixture starts with a key');
        $public = flexicontent_images::phpThumbURL(array('src' => 'images/public.jpg', 'w' => 100, 'h' => 100, 'zc' => 1), false);
        $check($public === '/joomla/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=%2Fjoomla%2Fimages%2Fpublic.jpg&w=100&h=100&zc=1', 'an unsigned public thumbnail URL changed: ' . $public);
        $check(!is_file(flexicontent_images::phpThumbManagedFile()), 'a public thumbnail created the key file');
        $accepted = function ($url, $drop_hash = false) use ($base, $protected, $scriptDir) {
            $query = (string) parse_url($url, PHP_URL_QUERY);
            $query = $drop_hash ? preg_replace('/&hash=[0-9a-f]+$/', '', $query) : $query;
            parse_str($query, $get);
            return flexicontent_phpthumb_refusal($get) === null
                && flexicontent_phpthumb_protected_refusal($get, $query, array('document_root' => $base) + $GLOBALS['PHPTHUMB_CONFIG'], $protected, $scriptDir) === null;
        };
        $preview = flexicontent_images::phpThumbURL(array('src' => 'components/com_flexicontent/uploads/secret.jpg', 'w' => 800, 'h' => 800, 'zc' => 1, 'q' => 95, 'f' => 'jpeg', 'ar' => 'x'), false, true);
        $state = flexicontent_images::phpThumbManagedState();
        $check($state['exists'] && strlen($state['key']) === 64 && $state['enabled'] === false, 'building a protected thumbnail URL did not create the key');
        $check($state['protected'] === array('components/com_flexicontent/uploads'), 'the key file does not list the secure folder');
        $check(flexicontent_images::phpThumbKey() === $state['key'] && !flexicontent_images::phpThumbEnforced(), 'the new key is not used, or high security was switched on');
        $check(preg_match('/&hash=[0-9a-f]{64}$/', $preview) === 1 && $accepted($preview), 'the URL of a protected image is not signed with the new key: ' . $preview);
        $check(!$accepted($preview, true), 'the same request without the signature is served');
        $check($accepted($public), 'the public thumbnail is refused once a key exists');
        $check($accepted(flexicontent_images::phpThumbURL(array('src' => $original, 'w' => 400), false, false, true)), 'the URL of an original is not signed');
        $check(flexicontent_images::phpThumbSyncConfig() === null, 'an up to date key file is rewritten');

        // The generated file is plain PHP that sets the key and the folder list when the endpoint includes it
        $load = function ($file) { $PHPTHUMB_CONFIG = array(); include $file; return $PHPTHUMB_CONFIG; };
        unset($GLOBALS['PHPTHUMB_FLEXI_PROTECTED_DIRS']);
        $loaded = $load($state['file']);
        $check($loaded === array('high_security_enabled' => false, 'high_security_password' => $state['key'], 'nohotlink_valid_domains' => array('fixture.invalid')), 'the key file sets other configuration values');
        $check($GLOBALS['PHPTHUMB_FLEXI_PROTECTED_DIRS'] === array('components/com_flexicontent/uploads'), 'the key file does not tell the endpoint the secure folder');

        // 8. Another secure folder in the component configuration reaches the endpoint with the next protected URL
        ComponentHelper::$params = array('file_path' => "secure-docs");
        $synced->setValue(null, false);
        flexicontent_images::phpThumbURL(array('src' => 'images/public.jpg', 'w' => 100), false);
        $check(flexicontent_images::phpThumbManagedState()['protected'] === array('components/com_flexicontent/uploads'), 'a public thumbnail rewrote the key file');
        $scan = flexicontent_images::phpThumbURL(array('src' => 'secure-docs/scan.png', 'w' => 100), false);
        $after = flexicontent_images::phpThumbManagedState();
        $check($after['protected'] === array('secure-docs') && $after['key'] === $state['key'], 'the changed secure folder was not written, or the key changed');
        $check($GLOBALS['PHPTHUMB_FLEXI_PROTECTED_DIRS'] === array('secure-docs') && $accepted($scan) && !$accepted($scan, true), 'the changed secure folder is not signed for');
        ComponentHelper::$params = array('file_path' => "it's \\secure\\");
        $check(flexicontent_images::phpThumbSyncConfig() === true && flexicontent_images::phpThumbManagedState()['protected'] === array("it's /secure"), 'a folder name with a quote does not survive the key file');
        unset($GLOBALS['PHPTHUMB_FLEXI_PROTECTED_DIRS']);
        $load($state['file']);
        $check($GLOBALS['PHPTHUMB_FLEXI_PROTECTED_DIRS'] === array("it's /secure"), 'a folder name with a quote breaks the key file');
        ComponentHelper::$params = array();
        $check(flexicontent_images::phpThumbSyncConfig() === true, 'the default folder was not restored');

        // 9. A query without a source is never signed
        foreach (array(array(), array('w' => 100, 'h' => 100), array('src' => '', 'w' => 100), array('src' => null), array('src' => false, 'hash' => 'x'), array('src' => array('a.jpg'))) as $i => $params) {
            $check(strpos(flexicontent_images::phpThumbQuery($params), 'hash=') === false, 'a query without a source is signed: ' . $i);
        }
        $check(flexicontent_images::phpThumbQuery(array()) === '', 'the empty query is not empty');
        $check(preg_match('/^src=%2Fimages%2Fa\.jpg&w=0&hash=[0-9a-f]{64}$/', flexicontent_images::phpThumbQuery(array('src' => '/images/a.jpg', 'w' => 0))) === 1, 'a query with a source is not signed');

        // 10. Hand written thumbnail URLs: only a URL of the endpoint itself, with plain parameters and a public local image
        $key = flexicontent_images::phpThumbKey();
        $endpoint = '/joomla/components/com_flexicontent/librairies/phpthumb/phpThumb.php';
        $plain = 'src=/joomla/images/public.jpg&amp;w=100';
        $signs = function ($html) use ($key) {
            $out = flexicontent_images::phpThumbSignHtml($html);
            if ($out === $html) return false;
            if (!preg_match('/\?([^"\')\s]+)/', html_entity_decode($out, ENT_QUOTES, 'UTF-8'), $m)) return null;
            $parts = explode('&hash=', $m[1]);
            return count($parts) === 2 && hash_equals(hash_hmac('sha256', $parts[0], $key), $parts[1]) ? true : null;
        };
        foreach (array(
            'image'                => '<img src="' . $endpoint . '?' . $plain . '">',
            'link to a larger one' => '<a href="' . $endpoint . '?' . $plain . '&amp;h=600&amp;zc=1&amp;q=90&amp;f=webp">',
            'absolute URL'         => '<img src="https://fixture.invalid' . $endpoint . '?' . $plain . '">',
            'protocol relative'    => '<img src="//fixture.invalid' . $endpoint . '?' . $plain . '">',
            'relative URL'         => '<img src="components/com_flexicontent/librairies/phpthumb/phpThumb.php?' . $plain . '">',
            'plain filters'        => '<img src="' . $endpoint . '?' . $plain . '&amp;fltr[]=gray&amp;fltr[]=usm|80|0.5|3&amp;fltr[]=crop|0|0|10|10">',
            'crop parameters'      => '<img src="' . $endpoint . '?' . $plain . '&amp;sx=10&amp;sy=10&amp;sw=0.5&amp;sh=0.5&amp;far=C&amp;bg=FFFFFF&amp;aoe=1&amp;iar=1&amp;ar=x">',
            'stale signature'      => '<img src="' . $endpoint . '?' . $plain . '&amp;hash=' . str_repeat('a', 64) . '">',
            'style'                => '<div style="background:url(' . $endpoint . '?src=/joomla/images/public.jpg&amp;w=100)">',
        ) as $label => $html) {
            $check($signs($html) === true, 'a hand written thumbnail URL is not signed: ' . $label);
        }
        foreach (array(
            'reflected request URI'             => '<a href="/index.php?x=phpThumb.php?src=/a.png&amp;md5s=0&amp;pop=1">',
            'marker in the query of a page'     => '<a href="/joomla/index.php?option=com_flexicontent&amp;x=' . $endpoint . '?' . $plain . '">',
            'page below index.php'              => '<a href="/joomla/index.php' . $endpoint . '?' . $plain . '">',
            'page with a rewritten URL'         => '<a href="/joomla/blog' . $endpoint . '?' . $plain . '">',
            'page of another folder'            => '<img src="/other' . $endpoint . '?' . $plain . '">',
            'marker behind a fragment'          => '<a href="/joomla/page#' . $endpoint . '?' . $plain . '">',
            'other script with the same name'   => '<img src="/joomla/media/phpThumb.php?' . $plain . '">',
            'path that only ends like it'       => '<img src="/joomla/xcomponents/com_flexicontent/librairies/phpthumb/phpThumb.php?' . $plain . '">',
            'relative URL behind a host'        => '<img src="https://fixture.invalid/components/com_flexicontent/librairies/phpthumb/phpThumb.php?' . $plain . '">',
            'checksum lookup'                   => '<img src="' . $endpoint . '?' . $plain . '&amp;md5s=0">',
            'empty checksum lookup'             => '<img src="' . $endpoint . '?' . $plain . '&amp;md5s=">',
            'sourceless canvas'                 => '<img src="' . $endpoint . '?' . $plain . '&amp;new=FFFFFF">',
            'download name'                     => '<a href="' . $endpoint . '?' . $plain . '&amp;down=a.html">',
            'inline name'                       => '<a href="' . $endpoint . '?' . $plain . '&amp;sia=a.html">',
            'debug output'                      => '<img src="' . $endpoint . '?' . $plain . '&amp;phpThumbDebug=9">',
            'cache bypass'                      => '<img src="' . $endpoint . '?' . $plain . '&amp;nocache=1">',
            'parameters of the print link'      => '<a href="' . $endpoint . '?' . $plain . '&amp;pop=1&amp;tmpl=component&amp;print=1">',
            'parameter list'                    => '<img src="' . $endpoint . '?' . $plain . '&amp;w[]=100">',
            'remote source'                     => '<img src="' . $endpoint . '?src=http://127.0.0.1:8080/a.png&amp;w=100">',
            'source of this host by URL'        => '<img src="' . $endpoint . '?src=https://fixture.invalid/joomla/images/public.jpg&amp;w=100">',
            'network path'                      => '<img src="' . $endpoint . '?src=//fileserver/share/a.jpg&amp;w=100">',
            'file wrapper'                      => '<img src="' . $endpoint . '?src=file:///etc/a.png">',
            'parent segments'                   => '<img src="' . $endpoint . '?src=/joomla/images/../../outside/a.jpg">',
            'encoded parent segments'           => '<img src="' . $endpoint . '?src=/joomla/images/%252e%252e/a.jpg">',
            'secure file folder'                => '<img src="' . $endpoint . '?src=' . $uploads . '&amp;w=100">',
            'original of an image field'        => '<img src="' . $endpoint . '?src=' . $original . '&amp;w=100">',
            'not an image'                      => '<img src="' . $endpoint . '?src=/joomla/configuration.php&amp;w=100">',
            'document'                          => '<img src="' . $endpoint . '?src=/joomla/images/manual.pdf&amp;w=100">',
            'source list'                       => '<img src="' . $endpoint . '?src[]=/joomla/images/public.jpg">',
            'no source'                         => '<img src="' . $endpoint . '?w=100&amp;h=100">',
            'watermark image'                   => '<img src="' . $endpoint . '?' . $plain . '&amp;fltr[]=wmi|/joomla/images/watermark.png|BR">',
            'overlay from the secure folder'    => '<img src="' . $endpoint . '?' . $plain . '&amp;fltr[]=gray&amp;fltr[]=over|' . $uploads . '">',
            'mask'                              => '<img src="' . $endpoint . '?' . $plain . '&amp;fltr=mask|/joomla/images/watermark.png">',
            'watermark text'                    => '<img src="' . $endpoint . '?' . $plain . '&amp;fltr[]=wmt|Copyright|3|BR">',
            'nested filter list'                => '<img src="' . $endpoint . '?' . $plain . '&amp;fltr[0][]=gray">',
            'stale signature, checksum lookup'  => '<img src="' . $endpoint . '?' . $plain . '&amp;md5s=0&amp;hash=' . str_repeat('a', 64) . '">',
        ) as $label => $html) {
            $check(flexicontent_images::phpThumbSignHtml($html) === $html, 'a URL that must stay unsigned is rewritten: ' . $label);
        }
        $current = '<img src="' . $endpoint . '?' . str_replace('&', '&amp;', flexicontent_images::phpThumbQuery(array('src' => $uploads, 'w' => 100))) . '">';
        $check(flexicontent_images::phpThumbSignHtml($current) === $current, 'a URL signed by the component is rewritten');

        // 11. Image paths written by authors: no thumbnail URL for a protected or non-image file
        eval('final class FixtureHtml { static ' . $methodSource(file_get_contents($root . '/site/classes/helpers/html.php'), 'extractimagesrc') . ' }');
        $row = function ($src) { return (object) array('introtext' => '<p><img alt="" src="' . $src . '"></p>', 'fulltext' => ''); };
        foreach (array(
            'images/public.jpg' => 'images/public.jpg', '/images/public.jpg' => '/images/public.jpg', 'images/absent.jpg' => '',
            'components/com_flexicontent/uploads/secret.jpg' => '', '/components/com_flexicontent/uploads/secret.jpg' => '',
            'images/stories/flexicontent/item_12_field_5/original/photo.jpg' => '', 'images/stories/flexicontent/item_12_field_5/l_photo.jpg' => 'images/stories/flexicontent/item_12_field_5/l_photo.jpg',
            'images/../components/com_flexicontent/uploads/secret.jpg' => '', 'images/manual.pdf' => '', 'httpdocs/../components/com_flexicontent/uploads/secret.jpg' => '',
            'https://example.org/remote.jpg' => '', 'http://fixture.invalid/joomla/images/public.jpg' => 'http://fixture.invalid/joomla/images/public.jpg',
            'http://fixture.invalid/joomla/components/com_flexicontent/uploads/secret.jpg' => '', 'ftp://example.org/a.jpg' => '', '//fixture.invalid/joomla/images/public.jpg' => '',
        ) as $src => $expected) {
            $check(FixtureHtml::extractimagesrc($row($src)) === $expected, 'image of an item text misjudged: ' . $src);
        }
        $check(FixtureHtml::extractimagesrc((object) array('introtext' => 'no image', 'fulltext' => '<img src="images/public.jpg">')) === 'images/public.jpg', 'the image of the full text is lost');

        $source = file_get_contents($root . '/site/tasks/core.php');
        eval(substr($source, strpos($source, 'class FlexicontentTasksCore')));
        $class = new ReflectionClass(FlexicontentTasksCore::class);
        $task = $class->newInstanceWithoutConstructor();
        $fromText = $class->getMethod('_extractTextImage');
        foreach (array(
            '<img src="images/public.jpg">' => 'images/public.jpg', '<img src="/images/public.jpg?v=3">' => 'images/public.jpg',
            '<img src="components/com_flexicontent/uploads/secret.jpg">' => '', '<img src="images/stories/flexicontent/item_12_field_5/original/photo.jpg">' => '',
            '<img src="components/com_flexicontent/uploads/secret.jpg"><img src="images/public.jpg">' => 'images/public.jpg',
            '<img src="components/com_flexicontent/%75ploads/secret.jpg">' => '', '<img src="images/manual.pdf">' => '', '<img src="images/absent.jpg">' => '',
            '<img src="https://example.org/remote.jpg">' => 'https://example.org/remote.jpg',
        ) as $text => $expected) {
            $check($fromText->invoke($task, $text) === $expected, 'image of an autocomplete item misjudged: ' . $text);
        }

        // The preview of a file field: a registered URL or path gets a thumbnail URL only for a public image
        $template = str_replace("\r\n", "\n", file_get_contents($root . '/plugins/flexicontent_fields/file/tmpl/field_InlineBoxes.php'));
        $from = strpos($template, 'switch((int) $file_data->url)');
        $to = strpos($template, "\$preview_text = '';", $from);
        $check($from !== false && $to !== false, 'the preview block of the file field is missing');
        $previewOf = function ($file_data) use ($template, $from, $to) {
            $mediapath = 'components/com_flexicontent/medias';
            $docspath = 'components/com_flexicontent/uploads';
            $image_placeholder = 'placeholder';
            eval(substr($template, $from, $to - $from));
            return html_entity_decode($preview_src, ENT_QUOTES, 'UTF-8');
        };
        foreach (array('components/com_flexicontent/uploads/secret.jpg', '/joomla/components/com_flexicontent/uploads/secret.jpg', $original,
            'http://fixture.invalid/joomla/components/com_flexicontent/uploads/secret.jpg', 'images/manual.pdf', 'images/../configuration.php', 'file:///etc/a.png') as $registered) {
            $check($previewOf((object) array('url' => 1, 'filename' => $registered, 'secure' => 0)) === 'placeholder', 'a registered URL gets a thumbnail URL: ' . $registered);
        }
        foreach (array('images/public.jpg' => '/joomla/images/public.jpg', 'https://fixture.invalid/joomla/images/public.jpg' => 'https://fixture.invalid/joomla/images/public.jpg') as $registered => $src) {
            $url = $previewOf((object) array('url' => 1, 'filename' => $registered, 'secure' => 0));
            parse_str((string) parse_url($url, PHP_URL_QUERY), $get);
            $check(isset($get['src'], $get['hash']) && $get['src'] === $src, 'a registered public image lost its preview: ' . $registered);
        }
        $url = $previewOf((object) array('url' => 0, 'filename' => 'secret.jpg', 'secure' => 1));
        $check($accepted($url) && !$accepted($url, true), 'the preview of an uploaded secure file is not a signed URL');

        // 12. The cache limits are applied more often than once per hour
        $cache = $base . '/cache';
        mkdir($cache);
        $stats = $cache . DIRECTORY_SEPARATOR . 'phpThumbCacheStats.txt';
        $check(flexicontent_phpthumb_cache_cleanup_due($cache, 300) === false, 'a clean-up is brought forward without a previous one');
        touch($stats);
        $check(flexicontent_phpthumb_cache_cleanup_due($cache . DIRECTORY_SEPARATOR, 300) === false && filemtime($stats) > time() - 60, 'a clean-up is repeated at once');
        touch($stats, time() - 400);
        foreach (array(0, -5, 3600, 86400) as $interval) {
            $check(flexicontent_phpthumb_cache_cleanup_due($cache, $interval) === false, 'the interval ' . $interval . ' does not keep the hourly clean-up');
        }
        $check(flexicontent_phpthumb_cache_cleanup_due($cache . DIRECTORY_SEPARATOR, 300) === true, 'a clean-up is not brought forward after the interval');
        // No clearstatcache() here: phpThumb reads the time right after this call, in the same request
        $check(filemtime($stats) < time() - 3600, 'the next clean-up is still skipped by phpThumb');
        $library_class = file_get_contents($library . '/phpthumb.class.php');
        $check(strpos($library_class, "(filemtime(\$phpThumbCacheStats_filename) >= (time() - 3600))") !== false, 'phpThumb no longer decides by the age of phpThumbCacheStats.txt');
        $shipped = file_get_contents($library . '/phpThumb.config.php');
        $check(preg_match('/^\$PHPTHUMB_FLEXI_CACHE_CLEANUP_INTERVAL\s*=\s*(\d+);/m', $shipped, $m) === 1 && $m[1] >= 60 && $m[1] <= 900, 'the shipped clean-up interval is not between one and fifteen minutes');
        $due = strpos($entry, 'flexicontent_phpthumb_cache_cleanup_due($phpThumb->config_cache_directory, isset($PHPTHUMB_FLEXI_CACHE_CLEANUP_INTERVAL)');
        $check($due !== false && $due < strpos($entry, '$phpThumb->CleanUpCacheDirectory();'), 'the endpoint does not bring the clean-up forward');
    } finally {
        $remove($base);
    }

    if ($failures) {
        fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n");
        exit(1);
    }
    echo $checks . " checks passed\n";
}
