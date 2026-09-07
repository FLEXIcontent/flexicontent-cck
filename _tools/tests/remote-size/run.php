<?php
// CLI regression suite: real remote helper/controller methods, isolated Joomla and persistence doubles.
namespace Joomla\CMS\Component {
    class ComponentHelper {
        public static $values = array();
        public static function getParams($name) { return new \TestInput(self::$values); }
    }
}
namespace Joomla\CMS {
    class Factory {
        public static $app, $db;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($name) { return Factory::$db; } }; }
        public static function getDate($value) { return new class { public function toSql() { return '2026-01-01 00:00:00'; } }; }
    }
}
namespace Joomla\CMS\Session { class Session { public static function checkToken($type) { return true; } } }
namespace Joomla\CMS\Language { class Text { public static function _($text) { return $text; } } }
namespace {
    if (PHP_SAPI !== 'cli') { exit(1); }
    error_reporting(E_ALL);
    set_error_handler(function ($level, $message, $file, $line) {
        if (error_reporting() & $level) { throw new \ErrorException($message, 0, $level, $file, $line); }
        return false;
    });
    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    class TestInput {
        public $values, $post, $files;
        public function __construct($values = array()) { $this->values = $values; }
        public function get($key, $default = null, $filter = null) { return $this->values[$key] ?? $default; }
        public function getCmd($key, $default = '') { return $this->get($key, $default); }
        public function getArray() { return $this->values; }
    }
    class TestUser {
        public $id = 7;
        public function get($name) { return $this->$name; }
        public function authorise($action, $asset = null) { return false; }
    }
    class TestApp {
        public $input, $client = 'administrator', $messages = array();
        public function getIdentity() { return new TestUser(); }
        public function getSession() { return new TestInput(); }
        public function get($key, $default = null) { return $default; }
        public function isClient($client) { return $this->client === $client; }
        public function enqueueMessage($message, $type) { $this->messages[] = $message; }
        public function setHeader(...$args) {}
    }
    class TestDb {
        public $stored;
        public function insertObject($table, $row) { $this->stored = clone $row; }
        public function insertid() { return 101; }
    }
    class TestModel {
        public $stored, $editable = true, $id = 0;
        public function setId($id) { $this->id = $id; }
        public function getItem() { return (object) array('id' => $this->id, 'filename' => '', 'uploaded_by' => 7, 'uploaded' => '', 'published' => 1); }
        public function canEdit($record) { return $this->editable; }
        public function canEditState($record) { return true; }
        public function store($data) { $this->stored = $data; return true; }
        public function checkin(...$args) { return true; }
    }
    class FlexicontentControllerBaseAdmin {
        public $input, $task = 'save', $returnURL = '/', $refererURL = '/', $model;
        public function getModel($name) { return $this->model; }
        protected function _cleanCache() {}
        public function setRedirect($url) {}
        public function terminate($value = null, &$messages = null, $data = null) { return $value; }
    }
    class flexicontent_html { public static function dataFilter($value, ...$args) { return $value; } }
    class flexicontent_upload { public static function getExt($path) { return pathinfo($path, PATHINFO_EXTENSION); } }
    function jimport($name) {}
    function sourcePath($relative) {
        $installed = getenv('FLEXI_INSTALLED_ROOT');
        if ($installed) {
            return $installed . '/' . preg_replace(array('#^admin/#', '#^site/#'), array('administrator/components/com_flexicontent/', 'components/com_flexicontent/'), $relative);
        }
        return dirname(__DIR__, 3) . '/' . $relative;
    }
    require sourcePath('site/classes/helpers/remote.php');
    // Keep imports and the complete controller class. Only Joomla's include/bootstrap preamble is replaced.
    $source = file_get_contents(sourcePath('admin/controllers/filemanager.php'));
    $imports = substr($source, strpos($source, 'use Joomla\\CMS\\Factory;'), strpos($source, 'JLoader::register') - strpos($source, 'use Joomla\\CMS\\Factory;'));
    eval($imports . substr($source, strpos($source, 'class FlexicontentControllerFilemanager ')));
    class TestController extends FlexicontentControllerFilemanager {
        public function __construct($input) { $this->input = $input; $this->model = new TestModel(); }
        protected function _cleanCache() {}
    }
    $checks = 0;
    function check($label, $condition) {
        global $checks;
        if (!$condition) { throw new \RuntimeException('FAIL: ' . $label); }
        $checks++;
        echo 'PASS ' . $label . PHP_EOL;
    }
    function requests() { return array_values(array_filter(explode("\n", trim(file_get_contents(getenv('FLEXI_TEST_LOG')))))); }
    function configure($enabled, $hosts) {
        \Joomla\CMS\Component\ComponentHelper::$values = array('remote_probe_size' => $enabled, 'upload_extensions' => 'txt');
        flexicontent_remote::setTrustedHosts($hosts);
        $property = new \ReflectionProperty('flexicontent_remote', 'size_probe_deadline');
        $property->setValue(null, null);
    }
    function controller($mode, $url, $size = 0, $client = 'administrator') {
        $values = $mode === 'addurl' ? array('file-url-data' => $url, 'file-url-size' => $size, 'size_unit' => 'KBs') : array(
            'id' => 0, 'filename' => $url, 'filename_original' => $url, 'description' => '', 'hits' => 0,
            'secure' => 1, 'stamp' => 0, 'url' => 1, 'access' => 1, 'size' => $size, 'size_unit' => 'KBs'
        );
        $input = new TestInput($values);
        $input->post = new TestInput($values);
        $input->files = new TestInput();
        \Joomla\CMS\Factory::$app = new TestApp();
        \Joomla\CMS\Factory::$app->client = $client;
        \Joomla\CMS\Factory::$app->input = $input;
        \Joomla\CMS\Factory::$db = new TestDb();
        return new TestController($input);
    }
    function storedSize($mode, $url, $size = 0, $client = 'administrator') {
        $controller = controller($mode, $url, $size, $client);
        $controller->$mode();
        return $mode === 'addurl' ? \Joomla\CMS\Factory::$db->stored->size : $controller->model->stored['size'];
    }
    $url = getenv('FLEXI_TEST_URL');
    $host = parse_url($url, PHP_URL_HOST) . ':' . parse_url($url, PHP_URL_PORT);
    $otherHost = '127.0.0.2:' . getenv('FLEXI_TEST_OTHER');
    $otherUrl = 'http://' . $otherHost;
    flexicontent_remote::setSiteHost('127.0.0.2');
    $_SERVER['SERVER_ADDR'] = '127.0.0.2';
    configure(1, array($host));
    if (in_array('--no-curl', $argv, true)) {
        check('no-cURL process has no cURL', !function_exists('curl_init'));
        $before = count(requests());
        foreach (array('addurl', 'save') as $mode) {
            check($mode . ' stores zero without cURL', storedSize($mode, $url . '/size.txt') === 0);
            check($mode . ' preserves supplied size without cURL', storedSize($mode, $url . '/size.txt', 2) === 2048);
        }
        check('no cURL means no HTTP requests', count(requests()) === $before);
        echo $checks . ' checks passed (no cURL)' . PHP_EOL;
        exit;
    }
    check('cURL available', function_exists('curl_init'));
    foreach (array('addurl', 'save') as $mode) {
        configure(0, array($host));
        $before = count(requests());
        check($mode . ' default disabled', storedSize($mode, $url . '/size.txt') === 0);
        check($mode . ' disabled makes no HTTP request', count(requests()) === $before);
        configure(1, array());
        // The site exception makes this URL otherwise valid, but does not authorize automatic sizing.
        check($mode . ' empty list stores zero', storedSize($mode, $otherUrl . '/size.txt') === 0);
        check($mode . ' empty list makes no HTTP request', count(requests()) === $before);
        configure(1, array($host));
        check($mode . ' supplied size wins', storedSize($mode, $url . '/size.txt', 2) === 2048);
        check($mode . ' supplied size makes no HTTP request', count(requests()) === $before);
        check($mode . ' trusted file stores exact bytes', storedSize($mode, $url . '/size.txt') === 12345);
        check($mode . ' frontend internal call stores size', storedSize($mode, $url . '/size.txt', 0, 'site') === 12345);
        check($mode . ' HTTP failure still stores record', storedSize($mode, $url . '/missing.txt') === 0);
        $before = count(requests());
        check($mode . ' unlisted site host stores zero', storedSize($mode, $otherUrl . '/size.txt') === 0);
        check($mode . ' unlisted site host not contacted', count(requests()) === $before);
    }
    configure(1, array($host));
    check('relative redirect inside list', flexicontent_remote::getSizeOnSave($url . '/redirect.txt') === 12345);
    $before = count(requests());
    check('unlisted redirect rejected', flexicontent_remote::getSizeOnSave($url . '/other.txt') === 0);
    check('unlisted redirect destination not contacted', count(requests()) === $before + 1);
    $before = count(requests());
    check('port mismatch rejected', flexicontent_remote::getSizeOnSave($url . '/port.txt') === 0);
    check('unlisted port never contacted', count(requests()) === $before + 1);
    configure(1, array('127.0.0.1'));
    check('hostname-only entry permits its other ports', flexicontent_remote::getSizeOnSave($url . '/port.txt') === 12345);
    configure(1, array($host, $otherHost));
    check('explicitly listed redirect succeeds', flexicontent_remote::getSizeOnSave($url . '/other.txt') === 12345);
    configure(1, array($host));
    check('HEAD without length uses Range total', flexicontent_remote::getSizeOnSave($url . '/range.txt') === 67890);
    check('HEAD unsupported uses Range total', flexicontent_remote::getSizeOnSave($url . '/nohead.txt') === 67890);
    check('ignored Range gets size without buffering body', flexicontent_remote::getSizeOnSave($url . '/ignored.txt') === 2000000);
    $before = count(requests());
    check('Range fallback does not follow unlisted redirect', flexicontent_remote::getSizeOnSave($url . '/range-redirect.txt') === 0 && count(requests()) === $before + 2);
    check('missing length remains zero', flexicontent_remote::getSizeOnSave($url . '/unknown.txt') === 0);
    check('zero-byte response supported', flexicontent_remote::getSizeOnSave($url . '/zero.txt') === 0);
    $before = count(requests());
    check('redirect loop fails safely', flexicontent_remote::getSizeOnSave($url . '/loop.txt') === 0);
    check('redirect loop bounded', count(requests()) === $before + 6);
    $before = count(requests());
    foreach (array('ftp://127.0.0.1/file.txt', str_replace('http://', 'http://user:pass@', $url) . '/size.txt', 'http://8.8.8.8/file.txt') as $invalid) {
        check('unsafe or unlisted URL rejected: ' . parse_url($invalid, PHP_URL_SCHEME), flexicontent_remote::getSizeOnSave($invalid) === 0);
    }
    check('invalid URLs cause no HTTP traffic', count(requests()) === $before);
    $error = '';
    check('legacy headSize retains site-host policy', flexicontent_remote::headSize($otherUrl . '/size.txt', $error) === 12345);
    check('legacy proxy retains site-host exception', flexicontent_remote::resolveFinal($otherUrl . '/size.txt', $error, true)['size'] === 12345);
    check('strict mode rejects empty list', (function () use ($otherUrl, &$error) { flexicontent_remote::setTrustedHosts(array()); return flexicontent_remote::headSize($otherUrl . '/size.txt', $error, true) < 0; })());
    configure(1, array($host));
    $before = count(requests());
    $denied = controller('save', $url . '/size.txt');
    $denied->model->editable = false;
    check('save ACL still denies before probing', $denied->save() === false && $denied->model->stored === null && count(requests()) === $before);
    foreach (array('filemanager.addurl', 'ADDURL', 'filemanager.x.AddLocal') as $task) {
        \Joomla\CMS\Factory::$app->client = 'site';
        \Joomla\CMS\Factory::$app->input->values['task'] = $task;
        $denied = false;
        try { include sourcePath('site/controllers/filemanager.php'); } catch (\RuntimeException $e) { $denied = $e->getCode() === 403; }
        check('frontend dispatch remains blocked: ' . $task, $denied);
    }
    configure(1, array($host));
    $start = microtime(true);
    check('redirects share the supplied HTTP deadline', flexicontent_remote::headSize($url . '/delayed-redirect.txt', $error, true, $start + 0.45) < 0 && microtime(true) - $start < 0.8);
    configure(1, array($host));
    $start = microtime(true);
    check('slow server yields zero', flexicontent_remote::getSizeOnSave($url . '/slow.txt') === 0);
    $elapsed = microtime(true) - $start;
    check('shared HTTP budget enforced', $elapsed >= 4 && $elapsed < 6);
    $before = count(requests());
    check('exhausted budget prevents later probes in same save', flexicontent_remote::getSizeOnSave($url . '/size.txt') === 0 && count(requests()) === $before);
    echo $checks . ' checks passed (real local HTTP)' . PHP_EOL;
}
