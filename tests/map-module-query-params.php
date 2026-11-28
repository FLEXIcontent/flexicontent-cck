<?php
/**
 * The map module builds its item query from stored module parameters. Runs the real getItemsLocations()
 * with a database double and checks that the field id, the categories and the limit reach SQL only as
 * integers or as one of the offered "DESC LIMIT <number>" values.
 */
namespace Joomla\CMS\Language {
    class Text { public static function _($text) { return $text; } }
}
namespace Joomla\Database {
    interface DatabaseInterface {}
}
namespace Joomla\CMS {
    class Factory {
        public static $db, $levels = array(1, 5);
        public static function getContainer() { return new class { public function get($id) { return Factory::$db; } }; }
        public static function getApplication() { return new class { public function getIdentity() { return new class { public function getAuthorisedViewLevels() { return Factory::$levels; } }; } }; }
        public static function getDate() { return new class { public function toSql() { return '2026-11-27 10:00:00'; } }; }
    }
}
namespace Joomla\CMS\Component {
    class ComponentHelper {
        public static $show_noauth = 0;
        public static function getParams($option) { return new class { public function get($name, $default = null) { return $name === 'show_noauth' ? ComponentHelper::$show_noauth : $default; } }; }
    }
}
namespace {
    define('_JEXEC', 1);

    $checks = 0;
    $failures = array();
    $check = function ($condition, $message) use (&$checks, &$failures) {
        $checks++;
        if (!$condition) $failures[] = $message;
    };

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

    final class DbDouble implements \Joomla\Database\DatabaseInterface {
        public $queries = array();
        public function setQuery($query) { $this->queries[] = (string) $query; return $this; }
        public function loadObjectList() { return array(); }
        public function quote($value) { return "'" . str_replace("'", "''", (string) $value) . "'"; }
        public function getNullDate() { return '0000-00-00 00:00:00'; }
    }
    final class ParamsDouble {
        private $data;
        public function __construct(array $data) { $this->data = $data; }
        public function get($key, $default = null) { return array_key_exists($key, $this->data) ? $this->data[$key] : $default; }
    }
    // Returns the categories it is given, as the real helper does for "current category only"
    final class flexicontent_cats {
        public static $seen = array();
        public static function getExtraCats($cids, $treeinclude, $curritemcats) { self::$seen = $cids; return $cids; }
    }

    $source = file_get_contents(dirname(__DIR__) . '/modules/mod_flexigooglemap/helper.php');
    eval('use Joomla\CMS\Language\Text; final class modFlexigooglemapHelper {
        public static $field_ids = array();
        public static $field_access = 1;
        public static function getField($fieldId) { self::$field_ids[] = $fieldId; return $fieldId === 12 ? (object) array("id" => 12, "access" => self::$field_access) : false; }
        public static function getFilteredItemIdsForView() { return null; }
        public static ' . methodSource($source, '_checkConfiguration') . '
        public static ' . methodSource($source, 'getItemsLocations') . '
    }');

    $run = function (array $params) {
        \Joomla\CMS\Factory::$db = new DbDouble;
        modFlexigooglemapHelper::$field_ids = array();
        flexicontent_cats::$seen = array();
        ob_start();
        modFlexigooglemapHelper::getItemsLocations(new ParamsDouble($params));
        $output = ob_get_clean();
        return array(\Joomla\CMS\Factory::$db->queries, $output);
    };

    // 1. Ordinary configuration: the query is unchanged
    list($queries, $output) = $run(array('fieldaddressid' => '12', 'catid' => array('3', '7'), 'count' => 'DESC LIMIT 50', 'method_category' => '0'));
    $check(count($queries) === 1, 'ordinary parameters: one query runs');
    $query = isset($queries[0]) ? $queries[0] : '';
    $check(strpos($query, ' WHERE b.field_id = 12 AND  rel.catid IN (3,7) AND a.state = 1') !== false, 'ordinary parameters: field id and categories are in the query');
    $check(substr($query, -31) === ' ORDER BY a.title DESC LIMIT 50','ordinary parameters: the offered limit is kept');

