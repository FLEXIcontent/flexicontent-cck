<?php
/**
 * Tag cloud and filter module lists follow the view levels of the visitor, the map module includes only layouts of its
 * own folder, CSV cells are not run as formulas, the JSON view has no debug dump, and the CSS of the layout builder
 * is written as CSS without being compiled.
 * Runs the real helpers and view code with doubles for Joomla and the database; files are written to a temporary folder only.
 */
namespace Joomla\Database {
    interface DatabaseInterface {}
}
namespace Joomla\CMS\MVC\View {
    class HtmlView {}
}
namespace Joomla\CMS\Language {
    final class Text { public static function _($key) { return $key; } public static function sprintf($key, ...$args) { return $key; } }
}
namespace Joomla\CMS\HTML {
    final class HTMLHelper { public static function _($name, ...$args) { return ''; } }
}
namespace Joomla\CMS\Router {
    final class Route { public static function _($url) { return $url; } }
}
namespace Joomla\Filesystem {
    final class Path { public static function clean($path, $ds = DIRECTORY_SEPARATOR) { return preg_replace('#[/\\\\]+#', $ds, $path); } }
}
namespace Joomla\CMS\Access {
    final class Access { public static $levels = array(1, 5); public static function getAuthorisedViewLevels($id) { return self::$levels; } }
}
namespace Joomla\CMS\Component {
    final class ComponentHelper {
        public static $show_noauth = 0;
        public static function getParams($option) { return new class { public function get($name, $default = null) { return $name === 'show_noauth' ? ComponentHelper::$show_noauth : $default; } }; }
    }
}
namespace Joomla\CMS {
    final class Factory {
        public static $db, $request = array();
        public static function getContainer() { return new class { public function get($id) { return Factory::$db; } }; }
        public static function getApplication() {
            return new class {
                public $input;
                public function __construct() {
                    $this->input = new class {
                        public function get($name, $default = null, $filter = 'cmd') { return isset(Factory::$request[$name]) ? Factory::$request[$name] : $default; }
                        public function getInt($name, $default = 0) { return isset(Factory::$request[$name]) ? (int) Factory::$request[$name] : $default; }
                    };
                }
                public function getIdentity() { return (object) array('id' => 0); }
            };
        }
    }
}
namespace {
    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('FC_ROOT', dirname(__DIR__));

    // Everything that the code under test writes goes below this folder
    $sandbox = sys_get_temp_dir() . DS . 'fc-layout-test-' . bin2hex(random_bytes(6));
    define('JPATH_ROOT', $sandbox);
    define('JPATH_SITE', $sandbox);

