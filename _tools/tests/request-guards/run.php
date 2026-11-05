<?php
// CLI-only regression tests. No Joomla installation, database or HTTP requests.
namespace Joomla\CMS {
    class Factory {
        public static $app;
        public static function getApplication() { return self::$app; }
    }
}
namespace Joomla\CMS\Session {
    class Session {
        public static $postToken = false, $queryToken = false, $checkedTypes = array();
        public static function checkToken($type) {
            self::$checkedTypes[] = $type;
            return $type === 'post' ? self::$postToken : (self::$postToken || self::$queryToken);
        }
    }
}
namespace Joomla\CMS\Language {
    class Text { public static function _($key) { return $key; } }
}
namespace {
    if (PHP_SAPI !== 'cli') { exit(1); }
    error_reporting(E_ALL);
    set_error_handler(function ($level, $message, $file, $line) {
        if (error_reporting() & $level) { throw new \ErrorException($message, 0, $level, $file, $line); }
        return false;
    });
    class TestInput {
        public $method;
        public function __construct($method) { $this->method = $method; }
        public function getMethod() { return $this->method; }
    }
    class TestModel {
        public $purges = 0;
        public function purge() { $this->purges++; }
    }
    class FlexicontentControllerBaseAdmin {
        public $model, $modelRequests = 0, $redirect;
        public function __construct() { $this->model = new TestModel(); }
        public function getModel($name) { $this->modelRequests++; return $this->model; }
        public function setRedirect($url, $message) { $this->redirect = array($url, $message); }
    }
    class FlexicontentHelperPerm {
        public static $canIndex = false;
        public static function getPerm() { return (object) array('CanIndex' => self::$canIndex); }
    }
    $root = dirname(__DIR__, 3);
    // Execute the real complete classes, omitting only installation-specific bootstrap preambles.
    $controllerSource = file_get_contents($root . '/admin/controllers/search.php');
    eval(substr($controllerSource, strpos($controllerSource, 'class FlexicontentControllerSearch ')));
    $tasksSource = file_get_contents($root . '/site/tasks/core.php');
    eval(substr($tasksSource, strpos($tasksSource, 'class FlexicontentTasksCore')));
    $checks = 0;
    function check($label, $condition) {
        global $checks;
        if (!$condition) { throw new \RuntimeException('FAIL: ' . $label); }
        $checks++;
        echo 'PASS ' . $label . PHP_EOL;
    }
    function purge($method, $postToken, $queryToken, $canIndex) {
        \Joomla\CMS\Factory::$app = (object) array('input' => new TestInput($method));
        \Joomla\CMS\Session\Session::$postToken = $postToken;
        \Joomla\CMS\Session\Session::$queryToken = $queryToken;
        \Joomla\CMS\Session\Session::$checkedTypes = array();
        FlexicontentHelperPerm::$canIndex = $canIndex;
        $controller = new FlexicontentControllerSearch();
        $error = null;
        try { $controller->purge(); } catch (\Exception $e) { $error = $e; }
        return array($controller, $error);
    }
    foreach (array(
        array('GET with no token', 'GET', false, false, true),
        array('GET with query token', 'GET', false, true, true),
        array('GET with posted token double', 'GET', true, false, true),
        array('POST without token', 'POST', false, false, true),
        array('POST with query-only token', 'POST', false, true, true),
        array('POST with valid token but denied indexing ACL', 'POST', true, false, false)
    ) as $case) {
        list($label, $method, $post, $query, $allowed) = $case;
        list($controller, $error) = purge($method, $post, $query, $allowed);
        check($label . ' returns403', $error && $error->getCode() === 403);
        check($label . ' never reaches the model', $controller->modelRequests === 0 && $controller->model->purges === 0);
        check($label . ' has no success redirect', $controller->redirect === null);
    }
    list($controller, $error) = purge('POST', true, false, true);
    check('authorized token-bearing POST purges once', !$error && $controller->model->purges === 1);
    check('CSRF validation explicitly uses POST', \Joomla\CMS\Session\Session::$checkedTypes === array('post'));
    check('successful purge keeps existing redirect/message', $controller->redirect === array('index.php?option=com_flexicontent&view=search', 'FLEXI_ITEMS_PURGED'));

