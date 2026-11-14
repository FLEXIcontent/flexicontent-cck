<?php
/** Standalone query contract: php _tools/tests/file-index-custom-properties.php */
define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);
// The packaged plugin expects installed Joomla paths for its shared helpers.
// Supply only those two files in an isolated temporary directory.
$testRoot = sys_get_temp_dir() . '/flexi-file-index-' . bin2hex(random_bytes(8));
$helperDir = $testRoot . '/components/com_flexicontent/classes/helpers';
if (!mkdir($helperDir, 0700, true)) throw new RuntimeException('Cannot create test fixture');
register_shutdown_function(function() use ($testRoot, $helperDir) {
    foreach (array('security.php', 'fileeditor.php') as $file) if (is_file($helperDir . '/' . $file)) unlink($helperDir . '/' . $file);
    foreach (array($helperDir, dirname($helperDir), dirname($helperDir, 2), dirname($helperDir, 3), $testRoot) as $dir) rmdir($dir);
});
foreach (array('security.php', 'fileeditor.php') as $file) copy(__DIR__ . '/../../site/classes/helpers/' . $file, $helperDir . '/' . $file);
define('JPATH_SITE', $testRoot);
define('JPATH_ADMINISTRATOR', realpath(__DIR__ . '/../../admin'));
define('JPATH_BASE', JPATH_ADMINISTRATOR);
class JLoader { public static function register($name, $path) {} }
class FCField {}
class IndexParameters {
    public $values;
    public function __construct($values) { $this->values = $values; }
    public function get($key, $default = null) { return $this->values[$key] ?? $default; }
}
class FlexicontentFields {
    public static $calls = array();
    public static function onIndexSearch(&$field, $values, &$item, $required, $properties, $spacer, $filter) {
        self::$calls[] = array($field->field_valuesselect ?? '', $properties, $filter);
    }
    public static function onIndexAdvSearch(&$field, $values, &$item, $required, $properties, $spacer, $filter) {
        self::onIndexSearch($field, $values, $item, $required, $properties, $spacer, $filter);
    }
}
require __DIR__ . '/../../plugins/flexicontent_fields/file/file.php';
$plugin = (new ReflectionClass('plgFlexicontent_fieldsFile'))->newInstanceWithoutConstructor();
plgFlexicontent_fieldsFile::$field_types = array('file');
$checks = 0;
foreach (array(array(0,0),array(1,0),array(0,1),array(1,1)) as $enabled) {
    foreach (array('onIndexSearch','onIndexAdvSearch') as $method) {
        $field = (object) array('field_type'=>'file','issearch'=>1,'isadvsearch'=>1,'isadvfilter'=>0,
            'parameters'=>new IndexParameters(array('inputmode'=>0,'custom1_enable'=>$enabled[0],'custom2_enable'=>$enabled[1])));
        $post = null; $item = (object) array('id'=>1);
        $plugin->$method($field, $post, $item);
        list($select,$properties,$filter) = array_pop(FlexicontentFields::$calls);
        foreach (array(1,2) as $n) {
            if (strpos($select, 'file.custom' . $n) === false) throw new RuntimeException($method . ' missing rebuild column ' . $n);
            if (in_array('custom' . $n, $properties) !== (bool) $enabled[$n-1]) throw new RuntimeException('Disabled property indexed');
            $checks += 2;
        }
        if ($filter !== 'strip_tags') throw new RuntimeException('HTML filter changed');
        $checks++;
    }
}
echo "$checks passed\n";