    // 1b. The item query applies the list rules of the component
    $check(strpos($query, " AND (a.publish_up IS NULL OR a.publish_up = '0000-00-00 00:00:00' OR a.publish_up <= '2026-11-27 10:00:00')") !== false, 'list rules: items not yet published are left out');
    $check(strpos($query, " AND (a.publish_down IS NULL OR a.publish_down = '0000-00-00 00:00:00' OR a.publish_down >= '2026-11-27 10:00:00')") !== false, 'list rules: expired items are left out');
    $check(strpos($query, ' AND c.published = 1') !== false, 'list rules: the main category must be published');
    $check(strpos($query, ' AND a.access IN (1,5) AND c.access IN (1,5) AND (ty.id IS NULL OR ty.access IN (1,5))') !== false, 'list rules: item, main category and type access levels of the visitor');
    $check(strpos($query, ' LEFT JOIN #__flexicontent_types AS ty ON ty.id = ext.type_id') !== false, 'list rules: the type is joined');
    \Joomla\CMS\Component\ComponentHelper::$show_noauth = 1;
    list($queries) = $run(array('fieldaddressid' => 12, 'catid' => 3));
    $check(isset($queries[0]) && strpos($queries[0], '.access IN') === false && strpos($queries[0], 'a.publish_up <=') !== false, 'list rules: with unauthorised listing enabled only the access levels are dropped');
    \Joomla\CMS\Component\ComponentHelper::$show_noauth = 0;
    modFlexigooglemapHelper::$field_access = 3;
    list($queries) = $run(array('fieldaddressid' => 12, 'catid' => 3));
    $check($queries === array(), 'list rules: no query when the visitor may not view the address field');
    \Joomla\CMS\Factory::$levels = array(1, 3);
    list($queries) = $run(array('fieldaddressid' => 12, 'catid' => 3));
    $check(count($queries) === 1 && strpos($queries[0], 'a.access IN (1,3)') !== false, 'list rules: a user with the field level gets the list with their own levels');
    \Joomla\CMS\Factory::$levels = array(1, 5);
    modFlexigooglemapHelper::$field_access = 1;
    $check($output === '', 'ordinary parameters: nothing is printed');

    // 2. Every limit the module form offers is accepted
    $manifest = simplexml_load_file(dirname(__DIR__) . '/modules/mod_flexigooglemap/mod_flexigooglemap.xml');
    $options = $manifest->xpath('//field[@name="count"]/option');
    $check(count($options) > 5, 'the limit options of the module form were found');
    foreach ($options as $option) {
        $value = (string) $option['value'];
        list($queries) = $run(array('fieldaddressid' => 12, 'catid' => 3, 'count' => $value));
        $check(isset($queries[0]) && substr($queries[0], -strlen(' ORDER BY a.title ' . $value)) === ' ORDER BY a.title ' . $value, 'offered limit is kept: ' . json_encode($value));
    }

    // 3. Text in the limit never reaches the query
    $hostile_limits = array(
        'DESC LIMIT 10; DELETE FROM x', 'DESC LIMIT 10 UNION SELECT password FROM #__users', ', (SELECT SLEEP(5))', "DESC LIMIT 10\n", 'DESC LIMIT 0',
        'ASC', 'desc limit 10', array('DESC LIMIT 10'), 'DESC LIMIT 99999999',
    );
    foreach ($hostile_limits as $value) {
        list($queries) = $run(array('fieldaddressid' => 12, 'catid' => 3, 'count' => $value));
        $check(isset($queries[0]) && substr($queries[0], -18) === ' ORDER BY a.title ', 'other limit text is dropped: ' . json_encode($value));
    }

    // 4. The field id reaches the field lookup and the query as an integer only
    foreach (array('12 OR 1=1', '12) UNION SELECT 1 -- ', "12'") as $value) {
        list($queries, $output) = $run(array('fieldaddressid' => $value, 'catid' => 3));
        $check(array_values(array_unique(modFlexigooglemapHelper::$field_ids)) === array(12), 'field lookup gets an integer: ' . json_encode($value));
        $check(isset($queries[0]) && strpos($queries[0], ' WHERE b.field_id = 12 AND ') !== false && strpos($queries[0], 'UNION') === false && strpos($queries[0], '1=1') === false,
            'field id is an integer in the query: ' . json_encode($value));
    }
    foreach (array('0 OR 1=1', '<script>alert(1)</script>', 'abc', '') as $value) {
        list($queries, $output) = $run(array('fieldaddressid' => $value, 'catid' => 3));
        $check($queries === array(), 'no query without a field id: ' . json_encode($value));
        $check(strpos($output, '<script') === false && strpos($output, '1=1') === false, 'the stored field id is not printed: ' . json_encode($value));
    }
    list($queries, $output) = $run(array('fieldaddressid' => '99<script>', 'catid' => 3));
    $check($queries === array() && strpos($output, 'id 99 ') !== false && strpos($output, '<script') === false, 'unknown field: the message shows the integer only');

    // 5. Categories reach the category helper and the query as integers only
    list($queries) = $run(array('fieldaddressid' => 12, 'catid' => array('3', '7) OR 1=1 -- ', 'x', array('9'), '0', '-4')));
    $check(flexicontent_cats::$seen === array(3, 7, -4), 'category helper gets integers');
    $check(isset($queries[0]) && strpos($queries[0], ' rel.catid NOT IN (3,7,-4)') !== false && strpos($queries[0], '1=1') === false, 'categories are integers in the query');
    list($queries) = $run(array('fieldaddressid' => 12, 'catid' => '3) OR (1=1'));
    $check(isset($queries[0]) && strpos($queries[0], ' rel.catid NOT IN (3)') !== false, 'a single category value is an integer in the query');
    list($queries) = $run(array('fieldaddressid' => 12, 'catid' => 'x'));
    $check($queries === array(), 'no query without a category');

    if ($failures) {
        fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n");
        exit(1);
    }
    echo $checks . " checks passed\n";
}
