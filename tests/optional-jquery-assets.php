<?php
/** Execute the actual framework loader with a recording document, without Joomla or a database. */
namespace Joomla\Registry {
    class Registry {
        public function __construct(public array $values = []) {}
        public function get($name, $default = null) { return $this->values[$name] ?? $default; }
    }
}
namespace Joomla\CMS {
    class Factory { public static $app; public static function getApplication() { return self::$app; } }
}
namespace Joomla\CMS\Component {
    class ComponentHelper { public static $params; public static function getParams($name) { return self::$params; } }
}
namespace Joomla\CMS\Uri {
    class Uri { public static function root($path = false) { return $path ? '' : 'https://fixture.invalid/'; } public static function base($path = false) { return self::root($path); } }
}
namespace Joomla\CMS\Language { class Text { public static function script(...$args) {} } }
namespace Joomla\CMS\Plugin { class PluginHelper { public static function isEnabled(...$args) { return false; } } }
namespace Joomla\CMS\HTML {
    class HTMLHelper {
        public static function _($name, ...$args) {
            if ($name !== 'jquery.framework') throw new \RuntimeException('Unexpected dependency: ' . $name);
            \Joomla\CMS\Factory::$app->document->events[] = 'jquery';
        }
    }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($level, $message, $file, $line) { throw new \ErrorException($message, 0, $level, $file, $line); });
    define('_JEXEC', 1); define('FLEXI_J40GE', true); define('FLEXI_J30GE', true); define('FLEXI_VHASH', 'test');
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
    require __DIR__ . '/../site/classes/helpers/html.php';
    $framework = $argv[1] ?? 'fancybox'; $manual = ($argv[2] ?? '') === 'manual';
    $document = new class {
        public array $events = [];
        public function addScript($url, ...$args) { $this->events[] = $url; }
        public function addStyleSheet($url, ...$args) { $this->events[] = $url; }
        public function addScriptDeclaration($script) {}
        public function addStyleDeclaration($style) {}
    };
    \Joomla\CMS\Factory::$app = new class($document) {
        public function __construct(public $document) {}
        public function isClient($client) { return $client === 'site'; }
        public function getDocument() { return $this->document; }
        public function getInput() { return new class { public function getCmd($key, $default = '') { return $default; } }; }
        public function enqueueMessage($text, $type) { throw new \RuntimeException($text); }
    };
    \Joomla\CMS\Component\ComponentHelper::$params = new \Joomla\Registry\Registry([
        'select_lib_type' => 1, 'loadfw_jquery' => 2,
        'loadfw_' . strtolower(str_replace('-', '_', $framework)) => $manual ? 2 : 1,
    ]);
    flexicontent_html::loadFramework('form-utils');
    if (in_array('jquery', $document->events, true)) throw new \RuntimeException('Native page preloaded jQuery');
    $before = $document->events;
    flexicontent_html::loadFramework($framework);
    $once = $document->events;
    flexicontent_html::loadFramework($framework);
    if ($once !== $document->events) throw new \RuntimeException('Duplicate widget dependencies');
    $legacy = in_array($framework, ['fancybox', 'galleriffic', 'elastislide', 'multibox', 'fcxSlide', 'image-picker', 'zTree'], true);
    $count = count(array_filter($once, static fn($asset) => $asset === 'jquery'));
    if ($count !== ($legacy && !$manual ? 1 : 0)) throw new \RuntimeException('Incorrect conditional jQuery loading');
    if ($manual && $once !== $before) throw new \RuntimeException('Per-widget manual loading option ignored');
    if (str_contains(implode('\n', $once), 'jquery-ui')) throw new \RuntimeException('Unnecessary jQuery UI');
    if ($legacy && !$manual && array_search('jquery', $once, true) !== count($before)) throw new \RuntimeException('Plugin precedes its dependency');
    echo "PASS: $framework " . ($manual ? 'manual opt-out' : 'dependencies and deduplication') . "\n";
}
