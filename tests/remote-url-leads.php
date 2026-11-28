<?php
/**
 * Remote URL guard: the site-host exception, and the form of the host that is resolved, pinned and requested.
 * Runs the real flexicontent_remote code with a fixture name resolver; no Joomla bootstrap, no DNS, no HTTP.
 *
 * IDN hosts are converted with the intl extension here. To use Joomla's own converter instead (the one a site uses):
 *   JOOMLA_ROOT=/path/to/joomla php tests/remote-url-leads.php
 */
namespace Joomla\Registry {
    final class Registry {
        private $data;
        public function __construct($data = null) { $this->data = is_string($data) ? (array) json_decode($data, true) : (array) $data; }
        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
        public function set($key, $value) { $this->data[$key] = $value; }
        public function toString() { return json_encode($this->data); }
    }
}
namespace Joomla\Database { interface DatabaseInterface {} }
namespace Joomla\CMS\Component {
    final class ComponentHelper {
        public static $params;
        public static function getParams($option) { return self::$params; }
    }
}
namespace Joomla\CMS\Uri {
    final class Uri {
        public static $host = '';
        public static function getInstance() { return new self; }
        public function getHost() { return self::$host; }
    }
}
namespace Joomla\CMS {
    final class Factory {
        public static $live_site = '';
        public static $client = 'site';
        public static $rights = array();
        public static $db;
        public static function getApplication() {
            return new class {
                public function get($key, $default = null) { return $key === 'live_site' ? Factory::$live_site : $default; }
                public function isClient($client) { return $client === Factory::$client; }
                public function getIdentity() {
                    return new class { public function authorise($action, $asset) { return in_array($action . '@' . $asset, Factory::$rights, true); } };
                }
            };
        }
        public static function getContainer() {
            return new class { public function get($name) { if ($name !== \Joomla\Database\DatabaseInterface::class) throw new \RuntimeException('no ' . $name); return Factory::$db; } };
        }
    }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_ROOT', dirname(__DIR__));

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };

    // The component configuration row: the parameters of the request (cached copy) and the stored ones
    \Joomla\CMS\Component\ComponentHelper::$params = new \Joomla\Registry\Registry;
    final class ConfigRowDb implements \Joomla\Database\DatabaseInterface {
        public $params = '{}'; public $writes = 0; private $query;
        public function getQuery($new = false) {
            return new class { public $set = null;
                public function select($c) { return $this; } public function from($t) { return $this; } public function where($w) { return $this; }
                public function update($t) { return $this; } public function set($s) { $this->set = $s; return $this; } };
        }
        public function qn($name) { return $name; }
        public function q($value) { return "'" . $value . "'"; }
        public function setQuery($query) { $this->query = $query; return $this; }
        public function loadObject() { return (object) array('extension_id' => 7, 'params' => $this->params); }
        public function execute() { ++$this->writes; $this->params = substr($this->query->set, strlen("params = '"), -1); return true; }
    }

    if (getenv('JOOMLA_ROOT')) {
        require getenv('JOOMLA_ROOT') . '/libraries/vendor/autoload.php';
        require getenv('JOOMLA_ROOT') . '/libraries/src/String/PunycodeHelper.php';
    }
    $converter = class_exists('\Joomla\CMS\String\PunycodeHelper', false) ? 'Joomla PunycodeHelper' : (function_exists('idn_to_ascii') ? 'intl' : '');

    // Names resolve from this table only. IP literals resolve to themselves, like in the real resolver.
    final class RemoteFixture {
        public static $names = array();
        public static $asked = array();
        public static function resolve($host) {
            $host = trim((string) $host, '.[]');
            self::$asked[] = $host;
            if (filter_var($host, FILTER_VALIDATE_IP)) return array($host);
            return isset(self::$names[$host]) ? (array) self::$names[$host] : array();
        }
    }

    // The real class, with only the system resolver replaced by the table above
    $source = file_get_contents(JPATH_ROOT . '/site/classes/helpers/remote.php');
    $source = str_replace(
        'public static function resolveHost($host)',
        'public static function resolveHost($host) { return RemoteFixture::resolve($host); } public static function resolveHostSystem($host)',
        $source, $replaced
    );
    if ($replaced !== 1) { fwrite(STDERR, "resolveHost() was not found in remote.php\n"); exit(1); }
    eval('?>' . $source);

    $site_host = new ReflectionProperty('flexicontent_remote', 'site_host');
    $validate = static function ($url, $live_site = '', $server_name = '', $server_addr = '', $trusted = array()) use ($site_host) {
        \Joomla\CMS\Factory::$live_site = $live_site;
        $_SERVER['SERVER_NAME'] = $server_name;
        $_SERVER['HTTP_HOST']   = $server_name;
        $_SERVER['SERVER_ADDR'] = $server_addr;
        $site_host->setValue(null, null);
        flexicontent_remote::setTrustedHosts($trusted);
        RemoteFixture::$asked = array();
        $error = '';
        $target = flexicontent_remote::validateUrl($url, $error);
        return array($target, $error);
    };
    $ascii_only = static function ($target) {
        return preg_match('/^[\x21-\x7e]+$/', $target['url'] . $target['host'] . $target['pin']) === 1 && strpos($target['host'], '%') === false;
    };
    $utf8 = static function ($codepoint) {
        return chr(0xE0 | ($codepoint >> 12)) . chr(0x80 | (($codepoint >> 6) & 0x3F)) . chr(0x80 | ($codepoint & 0x3F));
    };
    $fullwidth = static function ($text) use ($utf8) {
        $out = '';
        foreach (str_split($text) as $c) { $out .= $c === '.' ? '.' : $utf8(0xFEE0 + ord($c)); }
        return $out;
    };

    $e_acute = "\xC3\xA9";
    $public  = '93.184.216.34';
    RemoteFixture::$names = array(
        'intranet.example'         => '10.0.0.5',
        'www.example.org'          => $public,
        'my_files.example.org'     => $public,
        'localhost'                => '127.0.0.1',
        'xn--9ca.pin.test'         => $public,
        'xn--9ca.intranet.example' => '10.0.0.5',
        // The raw name and its punycode form answer differently: only the punycode form may be used
        $e_acute . '.attacker.example' => $public,
        'xn--9ca.attacker.example'     => '169.254.169.254',
        '%c3%a9.attacker.example'      => $public,
    );

    // 1. Without a configured live_site, a host taken from the request never gets the site-host exception
    $port_error = 'Only the default HTTP / HTTPS ports are allowed for hosts that are not trusted';
    $private_error = 'Host resolves to a non-public address';
    $spoofed = array(
        // URL, SERVER_NAME, SERVER_ADDR, expected error
        array('http://127.0.0.1:8080/a.png', '127.0.0.1', '127.0.0.1', $port_error),
        array('http://127.0.0.1/a.png', '127.0.0.1', '127.0.0.1', $private_error),
        array('http://[::1]:8080/a.png', '[::1]', '127.0.0.1', $port_error),
        array('http://[::1]/a.png', '[::1]', '::1', $private_error),
        array('http://10.0.0.5:9200/a.png', '10.0.0.5', '10.0.0.5', $port_error),
        array('https://10.0.0.5/a.png', '10.0.0.5', '10.0.0.5', $private_error),
        array('http://intranet.example/a.png', 'intranet.example', '10.0.0.5', $private_error),
        array('http://localhost/a.png', 'localhost', '127.0.0.1', $private_error),
        array('http://8.8.4.4:8443/a.png', '8.8.4.4', '203.0.113.9', $port_error),
    );
    foreach ($spoofed as $case) {
        list($target, $error) = $validate($case[0], '', $case[1], $case[2]);
        $check($target === false && $error === $case[3], 'no live_site, SERVER_NAME ' . $case[1] . ': ' . $case[0] . ' gave ' . json_encode($target ? $target['url'] : $error));
        $check(flexicontent_remote::getSiteHost() === '', 'no live_site, SERVER_NAME ' . $case[1] . ': there is no site host');
    }
    list($target) = $validate('http://8.8.4.4/a.png', '', '8.8.4.4', '203.0.113.9', array('files.example.org'));
    $check($target && $target['is_site'] === false && $target['trusted'] === false, 'no live_site: a public host named by the request is an ordinary host for the trusted hosts policy');

    // 2. A configured live_site keeps the exception, and request data does not change it
    list($target, $error) = $validate('http://intranet.example:8080/a.png', 'https://intranet.example/site', 'intranet.example', '10.0.0.5');
    $check($target && $target['is_site'] === true && $target['url'] === 'http://intranet.example:8080/a.png', 'live_site host on the web server address keeps the exception: ' . $error);
    list($target, $error) = $validate('http://127.0.0.1:8080/a.png', 'https://intranet.example/site', '127.0.0.1', '127.0.0.1');
    $check($target === false && $error === $port_error, 'live_site set: SERVER_NAME does not replace it');
    list($target, $error) = $validate('http://intranet.example/a.png', 'https://intranet.example', 'intranet.example', '203.0.113.9');
    $check($target === false && $error === $private_error, 'live_site host on another private address is still refused');

    // 3. Without live_site, the trusted hosts list is the way to allow the site's own private host
    list($target, $error) = $validate('http://intranet.example:8080/a.png', '', 'intranet.example', '10.0.0.5', array('intranet.example'));
    $check($target && $target['trusted'] === true && $target['is_site'] === false, 'no live_site: a listed host is accepted: ' . $error);

    // 4. Plain ASCII hosts are handled as before
    $plain = array(
        'http://www.example.org/a.png'           => array('http://www.example.org/a.png', 'www.example.org', 'www.example.org:80:' . $public),
        'HTTPS://WWW.Example.ORG./a.png?x=1'     => array('https://www.example.org/a.png?x=1', 'www.example.org', 'www.example.org:443:' . $public),
        'http://my_files.example.org/a.png'      => array('http://my_files.example.org/a.png', 'my_files.example.org', 'my_files.example.org:80:' . $public),
        'http://8.8.8.8/a.png'                   => array('http://8.8.8.8/a.png', '8.8.8.8', '8.8.8.8:80:8.8.8.8'),
        'http://[2001:4860:4860::8888]/a.png'    => array('http://[2001:4860:4860::8888]/a.png', '2001:4860:4860::8888', '2001:4860:4860::8888:80:[2001:4860:4860::8888]'),
        'http://xn--9ca.pin.test/b.mp3'          => array('http://xn--9ca.pin.test/b.mp3', 'xn--9ca.pin.test', 'xn--9ca.pin.test:80:' . $public),
    );
    foreach ($plain as $url => $expected) {
        list($target, $error) = $validate($url);
        $check($target && array($target['url'], $target['host'], $target['pin']) === $expected, 'plain host unchanged: ' . $url . ' gave ' . json_encode($target ?: $error));
    }

    // 5. A host that libcurl would rewrite before its own lookup is never resolved or pinned as it was written
    $rewritten = array(
        'percent-encoded host'     => 'http://%c3%a9.attacker.example/x.mp3',
        'percent-encoded dot'      => 'http://www%2eexample%2eorg/x.mp3',
        'bracketed hostname'       => 'http://[www.example.org]/x.mp3',
        'bracketed IPv4'           => 'http://[8.8.8.8]/x.mp3',
        'invalid UTF-8'            => "http://\xC3.attacker.example/x.mp3",
        'fullwidth colon'          => 'http://a' . $utf8(0xFF1A) . '80.attacker.example/x.mp3',
        'fullwidth slash'          => 'http://a' . $utf8(0xFF0F) . 'b.attacker.example/x.mp3',
        'fullwidth percent'        => 'http://a' . $utf8(0xFF05) . '25.attacker.example/x.mp3',
        'fullwidth at'             => 'http://a' . $utf8(0xFF20) . 'b.attacker.example/x.mp3',
        'IDN answering differently' => 'http://' . $e_acute . '.attacker.example/x.mp3',
        'fullwidth localhost'      => 'http://' . $fullwidth('localhost') . '/x.mp3',
        'fullwidth 127.0.0.1'      => 'http://' . $fullwidth('127.0.0.1') . '/x.mp3',
        'circled digits 127.0.0.1' => 'http://' . $utf8(0x2460) . $utf8(0x2461) . $utf8(0x2466) . '.0.0.1/x.mp3',
    );
    foreach ($rewritten as $label => $url) {
        list($target, $error) = $validate($url);
        $check($target === false, $label . ' is refused, gave ' . json_encode($target ? $target['url'] : $error));
        $check(preg_grep('/[^a-z0-9_.:-]/', RemoteFixture::$asked) === array(), $label . ': only a plain hostname is ever resolved, asked ' . json_encode(array_map('bin2hex', RemoteFixture::$asked)));
    }
    list(, $error) = $validate($rewritten['percent-encoded host']);
    $check($error === 'URL is not valid' && RemoteFixture::$asked === array(), 'percent-encoded host is refused before any lookup');

    // 6. IDN hosts: the punycode form is what gets resolved, checked, pinned and requested
    if ($converter === '') {
        list($target, $error) = $validate('http://' . $e_acute . '.pin.test/b.mp3');
        $check($target === false && $error === 'URL is not valid' && RemoteFixture::$asked === array(), 'without a converter an IDN host is refused before any lookup');
        echo "IDN conversion checks skipped: neither Joomla's PunycodeHelper nor the intl extension is loaded\n";
    } else {
        list($target, $error) = $validate($rewritten['IDN answering differently']);
        $check($error === $private_error && RemoteFixture::$asked === array('xn--9ca.attacker.example'), 'IDN host is judged by the addresses of its punycode form');

        $expected = array('http://xn--9ca.pin.test/b.mp3?x=1', 'xn--9ca.pin.test', 'xn--9ca.pin.test:80:' . $public);
        foreach (array('lower case' => $e_acute, 'upper case' => "\xC3\x89") as $label => $letter) {
            list($target, $error) = $validate('http://' . $letter . '.PIN.test/b.mp3?x=1');
            $check($target && array($target['url'], $target['host'], $target['pin']) === $expected, 'IDN host (' . $label . ') is resolved, pinned and requested as punycode: ' . json_encode($target ?: $error));
            $check(RemoteFixture::$asked === array('xn--9ca.pin.test'), 'IDN host (' . $label . '): only the punycode form is resolved');
        }

        // Redirect Location values come straight from the remote server
        list($start) = $validate('http://www.example.org/dir/a.mp3');
        foreach (array('http://' . $e_acute . '.pin.test/b.mp3?x=1', '//' . $e_acute . '.pin.test/b.mp3?x=1') as $location) {
            list($target, $error) = $validate(flexicontent_remote::resolveRedirect($location, $start));
            $check($target && array($target['url'], $target['host'], $target['pin']) === $expected && $ascii_only($target), 'redirect to an IDN host is pinned as punycode: ' . json_encode($target ?: $error));
        }
        list($target, $error) = $validate(flexicontent_remote::resolveRedirect('http://' . $e_acute . '.attacker.example/b.mp3', $start));
        $check($target === false && $error === $private_error, 'redirect to an IDN host with a private punycode address is refused');

        // The trusted hosts list and live_site may name the host in either form
        list($target, $error) = $validate('http://' . $e_acute . '.intranet.example:8080/a.png', '', '', '', array("\xC3\x89.Intranet.example:8080", 'files.example.org'));
        $check(flexicontent_remote::getTrustedHosts() === array('xn--9ca.intranet.example:8080', 'files.example.org'), 'IDN trusted hosts entry is kept as punycode');
        $check($target && $target['trusted'] === true && $target['pin'] === 'xn--9ca.intranet.example:8080:10.0.0.5', 'IDN trusted hosts entry matches the IDN URL: ' . $error);
        list($target, $error) = $validate('http://xn--9ca.intranet.example:8081/a.png', '', '', '', array($e_acute . '.intranet.example:8080'));
        $check($target === false && $error === $port_error, 'IDN trusted hosts entry keeps its port');
        list($target, $error) = $validate('http://xn--9ca.intranet.example:8080/a.png', 'https://' . $e_acute . '.intranet.example/', '', '10.0.0.5');
        $check($target && $target['is_site'] === true, 'IDN live_site host keeps the site-host exception: ' . $error);
    }

    // Site host of the component configuration: used without live_site, taken once from a configuration administrator's backend request
    $params  = \Joomla\CMS\Component\ComponentHelper::$params;
    $admin   = array('core.admin@com_flexicontent');
    $remember = static function ($host, $client, $rights, $live_site = '', $stored = '{}', $cached = '') use ($params, $site_host) {
        \Joomla\CMS\Factory::$db = new ConfigRowDb;
        \Joomla\CMS\Factory::$db->params = $stored;
        \Joomla\CMS\Factory::$client = $client;
        \Joomla\CMS\Factory::$rights = $rights;
        \Joomla\CMS\Factory::$live_site = $live_site;
        \Joomla\CMS\Uri\Uri::$host = $host;
        $params->set('remote_site_host', $cached);
        $site_host->setValue(null, null);
        $result = flexicontent_remote::rememberSiteHost();
        return array($result, \Joomla\CMS\Factory::$db->writes, (string) (new \Joomla\Registry\Registry(\Joomla\CMS\Factory::$db->params))->get('remote_site_host', ''));
    };

    list($result, $writes, $stored) = $remember('Intranet.Example', 'administrator', $admin);
    $check($result === 'intranet.example' && $writes === 1 && $stored === 'intranet.example', 'configuration administrator in the backend: the host is stored');
    $check(flexicontent_remote::getSiteHost() === 'intranet.example', 'the stored host is the site host of the same request');
    list($result, $writes, $stored) = $remember('intranet.example', 'administrator', $admin, '', '{"other":"kept"}');
    $check((new \Joomla\Registry\Registry(\Joomla\CMS\Factory::$db->params))->get('other') === 'kept', 'other component parameters are kept');
    foreach (array(
        'frontend request'                => array('evil.example', 'site', $admin, '', '{}', ''),
        'backend user without the right'  => array('evil.example', 'administrator', array('core.manage@com_flexicontent'), '', '{}', ''),
        'live_site is set'                => array('evil.example', 'administrator', $admin, 'https://www.example.org/', '{}', ''),
        'a host is already configured'    => array('evil.example', 'administrator', $admin, '', '{}', 'intranet.example'),
        'a host is already stored'        => array('evil.example', 'administrator', $admin, '', '{"remote_site_host":"intranet.example"}', ''),
        'host with markup'                => array('evil.example"><x', 'administrator', $admin, '', '{}', ''),
        'empty host'                      => array('', 'administrator', $admin, '', '{}', ''),
    ) as $label => $case) {
        list($result, $writes) = $remember(...$case);
        $check($result === '' && $writes === 0, 'nothing is stored: ' . $label);
    }

    // The stored host gives the exception, the host of the request still does not
    $params->set('remote_site_host', 'https://Intranet.Example:8080/site/');
    list($target, $error) = $validate('http://intranet.example:8080/a.png', '', 'evil.example', '10.0.0.5');
    $check($target && $target['is_site'] === true, 'stored site host (typed as a URL) keeps the site-host exception: ' . $error);
    list($target, $error) = $validate('http://10.0.0.5:9200/a.png', '', '10.0.0.5', '10.0.0.5');
    $check($target === false && $error === $port_error, 'request host is still not the site host when another host is stored');
    list($target, $error) = $validate('http://intranet.example:8080/a.png', 'https://www.example.org/', '', '10.0.0.5');
    $check($target === false && flexicontent_remote::getSiteHost() === 'www.example.org', 'live_site takes precedence over the stored host');
    foreach (array('[::1]:8080' => '::1', '127.0.0.1:81' => '127.0.0.1', 'bad host' => '', 'a/b' => 'a', '' => '') as $typed => $expected) {
        $params->set('remote_site_host', $typed);
        $site_host->setValue(null, null);
        \Joomla\CMS\Factory::$live_site = '';
        $check(flexicontent_remote::getSiteHost() === $expected, 'stored value ' . json_encode($typed) . ' gives site host ' . json_encode($expected) . ', got ' . json_encode(flexicontent_remote::getSiteHost()));
    }
    $params->set('remote_site_host', '');

    echo 'IDN converter: ' . ($converter ?: 'none') . "\n";
    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
