<?php
/**
 * The phpThumb endpoint fetches a remote image only from a host name of the site that FLEXIcontent wrote to its
 * configuration, never from the host name of the request, only on the port of the scheme, and it follows a redirect
 * only to such a host. Runs the real guard, the real helper and the real URL reader of the library.
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
        public static function getParams($option)
        {
            return new class {
                public function get($name, $default = null) { return $default; }
            };
        }
    }
}

namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    final class flexicontent_remote
    {
        public static $site = 'fixture.invalid';
        public static $trusted = array();
        public static function getSiteHost() { return self::$site; }
        public static function getTrustedHosts() { return self::$trusted; }
    }

    $root = dirname(__DIR__);
    $library = $root . '/site/librairies/phpthumb';
    $base = str_replace('\\', '/', sys_get_temp_dir()) . '/fc-thumb-remote-' . getmypid();
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
    $remove = function ($dir) use (&$remove) {
        foreach (is_dir($dir) ? array_diff(scandir($dir), array('.', '..')) : array() as $name) {
            is_dir($dir . '/' . $name) && !is_link($dir . '/' . $name) ? $remove($dir . '/' . $name) : unlink($dir . '/' . $name);
        }
        is_dir($dir) && rmdir($dir);
    };
    $source = function ($file) use ($root) {
        return str_replace("\r\n", "\n", file_get_contents($root . '/' . $file));
    };

    $server = null;

    try {
        mkdir($scriptDir, 0777, true);
        mkdir($site . '/images', 0777, true);
        file_put_contents($site . '/images/public.jpg', 'image');
        copy($library . '/phpThumb.config.php', $scriptDir . '/phpThumb.config.php');
        copy($library . '/phpthumb.functions.php', $scriptDir . '/phpthumb.functions.php');

        require $library . '/phpThumb.flexi_guard.php';

        // 1. One URL against the host names of the site
        $hosts = array('www.example.org', 'Media.Example.org');
        foreach (array(
            'https://www.example.org/images/a.jpg', 'http://www.example.org/images/a.jpg', 'HTTPS://WWW.EXAMPLE.ORG/a.jpg', 'https://www.example.org:443/a.jpg',
            'http://www.example.org:80/a.jpg', 'https://media.example.org/a.jpg?v=2', 'https://www.example.org./a.jpg', 'https://www.example.org',
        ) as $url) {
            $check(flexicontent_phpthumb_url_refusal($url, $hosts) === null, 'a URL of the site is refused: ' . $url);
        }
        foreach (array(
            'https://example.org/a.jpg', 'https://evil.test/a.jpg', 'https://www.example.org.evil.test/a.jpg', 'https://evilwww.example.org/a.jpg',
            'https://www.example.org@evil.test/a.jpg', 'https://evil.test@www.example.org/a.jpg', 'https://user:pass@www.example.org/a.jpg',
            'https://www.example.org:8443/a.jpg', 'http://www.example.org:443/a.jpg', 'https://www.example.org:80/a.jpg', 'http://www.example.org:8080/a.jpg',
            'https://www.example.org:22/a.jpg', 'https://www.example.org%2eevil.test/a.jpg', 'https://www.example.org\\@evil.test/a.jpg',
            'https://www.example.org evil.test/a.jpg', "https://www.example.org\0.evil.test/a.jpg", 'https:///a.jpg', 'https://', 'https:/www.example.org/a.jpg',
            'ftp://www.example.org/a.jpg', '//www.example.org/a.jpg', '/images/a.jpg', 'www.example.org/a.jpg', '', 'https://[::1]/a.jpg', 'https://127.0.0.1/a.jpg',
        ) as $url) {
            $check(is_string(flexicontent_phpthumb_url_refusal($url, $hosts)), 'a URL of another host, port or shape is accepted: ' . json_encode($url));
        }
        $check(is_string(flexicontent_phpthumb_url_refusal(array('https://www.example.org/a.jpg'), $hosts)), 'a URL that is not a string is accepted');
        $check(flexicontent_phpthumb_url_refusal('https://[2001:db8::1]/a.jpg', array('2001:db8::1')) === null, 'a listed IPv6 host is refused');
        $check(flexicontent_phpthumb_url_refusal('https://[2001:db8::1]:443/a.jpg', array('[2001:DB8::1]')) === null, 'a listed IPv6 host with the port of the scheme is refused');
        $check(is_string(flexicontent_phpthumb_url_refusal('https://[2001:db8::1]:8443/a.jpg', array('2001:db8::1'))), 'an IPv6 host on another port is accepted');

        // 2. No host name known: nothing is fetched. The host name of the request is never a reason
        $_SERVER['HTTP_HOST'] = 'evil.test';
        foreach (array(array(), array(''), array(' '), array(null), array(array('evil.test'))) as $empty) {
            $check(is_string(flexicontent_phpthumb_url_refusal('https://evil.test/a.jpg', $empty)), 'a URL is accepted without a configured host name');
        }
        $check(is_string(flexicontent_phpthumb_remote_refusal(array('src' => 'https://evil.test/a.jpg'), array())), 'the host name of the request is accepted as source');

        // 3. The request: the source and every file named inside a filter
        foreach (array(
            array('src' => '/images/a.jpg'), array('src' => 'images/a.jpg', 'fltr' => array('wmi|/images/mark.png|BR', 'gray')), array(), array('src' => ''),
            array('src' => 'https://www.example.org/a.jpg'), array('src' => ' https://www.example.org/a.jpg'),
            array('src' => '/a.jpg', 'fltr' => array('wmi|https://www.example.org/mark.png|BR')), array('src' => '/a.jpg', 'fltr' => 'over|https://media.example.org/o.png'),
            array('src' => '/a.jpg', 'w' => 10, 'fltr' => array('wmt|Copyright|3|BR|000000')),
        ) as $get) {
            $check(flexicontent_phpthumb_remote_refusal($get, $hosts) === null, 'a request of the component is refused: ' . json_encode($get));
        }
        foreach (array(
            array('src' => 'https://evil.test/a.jpg'), array('src' => 'http://169.254.169.254/latest/meta-data/'), array('src' => 'https://www.example.org:8443/a.jpg'),
            array('src' => "\thttps://evil.test/a.jpg"), array('src' => 'HTTP://EVIL.TEST/a.jpg'), array('src' => 'https:evil.test/a.jpg'),
            array('src' => '/a.jpg', 'fltr' => array('wmi|https://evil.test/mark.png|BR')), array('src' => '/a.jpg', 'fltr' => array('gray', 'over|http://localhost:8080/o.png')),
            array('src' => '/a.jpg', 'fltr' => 'mask|https://www.example.org@evil.test/m.png'),
            array('src' => 'https://www.example.org/a.jpg', 'fltr' => array('wmi|https://evil.test/mark.png')),
        ) as $get) {
            $check(is_string(flexicontent_phpthumb_remote_refusal($get, $hosts)), 'a request for another host is accepted: ' . json_encode($get));
        }
        $check(flexicontent_phpthumb_remote_refusal(array('src' => array('https://evil.test/a.jpg'), 'fltr' => array(array('x'))), $hosts) === null, 'values that are not strings are not left to the other checks');
        $check(is_string(flexicontent_phpthumb_refusal(array('src' => array('https://evil.test/a.jpg')))), 'a source that is not a string passes the request guard');

        // 4. Redirects are followed only to a host of the list the endpoint checked the request against
        unset($GLOBALS['PHPTHUMB_FLEXI_REMOTE_HOSTS']);
        $check(!flexicontent_phpthumb_may_follow('https://www.example.org/a.jpg'), 'a redirect is followed without a host list');
        $GLOBALS['PHPTHUMB_FLEXI_REMOTE_HOSTS'] = 'www.example.org';
        $check(!flexicontent_phpthumb_may_follow('https://www.example.org/a.jpg'), 'a host list that is not a list is used');
        $GLOBALS['PHPTHUMB_FLEXI_REMOTE_HOSTS'] = $hosts;
        $check(flexicontent_phpthumb_may_follow('https://www.example.org/moved/a.jpg') && flexicontent_phpthumb_may_follow('http://media.example.org/a.jpg'), 'a redirect inside the site is not followed');
        foreach (array('https://evil.test/a.jpg', 'http://127.0.0.1/server-status', 'https://www.example.org:8443/a.jpg', 'file:///etc/passwd', '/local/path', 'gopher://www.example.org/') as $target) {
            $check(!flexicontent_phpthumb_may_follow($target), 'a redirect is followed to ' . $target);
        }

        // 5. The shipped configuration names no host, whatever the request says
        $shipped = $source('site/librairies/phpthumb/phpThumb.config.php');
        $check(preg_match('/^\$PHPTHUMB_CONFIG\[\'nohotlink_valid_domains\'\]\s*=\s*array\(\);/m', $shipped) === 1, 'the shipped list of valid domains is not empty');
        $check(preg_match('/^\$PHPTHUMB_CONFIG\[\'nohotlink_enabled\'\]\s*=\s*true;/m', $shipped) === 1, 'the hot-link rule is switched off');
        $check(preg_match_all('/^\$PHPTHUMB_CONFIG\[\'nohotlink_[a-z_]+\'\][^\n]*\$_SERVER/m', $shipped) === 0, 'the hot-link rule of the shipped configuration reads the request');

        // 6. The endpoint applies the rule with the configured list, before the signature check and before any fetch
        $entry = $source('site/librairies/phpthumb/phpThumb.php');
        $call = strpos($entry, "flexicontent_phpthumb_remote_refusal(\$_GET, \$GLOBALS['PHPTHUMB_FLEXI_REMOTE_HOSTS'])");
        $list = strpos($entry, "\$GLOBALS['PHPTHUMB_FLEXI_REMOTE_HOSTS'] = is_array(\$phpThumb->config_nohotlink_valid_domains) ? \$phpThumb->config_nohotlink_valid_domains : array();");
        $check($call !== false && $list !== false && $list < $call, 'the endpoint does not check remote sources against the configured host names');
        $check($call !== false && $call < strpos($entry, "if (!empty(\$phpThumb->config_high_security_enabled)) {"), 'the endpoint checks remote sources after the signature');
        $check($call !== false && $call < strpos($entry, 'phpthumb_functions::SafeURLread('), 'the endpoint fetches before it checks the host');
        $check($call !== false && $call > strpos($entry, "\$phpThumb->setParameter(\$keyname, \$value);"), 'the endpoint checks the host before its configuration is loaded');
        $refused = substr($entry, $call, 400);
        $check(strpos($refused, "\$phpThumb->ErrorImage('Request refused: ' . \$flexi_refusal);") !== false && strpos($refused, 'exit;') !== false, 'a refused remote source is not the end of the request');
        $check(preg_match('/preg_match\(\'#\^http:\/\/\'\s*\.\s*@?\$_SERVER\[\'HTTP_HOST\'\]/', $entry) === 0, 'the host name of the request is used as a pattern');

        // 7. The URL reader of the library: every way it follows a redirect asks first
        $functions = $source('site/librairies/phpthumb/phpthumb.functions.php');
        $reader = substr($functions, strpos($functions, 'public static function SafeURLread('), strpos($functions, 'public static function EnsureDirectoryExists(') - strpos($functions, 'public static function SafeURLread('));
        $check(preg_match('/Location\\\\\\\\: \(http\.\*\)#i\', \$errstr, \$matches\) && \(!function_exists\(\'flexicontent_phpthumb_may_follow\'\) \|\| flexicontent_phpthumb_may_follow\(trim\(\$matches\[1\]\)\)\)/', $reader) === 1, 'the socket reader follows a redirect without asking');
        $check(strpos($reader, 'curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);') !== false && substr_count($reader, 'CURLOPT_FOLLOWLOCATION') === 1, 'cURL follows redirects on its own');
        $check(strpos($reader, "!flexicontent_phpthumb_may_follow(\$flexi_target)") !== false && strpos($reader, "\$flexi_hops < 5") !== false, 'the cURL redirects are not checked or not limited');
        $check(strpos($reader, "'follow_location' => 0") !== false && preg_match('/fopen\(\$url, \'rb\'\)\)/', $reader) === 0, 'the stream wrapper follows redirects on its own');

        // The real reader against a local server: a redirect to a target that is not allowed is not followed
        $php = PHP_BINARY;
        $router = $base . '/router.php';
        file_put_contents($router, '<?php
            $port = $_SERVER["SERVER_PORT"];
            $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
            if ($path === "/moved") { header("Location: http://127.0.0.1:" . $port . "/target", true, 302); echo "moved"; return; }
            if ($path === "/target") { header("Content-Type: image/jpeg"); echo "TARGET-BODY"; return; }
            http_response_code(404);
        ');
        $port = 0;
        if (function_exists('proc_open') && function_exists('stream_socket_server') && ($probe = @stream_socket_server('tcp://127.0.0.1:0'))) {
            $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
            fclose($probe);
            $server = @proc_open(array($php, '-S', '127.0.0.1:' . $port, $router), array(array('pipe', 'r'), array('file', $base . '/server.log', 'a'), array('file', $base . '/server.log', 'a')), $pipes);
            $up = false;
            for ($i = 0; is_resource($server) && $i < 50 && !$up; $i++) {
                usleep(100000);
                if ($fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1)) { $up = true; fclose($fp); }
            }
            $port = $up ? $port : 0;
        }
        if ($port) {
            require $scriptDir . '/phpthumb.functions.php';
            $error = '';
            $check(phpthumb_functions::SafeURLread('http://127.0.0.1:' . $port . '/target', $error, 5, true) === 'TARGET-BODY', 'the local server of the test does not answer: ' . $error);
            // The target is on another port than the port of its scheme, so it is not a host the endpoint may fetch from
            $GLOBALS['PHPTHUMB_FLEXI_REMOTE_HOSTS'] = array('127.0.0.1');
            $error = '';
            $data = phpthumb_functions::SafeURLread('http://127.0.0.1:' . $port . '/moved', $error, 5, true);
            $check($data === false, 'a redirect to a target that is not allowed was followed, or its answer was returned: ' . var_export($data, true));
            $check(strpos((string) $error, 'TARGET-BODY') === false, 'the body of the redirect target leaked into the error message');
        } else {
            echo "note: no local server could be started, the redirect of the real reader was not exercised\n";
        }

        // 8. The helper: host names come from stored configuration, 'host:port' entries are left out
        require $root . '/site/classes/helpers/images.php';
        flexicontent_remote::$site = 'Fixture.Invalid.';
        flexicontent_remote::$trusted = array('cdn.fixture.invalid', 'files.fixture.invalid:8443', 'fixture.invalid', 'bad host', "evil.test', 'x", '');
        $check(flexicontent_images::phpThumbRemoteHosts() === array('fixture.invalid', 'cdn.fixture.invalid'), 'the host names for remote sources are wrong: ' . json_encode(flexicontent_images::phpThumbRemoteHosts()));
        flexicontent_remote::$site = '';
        flexicontent_remote::$trusted = array();
        $check(flexicontent_images::phpThumbRemoteHosts() === array(), 'a host name is used although none is stored');
        $check(!flexicontent_images::phpThumbPublicImage('https://fixture.invalid/joomla/images/public.jpg', true) && !flexicontent_images::phpThumbPublicImage('https://evil.test/joomla/images/public.jpg', true), 'a URL is a preview source although no host name is stored');
        $check(flexicontent_images::phpThumbPublicImage('images/public.jpg'), 'a local image stopped being a preview source');

        flexicontent_remote::$site = 'fixture.invalid';
        flexicontent_remote::$trusted = array('cdn.fixture.invalid', 'files.fixture.invalid:8443');
        foreach (array(
            'https://fixture.invalid/joomla/images/public.jpg' => true, 'http://fixture.invalid/joomla/images/public.jpg' => true, 'https://cdn.fixture.invalid/pic.png' => true,
            'https://files.fixture.invalid:8443/pic.png' => false, 'https://files.fixture.invalid/pic.png' => false, 'https://evil.test/joomla/images/public.jpg' => false,
            'https://fixture.invalid:8443/joomla/images/public.jpg' => false, 'https://fixture.invalid@evil.test/a.jpg' => false, 'https://fixture.invalid.evil.test/a.jpg' => false,
        ) as $url => $expected) {
            $check(flexicontent_images::phpThumbPublicImage($url, true) === $expected, 'URL misjudged as preview source: ' . $url);
            $check(flexicontent_images::phpThumbPublicImage($url) === false, 'a URL is accepted where only paths are: ' . $url);
        }

        // 9. Building the URL of a remote image writes the host names to the managed configuration file, once
        $check(!is_file(flexicontent_images::phpThumbManagedFile()), 'the fixture starts with a managed file');
        $local = flexicontent_images::phpThumbURL(array('src' => 'images/public.jpg', 'w' => 100), false);
        $check(!is_file(flexicontent_images::phpThumbManagedFile()), 'a local thumbnail wrote the managed file');
        $remote = flexicontent_images::phpThumbURL(array('src' => 'https://fixture.invalid/joomla/images/public.jpg', 'w' => 100), false);
        $state = flexicontent_images::phpThumbManagedState();
        $check($state['exists'] && $state['hosts'] === array('fixture.invalid', 'cdn.fixture.invalid'), 'the managed file does not list the host names: ' . json_encode($state['hosts']));
        $check($state['protected'] === array('components/com_flexicontent/uploads') && strlen($state['key']) === 64 && $state['enabled'] === false, 'the managed file lost its other values');
        $load = function ($file) { $PHPTHUMB_CONFIG = array(); include $file; return $PHPTHUMB_CONFIG; };
        $loaded = $load($state['file']);
        $check(isset($loaded['nohotlink_valid_domains']) && $loaded['nohotlink_valid_domains'] === array('fixture.invalid', 'cdn.fixture.invalid'), 'the endpoint does not get the host names from the managed file');
        parse_str((string) parse_url($remote, PHP_URL_QUERY), $get);
        $check(flexicontent_phpthumb_remote_refusal($get, $loaded['nohotlink_valid_domains']) === null, 'the URL built by the component is refused by the endpoint');
        $check(flexicontent_phpthumb_remote_refusal(array('src' => 'https://evil.test/a.jpg'), $loaded['nohotlink_valid_domains']) !== null, 'the endpoint accepts another host with the managed file');
        $check(flexicontent_images::phpThumbSyncConfig() === null, 'an up to date managed file is rewritten');

        // A changed list is written by the next synchronisation, and a file of an earlier version (no list) is completed
        flexicontent_remote::$trusted = array();
        $check(flexicontent_images::phpThumbSyncConfig() === true && flexicontent_images::phpThumbManagedState()['hosts'] === array('fixture.invalid'), 'a shorter list of host names is not written');
        $old = preg_replace('/^\$PHPTHUMB_CONFIG\[\'nohotlink_valid_domains\'\].*\R/m', '', file_get_contents($state['file']));
        file_put_contents($state['file'], $old);
        $before = flexicontent_images::phpThumbManagedState();
        $check($before['hosts'] === null && $before['key'] === $state['key'], 'the managed file of an earlier version is not read');
        $check(flexicontent_images::phpThumbSyncConfig() === true, 'the managed file of an earlier version is not completed');
        $after = flexicontent_images::phpThumbManagedState();
        $check($after['hosts'] === array('fixture.invalid') && $after['key'] === $state['key'], 'completing the managed file changed the key or lost the host names');
        flexicontent_remote::$site = '';
        $check(flexicontent_images::phpThumbSyncConfig() === true && flexicontent_images::phpThumbManagedState()['hosts'] === array(), 'an empty list of host names is not written');
        $loaded = $load($state['file']);
        $check($loaded['nohotlink_valid_domains'] === array() && is_string(flexicontent_phpthumb_remote_refusal(array('src' => 'https://fixture.invalid/a.jpg'), $loaded['nohotlink_valid_domains'])), 'remote sources stay enabled without a host name');
        flexicontent_remote::$site = 'fixture.invalid';

        // 10. The form layouts that preview a registered URL build a thumbnail URL only for an image of the site
        foreach (array(
            'plugins/flexicontent_fields/file/tmpl/field_InlineBoxes.php', 'plugins/flexicontent_fields/file/tmpl/field_Table.php',
            'plugins/flexicontent_fields/mediafile/tmpl/field_InlineBoxes.php', 'plugins/flexicontent_fields/mediafile/tmpl/field_Table.php',
        ) as $layout) {
            $template = $source($layout);
            $from = strpos($template, 'switch((int) $file_data->url)');
            $to = strpos($template, "\$preview_text = '';", (int) $from);
            $check($from !== false && $to !== false && substr_count($template, 'switch((int) $file_data->url)') === 1, 'the preview block is missing: ' . $layout);
            $check(strpos($template, '$image_placeholder = ') !== false && strpos($template, '$image_placeholder = ') < $from, 'the placeholder image is not defined before the preview: ' . $layout);
            $previewOf = function ($file_data) use ($template, $from, $to) {
                $mediapath = 'components/com_flexicontent/medias';
                $docspath = 'components/com_flexicontent/uploads';
                $image_placeholder = 'placeholder';
                eval(substr($template, $from, $to - $from));
                return html_entity_decode($preview_src, ENT_QUOTES, 'UTF-8');
            };
            foreach (array(
                'https://evil.test/a.jpg', 'http://169.254.169.254/latest/meta-data/a.jpg', 'https://fixture.invalid:8443/joomla/images/public.jpg', 'https://fixture.invalid@evil.test/a.jpg',
                'components/com_flexicontent/uploads/secret.jpg', 'https://fixture.invalid/joomla/components/com_flexicontent/uploads/secret.jpg', 'images/manual.pdf',
                'images/../configuration.php', 'file:///etc/a.png', '//evil.test/a.jpg',
            ) as $registered) {
                $check($previewOf((object) array('url' => 1, 'filename' => $registered, 'secure' => 0)) === 'placeholder', $layout . ': a registered URL gets a thumbnail URL: ' . $registered);
            }
            foreach (array('images/public.jpg' => '/joomla/images/public.jpg', 'https://fixture.invalid/joomla/images/public.jpg' => 'https://fixture.invalid/joomla/images/public.jpg') as $registered => $src) {
                $url = $previewOf((object) array('url' => 1, 'filename' => $registered, 'secure' => 0));
                parse_str((string) parse_url($url, PHP_URL_QUERY), $get);
                $check(isset($get['src'], $get['hash']) && $get['src'] === $src, $layout . ': a registered image of the site lost its preview: ' . $registered);
            }
            $url = $previewOf((object) array('url' => 0, 'filename' => 'media.jpg', 'secure' => 0));
            $check(strpos($url, 'phpThumb.php?src=') !== false && strpos($url, 'medias%2Fmedia.jpg') !== false, $layout . ': the preview of an uploaded file changed');
        }
    } finally {
        if (is_resource($server)) {
            proc_terminate($server);
            proc_close($server);
        }
        $remove($base);
    }

    if ($failures) {
        fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n");
        exit(1);
    }
    echo $checks . " checks passed\n";
}
