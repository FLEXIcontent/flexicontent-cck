<?php
/** Execute the shipped category CSV view, including its rows and field-access guard. */
namespace Joomla\CMS\MVC\View {
    class HtmlView {
        public function getModel() { return $GLOBALS['csvModel']; }
        public function get($name) { return $this->getModel()->getCategory(); }
    }
}
namespace Joomla\CMS {
    class Factory {
        public static function getApplication() { return $GLOBALS['csvApp']; }
        public static function getUser() { return (object) ['id' => 0]; }
    }
}
namespace Joomla\CMS\Access {
    class Access { public static function getAuthorisedViewLevels($id) { return [1]; } }
}
namespace Joomla\CMS\Component {
    class ComponentHelper { public static function getParams($name) { return $GLOBALS['csvParams']; } }
}
namespace Joomla\CMS\Plugin {
    class PluginHelper { public static function isEnabled($folder, $name) { return false; } }
}
namespace {
    define('_JEXEC', 1);
    function jimport($name) {}
    class ExportFinished extends \RuntimeException {}
    function jexit() { throw new ExportFinished(); }
    class FlexicontentFields {
        public static function getItemFields(&$items, &$_vars, $view, $aid) {}
        public static function loadFieldConfig($field, $item) {}
    }
    class CsvParams {
        public function __construct(private array $values) {}
        public function get($key, $default = null) { return $this->values[$key] ?? $default; }
        public function set($key, $value) { $this->values[$key] = $value; }
    }
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    require __DIR__ . '/../site/views/category/view.csv.php';
    $checks = 0;
    $assert = static function ($actual, $expected, $label) use (&$checks) {
        if ($actual !== $expected) { throw new \RuntimeException($label . ': ' . json_encode([$actual, $expected])); }
        $checks++;
    };
    $groups = [
        [[['a', 'b'], ['c', 'd']], 'a | b, c | d'],
        [[['a', 'b'], 'c'], 'a | b, c'],
        [['a', ['b', 'c']], 'a, b | c'],
        [['a', 'c'], 'a, c'],
        [['0', ['1', '2']], '0, 1 | 2'],
        [[], ''],
    ];
    foreach ([',', ';', "\t"] as $separator) {
        foreach ($groups as [$values, $expected]) {
            $csvParams = new CsvParams(['show_csvbutton' => 1, 'csv_export_field_sep' => $separator, 'csv_export_header' => 1]);
            $csvApp = new class {
                public $input;
                public function __construct() { $this->input = new CsvParams([]); }
                public function isClient($name) { return $name === 'site'; }
                public function getIdentity() { return (object) ['id' => 0]; }
            };
            $field = static function ($id, $name, $label, $core = false, $allowed = true) {
                return (object) ['id' => $id, 'name' => $name, 'label' => $label, 'field_type' => $core ? 'title' : 'selectmultiple', 'iscore' => $core, 'has_access' => $allowed, 'parameters' => new CsvParams(['include_in_csv_export' => 1])];
            };
            $title = "Title; tab\t comma, quote\" CR\rLF\n中文 and slash\\";
            $fields = ['title' => $field(1, 'title', 'Title' . $separator . 'label', true),
                'options' => $field(2, 'options', 'Options'), 'private' => $field(3, 'private', 'Private', false, false)];
            $csvModel = new class ($csvParams, $fields, $title, $values) {
                public function __construct(private $params, private $fields, private $title, private $values) {}
                public function getCategory() { return (object) ['parameters' => $this->params]; }
                public function getState($name) { return $name === 'limit' ? 100 : null; }
                public function setState($name, $value) {}
                public function getData() { return [(object) ['fields' => $this->fields, 'title' => $this->title, 'fieldvalues' => [2 => $this->values, 3 => ['SECRET_DO_NOT_EXPORT']]]]; }
            };
            // display() closes its incoming output buffer before sending the download.
            ob_start(); ob_start();
            try { (new \FlexicontentViewCategory())->display(); }
            catch (ExportFinished $e) {}
            $body = ob_get_clean();
            $assert(substr($body, 0, 3), "\xEF\xBB\xBF", 'UTF-8 BOM');
            $stream = fopen('php://temp', 'r+'); fwrite($stream, substr($body, 3)); rewind($stream);
            $header = fgetcsv($stream, 0, $separator, '"', '');
            $row = fgetcsv($stream, 0, $separator, '"', '');
            $assert($header, ['Title' . $separator . 'label', 'Options', 'Private'], 'Header round-trip');
            $assert($row, [$title, $expected, ''], 'Values, quoting and denied field');
            $assert(fgetcsv($stream, 0, $separator, '"', ''), false, 'No diagnostic or extra rows');
            fclose($stream);
        }
    }
    restore_error_handler();
    echo $checks . " category CSV checks passed\n";
}
