<?php
/** Verify web calls retain their menu and CLI resolves the supported site service. */
namespace Joomla\CMS {
    class Factory {
        public static $application, $container;
        public static function getApplication() { return self::$application; }
        public static function getContainer() { return self::$container; }
    }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    $source = file_get_contents(__DIR__ . '/../site/helpers/route.php');
    $start = strpos($source, 'private static function getSiteMenu(');
    if ($start === false) { throw new \RuntimeException('Site-menu resolver missing'); }
    $method = ''; $depth = 0; $opened = false;
    foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
        if (is_array($token) && $token[0] === T_OPEN_TAG) { continue; }
        $method .= is_array($token) ? $token[1] : $token;
        if ($token === '{') { $depth++; $opened = true; }
        if ($token === '}' && --$depth === 0 && $opened) { break; }
    }
    eval('class RouteMenuFixture { public static function run() { return self::getSiteMenu(); } ' . $method . '}');
    $web = new class {
        public int $calls = 0;
        public object $menu;
        public function __construct() { $this->menu = (object) ['context' => 'web']; }
        public function getMenu($name, $options) {
            if ($name !== 'site' || $options !== []) { throw new \RuntimeException('Menu arguments changed'); }
            $this->calls++; return $this->menu;
        }
    };
    $site = clone $web; $site->menu = (object) ['context' => 'site service'];
    $container = new class ($site) {
        public int $calls = 0;
        public function __construct(private object $site) {}
        public function get($service) {
            if ($service !== 'Joomla\\CMS\\Application\\SiteApplication') { throw new \RuntimeException('Wrong application service'); }
            $this->calls++; return $this->site;
        }
    };
    \Joomla\CMS\Factory::$container = $container;
    \Joomla\CMS\Factory::$application = $web;
    if (RouteMenuFixture::run() !== $web->menu || $web->calls !== 1 || $container->calls !== 0) {
        throw new \RuntimeException('Web application menu changed');
    }
    $console = new \stdClass;
    \Joomla\CMS\Factory::$application = $console;
    if (RouteMenuFixture::run() !== $site->menu || $site->calls !== 1 || $container->calls !== 1) {
        throw new \RuntimeException('CLI did not resolve the site menu');
    }
    if (\Joomla\CMS\Factory::$application !== $console) { throw new \RuntimeException('Global application was replaced'); }
    restore_error_handler();
    echo "3 route menu context checks passed\n";
}