    $sameOrigin = new \ReflectionMethod('FlexicontentTasksCore', 'hasSameOrigin');
    $sameOrigin->setAccessible(true);
    $cases = array(
        array('HTTP default', 'example.test', 'http://example.test/edit', array(), false, true),
        array('explicit HTTP default equivalent', 'example.test:80', 'http://example.test/edit', array(), false, true),
        array('explicit referer HTTP default equivalent', 'example.test', 'http://example.test:80/edit', array(), false, true),
        array('nonstandard HTTP port', '127.0.0.1:8765', 'http://127.0.0.1:8765/administrator/index.php', array(), false, true),
        array('host and scheme case-insensitive', 'EXAMPLE.test:8765', 'HTTP://example.TEST:8765/edit', array(), false, true),
        array('HTTPS default', 'example.test', 'https://example.test/edit', array('HTTPS' => 'on'), false, true),
        array('explicit HTTPS default equivalent', 'example.test:443', 'https://example.test/edit', array('HTTPS' => '1'), false, true),
        array('nonstandard HTTPS port', 'example.test:8443', 'https://example.test:8443/edit', array('HTTPS' => 'ON'), false, true),
        array('HTTPS off is HTTP', 'example.test', 'http://example.test/edit', array('HTTPS' => 'off'), false, true),
        array('IPv6 with nonstandard port', '[::1]:8765', 'http://[::1]:8765/edit', array(), false, true),
        array('IPv6 HTTPS default equivalent', '[2001:db8::1]:443', 'https://[2001:DB8::1]/edit', array('HTTPS' => 'on'), false, true),
        array('trusted TLS-terminating proxy', 'example.test:8443', 'https://example.test:8443/edit', array('HTTP_X_FORWARDED_PROTO' => 'https'), true, true),
        array('untrusted forwarded scheme ignored', 'example.test', 'https://example.test/edit', array('HTTP_X_FORWARDED_PROTO' => 'https'), false, false),
        array('untrusted header does not change direct HTTP', 'example.test', 'http://example.test/edit', array('HTTP_X_FORWARDED_PROTO' => 'https'), false, true),
        array('ambiguous trusted proxy scheme rejected', 'example.test', 'https://example.test/edit', array('HTTP_X_FORWARDED_PROTO' => 'https,http'), true, false),
        array('scheme mismatch', 'example.test', 'https://example.test/edit', array(), false, false),
        array('port mismatch', 'example.test:8765', 'http://example.test:8766/edit', array(), false, false),
        array('missing nondefault port', 'example.test:8765', 'http://example.test/edit', array(), false, false),
        array('different host', 'example.test', 'http://other.test/edit', array(), false, false),
        array('suffix lookalike host', 'example.test', 'http://example.test.other.test/edit', array(), false, false),
        array('missing referer', 'example.test', '', array(), false, false),
        array('missing host', '', 'http://example.test/edit', array(), false, false),
        array('relative referer', 'example.test', '/edit', array(), false, false),
        array('scheme-relative referer', 'example.test', '//example.test/edit', array(), false, false),
        array('non-HTTP referer', 'example.test', 'ftp://example.test/edit', array(), false, false),
        array('referer credentials', 'example.test', 'http://user@example.test/edit', array(), false, false),
        array('request authority credentials', 'user@example.test', 'http://example.test/edit', array(), false, false),
        array('request authority path', 'example.test/path', 'http://example.test/edit', array(), false, false),
        array('request authority query', 'example.test?q=1', 'http://example.test/edit', array(), false, false),
        array('invalid port', 'example.test:70000', 'http://example.test/edit', array(), false, false),
        array('zero port', 'example.test:0', 'http://example.test:0/edit', array(), false, false),
        array('whitespace rejected', 'example.test', 'http://example.test/with space', array(), false, false),
        array('backslash rejected', 'example.test', 'http://example.test\\other/edit', array(), false, false)
    );
    foreach ($cases as $case) {
        list($label, $host, $referer, $extra, $trusted, $expected) = $case;
        $server = array_merge(array('HTTP_HOST' => $host, 'HTTP_REFERER' => $referer), $extra);
        check($label, $sameOrigin->invoke(null, $server, $trusted) === $expected);
    }

    // The legitimate toolbar already uses the token-bearing POST form; avoid introducing a GET caller.
    $form = file_get_contents($root . '/admin/views/search/tmpl/default.php');
    check('search adminForm remains POST', preg_match('/<form[^\r\n]*method="post"[^\r\n]*id="adminForm"/', $form) === 1);
    check('search form retains Joomla CSRF token', strpos($form, "HTMLHelper::_('form.token')") !== false);
    check('task allowlist remains restricted', strpos($tasksSource, "array('txtautocomplete', 'viewtags')") !== false);
    check('AJAX header gate remains in constructor', strpos($tasksSource, 'HTTP_X_REQUESTED_WITH') !== false && strpos($tasksSource, 'if (!$is_ajax)') !== false);
    echo $checks . ' checks passed; no HTTP or database activity.' . PHP_EOL;
}