    function jimport($name) {}
    function methodSource($source, $name) {
        $start = strpos($source, 'function ' . $name . '(');
        $method = ''; $depth = 0; $opened = false;
        foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
            $method .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { ++$depth; $opened = true; }
            if ($token === '}' && --$depth === 0 && $opened) break;
        }
        return $method;
    }
    function removeTree($dir) {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..') continue;
            is_dir($dir . DS . $name) ? removeTree($dir . DS . $name) : unlink($dir . DS . $name);
        }
        rmdir($dir);
    }

    final class DbDouble implements \Joomla\Database\DatabaseInterface {
        public $queries = array();
        public function setQuery($query, $offset = 0, $limit = 0) { $this->queries[] = (string) $query; return $this; }
        public function loadObjectList($key = null) { return array(); }
        public function loadResult() { return null; }
        public function Quote($value) { return "'" . str_replace("'", "''", (string) $value) . "'"; }
        public function getNullDate() { return '0000-00-00 00:00:00'; }
    }
    final class ParamsDouble {
        private $data;
        public function __construct(array $data = array()) { $this->data = $data; }
        public function get($key, $default = null) { return array_key_exists($key, $this->data) ? $this->data[$key] : $default; }
    }
    final class flexicontent_cats {
        public static $tree = array();
        public static function getExtraCats($cids, $treeinclude, $curritemcats) { return $cids; }
        public static function getCategoriesTree() { return self::$tree; }
    }
    final class flexicontent_html {
        public static $less_compiles = 0;
        public static function loadframework($name) { if ($name === 'sabberworm') require_once FC_ROOT . '/site/librairies/php_css_parser/autoload.php'; }
        public static function checkedLessCompile() { ++self::$less_compiles; return array(); }
    }

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };

    $php_errors = array();
    set_error_handler(static function ($severity, $message, $file, $line) use (&$php_errors) {
        if (error_reporting() & $severity) $php_errors[] = $message . ' in ' . basename($file) . ':' . $line;
        return true;
    });


    // 1. Tag cloud: only tags of items that the visitor may view are counted (item, main category and type)
    require FC_ROOT . '/modules/mod_flexitagcloud/helper.php';
    $tag_query = static function (array $param_data) {
        \Joomla\CMS\Factory::$db = new DbDouble;
        $params = new ParamsDouble($param_data + array('method' => 3, 'method_types' => 0, 'categories' => array(4, 9)));
        $module = (object) array('id' => 7);
        modFlexiTagCloudHelper::getTags($params, $module);
        return count(\Joomla\CMS\Factory::$db->queries) === 1 ? \Joomla\CMS\Factory::$db->queries[0] : '';
    };

    \Joomla\CMS\Access\Access::$levels = array(1, 5);
    $query = $tag_query(array());
    $check(strpos($query, ' AND i.access IN (1,5)') !== false, 'tag cloud: the item access level is checked');
    $check(strpos($query, ' AND c.access IN (0,1,5)') !== false, 'tag cloud: the access level of the main category is checked');
    $check(strpos($query, ' AND (ty.id IS NULL OR ty.access IN (0,1,5))') !== false, 'tag cloud: the access level of the type is checked');
    $join = strpos($query, ' LEFT JOIN #__flexicontent_types AS ty ON ty.id = i.type_id');
    $check($join !== false && $join < strpos($query, ' WHERE '), 'tag cloud: the type is joined before the conditions');
    $check(strpos($query, ' JOIN #__categories AS c ON c.id = i.catid') !== false && strpos($query, ' AND c.id IN (4,9)') !== false && strpos($query, ' AND c.published = 1') !== false, 'tag cloud: category and publication conditions are unchanged');

    \Joomla\CMS\Access\Access::$levels = array('1', '2) OR (1=1', 6);
    $query = $tag_query(array());
    $check(strpos($query, ' AND i.access IN (1,2,6)') !== false && strpos($query, 'OR (1=1') === false, 'tag cloud: view levels reach the query as integers');

    \Joomla\CMS\Access\Access::$levels = array(1, 5);
    \Joomla\CMS\Component\ComponentHelper::$show_noauth = 1;
    $query = $tag_query(array());
    $check($query !== '' && strpos($query, '.access IN') === false && strpos($query, 'flexicontent_types') === false, 'tag cloud: no access condition when the listing of unauthorised content is enabled');
    \Joomla\CMS\Component\ComponentHelper::$show_noauth = 0;


    // 2. Filter module: the category selector lists only categories of the visitor's view levels
    require FC_ROOT . '/modules/mod_flexifilter/helper.php';
    $cat = static function ($id, $access) { return (object) array('id' => $id, 'title' => 'Category ' . $id, 'access' => $access, 'published' => 1); };
    $tree = array(1 => $cat(1, 1), 2 => $cat(2, 3), 3 => $cat(3, 5), 4 => $cat(4, '2'), 5 => $cat(5, 1));
    $GLOBALS['globalcats'] = array();
    foreach ($tree as $id => $c) $GLOBALS['globalcats'][$id] = (object) array('id' => $id, 'descendants' => (string) $id);
    $GLOBALS['globalcats'][1]->descendants = '1,2,3';
    $decide = static function (array $param_data) use ($tree) {
        flexicontent_cats::$tree = $tree;
        $params = new ParamsDouble($param_data);
        return array_keys(modFlexifilterHelper::decideCats($params));
    };

    \Joomla\CMS\Access\Access::$levels = array(1, 5);
    $check($decide(array('display_cat_list' => 1, 'catids' => array(1, 2, 3, 4))) === array(1, 3), 'filter module (include): categories of other view levels are left out');
    $check($decide(array('display_cat_list' => 1, 'catids' => array())) === array(1, 3, 5), 'filter module (include, no selection): all viewable categories are listed');
    $check($decide(array('display_cat_list' => 1, 'catids' => array(1), 'usesubcats' => 1)) === array(1, 3), 'filter module (include, with subcategories): restricted subcategories are left out');
    $check($decide(array('display_cat_list' => 2, 'catids' => array(1))) === array(3, 5), 'filter module (exclude): categories of other view levels are left out');
    $check($decide(array('display_cat_list' => 0, 'catids' => array(1, 2))) === array(), 'filter module: no list without a category selector');
    \Joomla\CMS\Access\Access::$levels = array(1, 2, 3, 5);
    $check($decide(array('display_cat_list' => 1, 'catids' => array(1, 2, 3, 4))) === array(1, 2, 3, 4), 'filter module: a visitor with the view levels sees every selected category');
    \Joomla\CMS\Access\Access::$levels = array(1);
    \Joomla\CMS\Component\ComponentHelper::$show_noauth = 1;
    $check($decide(array('display_cat_list' => 1, 'catids' => array(1, 2, 3, 4))) === array(1, 2, 3, 4) && $decide(array('display_cat_list' => 2, 'catids' => array(1))) === array(2, 3, 4, 5), 'filter module: every category is listed when the listing of unauthorised content is enabled');
    \Joomla\CMS\Component\ComponentHelper::$show_noauth = 0;


    // 3. Map module: the layout parameter selects files of the module's own tmpl folder only
    $module_dir = $sandbox . DS . 'mod_map';
    mkdir($module_dir . DS . 'tmpl', 0777, true);
    foreach (array('tmpl/default' => 'default', 'tmpl/custom' => 'custom', 'evil' => 'outside') as $name => $marker) {
        file_put_contents($module_dir . DS . str_replace('/', DS, $name) . '_mapLocation.php', '<?php $mapLocations[] = ' . var_export($marker, true) . ';');
        file_put_contents($module_dir . DS . str_replace('/', DS, $name) . '_mapLocations.php', '<?php $renderedMapLocations = array(' . var_export($marker, true) . ');');
    }
    $map_helper = file_get_contents(FC_ROOT . '/modules/mod_flexigooglemap/helper.php');
    $methods = methodSource($map_helper, 'renderMapLocations') . ' public static ' . methodSource($map_helper, 'renderLocation');
    $check(substr_count($methods, 'dirname(__FILE__)') === 6, 'map module: layout include code found');
    eval('final class MapHelperFixture { public static ' . str_replace('dirname(__FILE__)', var_export($module_dir, true), $methods) . ' }');

    $layouts = array(
        'default' => 'default', 'custom' => 'custom', '_:default' => 'default', '' => 'default',
        '../evil' => 'default', '..\\evil' => 'default', 'custom/../../evil' => 'default', "custom\0" => 'default', 'sub/custom' => 'default',
    );
    foreach ($layouts as $layout => $expected) {
        $params = new ParamsDouble(array('layout' => $layout));
        $items = array();
        $check(MapHelperFixture::renderMapLocations($params, $items, null) === array($expected), 'map module: locations layout ' . json_encode($layout) . ' includes the ' . $expected . ' file');
        $locations = array();
        MapHelperFixture::renderLocation($params, null, null, $locations, $items, null, 0);
        $check($locations === array($expected), 'map module: location layout ' . json_encode($layout) . ' includes the ' . $expected . ' file');
    }
    $params = new ParamsDouble(array('layout' => array('../evil')));
    $items = array();
    $check(MapHelperFixture::renderMapLocations($params, $items, null) === array('default'), 'map module: a layout that is not text falls back to the default file');


    // 4. CSV export: a cell that a spreadsheet would run as a formula is marked as text, numbers and ordinary text are unchanged
    require FC_ROOT . '/site/views/category/view.csv.php';
    $view = new FlexicontentViewCategory;
    $encode = new ReflectionMethod($view, '_encodeCSVField');
    $cells = array(
        '=1+1'                              => "'=1+1",
        '=HYPERLINK("http://x.example","y")' => '"\'=HYPERLINK(""http://x.example"",""y"")"',
        "+cmd|' /C calc'!A0"                => "'+cmd|' /C calc'!A0",
        '-2+3'                              => "'-2+3",
        '@SUM(1+1)'                         => "'@SUM(1+1)",
        "\t=1+1"                            => "'\t=1+1",
        "\r=1+1"                            => "\"'\r=1+1\"",
        '- list entry'                      => "'- list entry",
        '-5'                                => '-5',
        '-12.50'                            => '-12.50',
        '+306912345678'                     => '+306912345678',
        '0'                                 => '0',
        ''                                  => '',
        'Plain text'                        => 'Plain text',
        'a=b, c'                            => '"a=b, c"',
        'e-mail@example.org'                => 'e-mail@example.org',
        'He said "hi"'                      => '"He said ""hi"""',
    );
    foreach ($cells as $in => $expected) {
        $check($encode->invoke($view, (string) $in) === $expected, 'CSV cell ' . json_encode((string) $in) . ' is written as ' . json_encode($expected));
    }
    $check($encode->invoke($view, -5) === '-5' && $encode->invoke($view, null) === '', 'CSV cell: a number and an empty value are unchanged');


    // 5. JSON view of a category: no debug dump of an item
    $json_view = file_get_contents(FC_ROOT . '/site/views/category/view.json.php');
    $calls = array(); $exits = 0;
    foreach (token_get_all($json_view) as $token) {
        if (is_array($token) && $token[0] === T_STRING) $calls[strtolower($token[1])] = true;
        if (is_array($token) && $token[0] === T_EXIT) ++$exits;
    }
    // The only exit left is the "defined('_JEXEC') or die" guard at the top of the file
    $check(!isset($calls['print_r']) && !isset($calls['var_dump']) && !isset($calls['var_export']) && $exits === 1, 'JSON view: no dump of item data and no early exit');
    $check(strpos($json_view, "'test'") === false && strpos($json_view, 'json_encode( $items )') !== false, 'JSON view: the test switch is gone, the items are still printed as JSON');


    // 6. Layout builder: the CSS is prefixed and written as CSS. It is never compiled, so LESS functions and imports read no file
    require FC_ROOT . '/site/helpers/html/fclayoutbuilder.php';
    $builder = $sandbox . DS . 'modules' . DS . 'mod_flexicontent' . DS . 'builder' . DS;
    mkdir($builder . 'less', 0777, true);
    $secret = "<?php \$password = 'TOP-SECRET-VALUE';\n";
    file_put_contents($sandbox . DS . 'secret.php', $secret);
    file_put_contents($builder . 'less' . DS . 'other.less', ".imported-rule { color: red; }\n");
    $config = (object) array('location' => '/modules/mod_flexicontent/builder/', 'css_prefix' => '#mod_fc_7', 'layout_name' => 'builder_layout1');
    $module = (object) array('id' => 7);
    $css = '@import "other.less";' . "\n"
        . '.a { background: data-uri("../../../../secret.php"); color: red; }' . "\n"
        . '.b { background: data-uri("text/plain;base64", "../../../../secret.php"); }' . "\n"
        . '@media (max-width: 600px) { .c > d, e:hover { display: none; } }' . "\n"
        . '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }' . "\n"
        . '.x { animation: spin 2s linear; width: calc(100% - 10px); }' . "\n"
        . 'body .y { margin: 0 auto; }';

    JHtmlFclayoutbuilder::createCss($module, new ParamsDouble(array('builder_layout1_css' => $css)), $config);
    $css_file    = $builder . 'css' . DS . 'builder_layout1_7.css';
    $marker_file = $builder . 'less' . DS . 'builder_layout1_7.less';
    $written = is_file($css_file) ? file_get_contents($css_file) : '';
    $check($written !== '', 'layout builder: the CSS file is written');
    $check(strpos($written, 'TOP-SECRET') === false && strpos($written, base64_encode($secret)) === false && strpos($written, substr(base64_encode($secret), 0, 16)) === false, 'layout builder: no file content is embedded into the CSS');
    $check(strpos($written, 'imported-rule') === false, 'layout builder: no file is imported into the CSS');
    $check(strpos($written, 'data-uri("../../../../secret.php")') !== false && strpos($written, '@import url("other.less");') !== false, 'layout builder: function calls and imports stay text, as a browser reads them');
    $check(flexicontent_html::$less_compiles === 0 && !class_exists('lessc', false) && !class_exists('Less_Parser', false), 'layout builder: the LESS compiler is not used');
    $check(strpos($written, '#mod_fc_7 .a {') !== false && strpos($written, '#mod_fc_7 .c > d, #mod_fc_7 e:hover {display: none;}') !== false && strpos($written, '#mod_fc_7 .y {margin: 0 auto;}') !== false, 'layout builder: selectors are prefixed, a body selector becomes the prefix');
    $check(strpos($written, '@keyframes spin_0 {') !== false && strpos($written, 'animation: spin_0 2s linear;') !== false && strpos($written, 'width: calc(100% - 10px);') !== false, 'layout builder: animation names are made unique, values are unchanged');
    $marker = is_file($marker_file) ? file_get_contents($marker_file) : '';
    $check($marker !== '' && strpos($marker, 'data-uri') === false && strpos($marker, '@import') === false && strpos($marker, '.a') === false, 'layout builder: the marker file holds none of the entered CSS');

    // The CSS file is written again only after a module save removed the marker file
    JHtmlFclayoutbuilder::createCss($module, new ParamsDouble(array('builder_layout1_css' => '.changed { color: blue; }')), $config);
    $check(file_get_contents($css_file) === $written, 'layout builder: the CSS file is kept while the marker file exists');
    \Joomla\CMS\Factory::$request = array('id' => 7);
    $check(JHtmlFclayoutbuilder::prepareLess('/modules/mod_flexicontent/builder/less/builder_layout1_{{id}}') === '' && !is_file($marker_file), 'layout builder: a module save removes the marker file of its layout');
    JHtmlFclayoutbuilder::createCss($module, new ParamsDouble(array('builder_layout1_css' => '.changed { color: blue; }')), $config);
    $check(strpos(file_get_contents($css_file), '#mod_fc_7 .changed {color: blue;}') !== false && is_file($marker_file), 'layout builder: the CSS file is written again after a module save');

    // The posted path of the marker file cannot point at other files
    $victims = array(
        $sandbox . DS . 'victim_7.less'                           => '/victim_{{id}}',
        $sandbox . DS . 'modules' . DS . 'victim_7.less'          => '/modules/mod_flexicontent/builder/less/../../../victim_{{id}}',
        $builder . 'victim_7.less'                                => '/modules/mod_flexicontent/builder/less/../victim_{{id}}',
        $sandbox . DS . 'other_7.less'                            => '\\other_{{id}}',
        $builder . 'less' . DS . 'other.less'                     => '/modules/mod_flexicontent/builder/less/other',
    );
    foreach ($victims as $file => $posted) {
        if (!is_file($file)) file_put_contents($file, '/* kept */');
        JHtmlFclayoutbuilder::prepareLess($posted);
        $check(is_file($file), 'layout builder: posted path ' . $posted . ' removes no file');
    }
    $check(JHtmlFclayoutbuilder::prepareLess(array('/modules/mod_flexicontent/builder/less/builder_layout1_{{id}}')) === '' && is_file($marker_file), 'layout builder: a posted path that is not text removes no file');

    removeTree($sandbox);
    restore_error_handler();
    $check($php_errors === array(), 'the code raises no PHP notice or warning: ' . implode(' | ', array_slice(array_unique($php_errors), 0, 8)));
    $check(!is_dir($sandbox), 'the temporary folder is removed');

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
