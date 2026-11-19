<?php
namespace Joomla\CMS\HTML {
    class HTMLHelper {
        public static array $calls = [];
        public static function _($name, ...$args) {
            if (str_starts_with($name, 'tabs.') || str_starts_with($name, 'sliders.')) throw new \RuntimeException('Removed Joomla helper called');
            self::$calls[] = [$name, $args];
            return '<!--' . $name . '-->';
        }
    }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) { throw new \ErrorException($message, 0, $severity, $file, $line); });
    define('FLEXI_J40GE', true);
    $plugin = new class {
        public $params, $row;
        public bool $showall = false;
        public int $pagescount = 2;
        public array $texts = ['Intro', 'Second', 'Third'];
        public string $_text = '';
        public function __construct() {
            $this->row = (object) ['id' => 42];
            $this->params = new class {
                public array $values = [];
                public function get($key, $default = null) { return $this->values[$key] ?? $default; }
                public function set($key, $value) { $this->values[$key] = $value; }
            };
        }
        public function _generateToc($row, $page) { return (object) ['title' => 'Page ' . $page]; }
        public function render() { include __DIR__ . '/../plugins/content/flexibreak/flexibreak/tmpl/default_tabs_sliders.php'; }
    };
    foreach ([3 => 'uitab.startTabSet', 4 => 'bootstrap.startAccordion'] as $mode => $start) {
        \Joomla\CMS\HTML\HTMLHelper::$calls = [];
        $plugin->params->set('display_method', $mode); $plugin->render();
        $calls = \Joomla\CMS\HTML\HTMLHelper::$calls;
        if ($calls[0][0] !== $start || count($calls) !== 6) throw new \RuntimeException('Wrong native rendering helpers');
        if ($mode === 4 && $calls[0][1][1]['parent'] !== true) throw new \RuntimeException('Accordion must close its previous panel');
        foreach ($plugin->texts as $text) if (!str_contains($plugin->_text, $text)) throw new \RuntimeException('Page text lost');
    }
    $plugin->showall = true; \Joomla\CMS\HTML\HTMLHelper::$calls = []; $plugin->render();
    if (\Joomla\CMS\HTML\HTMLHelper::$calls || substr_count($plugin->_text, 'articlePageEnd') !== 2) throw new \RuntimeException('Show all failed');
    echo "PASS: actual Flexibreak tabs/accordion rendering and show-all on Joomla 6\n";
}
