<?php
/**
 * Access level of the Smart Search links, field access in the basic-index search, category path in the item lists.
 * Runs the real methods with stubs and dummy values; no Joomla bootstrap, no database (the SQL is built, not executed).
 */
namespace Joomla\Database {
    interface DatabaseInterface {}
}
namespace Joomla\Utilities {
    final class ArrayHelper { public static function toInteger($a) { return array_map('intval', (array) $a); } }
}
namespace Joomla\Registry {
    class Registry {
        public function __construct($data = null) {}
        public function get($key, $default = null) { return $default; }
        public function merge($other) { return $this; }
    }
}
namespace Joomla\CMS {
    final class Factory {
        public static $app; public static $db;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($name) { return \Joomla\CMS\Factory::$db; } }; }
    }
}
namespace Joomla\CMS\Access {
    final class Access { public static $levels = array(1, 5); public static function getAuthorisedViewLevels($uid) { return self::$levels; } }
}
namespace Joomla\CMS\Component {
    final class ComponentHelper {
        public static function isEnabled($option) { return true; }
        public static function getParams($option, $strict = false) { return new \Joomla\Registry\Registry; }
    }
}
namespace Joomla\CMS\Language {
    final class Text { public static function _($s) { return $s; } }
}
namespace Joomla\CMS\Plugin {
    final class PluginHelper { public static function getPlugin($folder, $name) { return (object) array('params' => '{}'); } }
}
namespace Joomla\CMS\Router {
    final class Route { public static function _($url) { return $url; } }
}
namespace Joomla\Plugin\Finder\Flexicontent\Extension {
    class Result extends \stdClass {
        public $access;
        public function setLanguage() {}
        public function addInstruction($group, $property) {}
        public function addTaxonomy($branch, $title, $state = 1, $access = 1, $language = '*') {}
    }
    final class Helper {
        public static function prepareContent($text, $params = null, $item = null) { return $text; }
        public static function getContentExtras($item) {}
    }
    final class Indexer { const META_CONTEXT = 3; }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    define('_JEXEC', 1);
    define('JVERSION', '6.0.0');
    define('JPATH_ROOT', dirname(__DIR__));
    define('FLEXI_J16GE', true);
    define('FLEXI_J40GE', true);
    define('FLEXI_FISH', false);
    $_SERVER['REQUEST_URI'] = '/';

    function methodSource($source, $name) {
        $start = strpos($source, 'function ' . $name . '(');
        if ($start === false) throw new \RuntimeException('Method ' . $name . ' is missing');
        $method = ''; $depth = 0; $opened = false;
        foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
            $method .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { ++$depth; $opened = true; }
            if ($token === '}' && --$depth === 0 && $opened) break;
        }
        return $method;
    }

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };


    /**
     * Stubs shared by the three parts
     */

    final class ParamsFixture {
        public $v;
        public function __construct(array $v = array()) { $this->v = $v; }
        public function get($k, $d = null) { return array_key_exists($k, $this->v) ? $this->v[$k] : $d; }
        public function set($k, $val) { $this->v[$k] = $val; }
    }
    final class InputFixture {
        public $v = array();
        public function get($k, $d = null, $filter = null) { return array_key_exists($k, $this->v) ? $this->v[$k] : $d; }
        public function getCmd($k, $d = null) { return $this->get($k, $d); }
        public function getInt($k, $d = null) { return $this->get($k, $d); }
        public function getString($k, $d = null) { return $this->get($k, $d); }
        public function getWord($k, $d = null) { return $this->get($k, $d); }
        public function set($k, $val) { $this->v[$k] = $val; }
    }
    final class UserFixture {
        public $id = 0; public $guest = 1; public $levels = array(1, 5); public $can = false;
        public function get($k, $d = null) { return $k === 'id' ? $this->id : $d; }
        public function authorise($action, $asset = null) { return $this->can; }
        public function getAuthorisedViewLevels() { return $this->levels; }
    }
    final class AppFixture {
        public $input; public $user; public $state = array();
        public function __construct() { $this->input = new InputFixture; $this->user = new UserFixture; }
        public function getIdentity() { return $this->user; }
        public function setUserState($k, $v) { $this->state[$k] = $v; }
        public function getUserState($k, $d = null) { return array_key_exists($k, $this->state) ? $this->state[$k] : $d; }
        public function isClient($client) { return $client === 'site'; }
        public function getLanguageFilter() { return false; }
        public function getLanguage() { return new class { public function getTag() { return 'en-GB'; } }; }
    }
    final class QueryFixture {
        public $select = array(); public $from = ''; public $join = array(); public $where = array();
        public function select($s) { $this->select[] = $s; return $this; }
        public function from($s) { $this->from = $s; return $this; }
        public function join($type, $s) { $this->join[] = $type . ' JOIN ' . $s; return $this; }
        public function where($s) { $this->where[] = $s; return $this; }
        public function __toString() {
            return 'SELECT ' . implode(', ', $this->select) . ' FROM ' . $this->from . ' ' . implode(' ', $this->join)
                . ($this->where ? ' WHERE ' . implode(' AND ', $this->where) : '');
        }
    }
    // Records every statement, the answers come from the callback of each part
    final class DbFixture implements \Joomla\Database\DatabaseInterface {
        public $queries = array(); public $current = ''; public $answer;
        public function getQuery($new = false) { return new QueryFixture; }
        public function setQuery($query, $offset = 0, $limit = 0) { $this->current = (string) $query; $this->queries[] = $this->current; return $this; }
        public function execute() { return true; }
        public function loadResult() { return ($this->answer)('result', $this->current); }
        public function loadObject() { return ($this->answer)('object', $this->current); }
        public function loadColumn($offset = 0) { return ($this->answer)('column', $this->current); }
        public function loadObjectList($key = '') { return ($this->answer)('list', $this->current); }
        public function getNullDate() { return '0000-00-00 00:00:00'; }
        public function quote($text, $escape = true) { return "'" . ($escape ? addslashes($text) : $text) . "'"; }
        public function escape($text, $extra = false) { return addslashes($text); }
    }

    final class flexicontent_db {
        public static $tables = array(); public static $direct = array();
        public static function getQuotedSearchTerms($text, $phrase) { return null; }
        public static function useLikeSearch($like_any, $text) { return false; }
        public static function getBooleanSearchWords($words, $tbl = 'flexicontent_items_ext', $col = 'search_index', $required = false) {
            return array_values(array_filter($words, 'strlen'));
        }
        public static function removeInvalidWords($words, &$stopwords, &$shortwords, $tbl = 'flexicontent_items_ext', $col = 'search_index', $isprefix = 1) {
            self::$tables[] = $tbl;
            return $words;
        }
        public static function buildItemOrderBy($params, &$order, ...$more) { $order = array(1 => 'title', 2 => ''); return ' ORDER BY i.title ASC'; }
        public static function getTypeData($ids, $load_params) { return array(7 => (object) array('id' => 7, 'params' => new ParamsFixture)); }
        public static function directQuery($query) { self::$direct[] = $query; return array((object) array('id' => 5, 'fc_list_total' => 1)); }
    }
    final class flexicontent_html { public static function getUserCurrentLang() { return 'en'; } }
    final class flexicontent_favs {
        public static function getInstance() { return new self; }
        public function getRecords($type) { return array(21 => 1); }
    }
    final class FlexicontentHelperRoute { public static function getItemRoute(...$args) { return 'index.php?option=com_flexicontent&view=item'; } }
    final class FlexicontentFields {
        public static $fields = array();
        public static function getSearchFields(...$args) { return self::$fields; }
        public static function getLangHandler($tag, $only = false) { return false; }
        public static function _getCategories($list) { return array(); }
    }

    \Joomla\CMS\Factory::$app = new AppFixture;
    \Joomla\CMS\Factory::$db  = new DbFixture;


    /**
     * 1. Smart Search: the link access carries the levels of the item, of its main category and of its type
     */

    $finder = file_get_contents(JPATH_ROOT . '/plugins/finder/flexicontent/flexicontent.php');
    $finder_methods = '';
    foreach (array('onFinderAfterSave', 'onFinderBeforeSave', 'index', 'getListParameter', 'getUrl', 'translateState',
        'getStateQuery', 'itemAccessChange', 'categoryAccessChange', 'typeAccessChange') as $name)
    {
        $finder_methods .= ' public ' . methodSource($finder, $name);
    }

    eval('namespace Joomla\Plugin\Finder\Flexicontent\Extension;
        use Joomla\CMS\Factory;
        use Joomla\CMS\Component\ComponentHelper;
        use Joomla\CMS\Event\Finder as FinderEvent;
        use Joomla\Database\DatabaseInterface;
        use Joomla\Registry\Registry;
        final class FinderFixture {
            protected static bool $isJ4GE = false;
            protected $extension = "com_flexicontent";
            protected $layout = "item";
            protected $table = "#__content";
            protected $state_field = "state";
            public $params; public $db; public $indexer; public $calls = array(); public $items = array();
            public $old_access; public $old_cataccess; public $old_typeaccess;
            public function __construct($db) {
                $this->db = $db;
                $this->params = new \ParamsFixture;
                $this->indexer = new class { public $indexed = array(); public function index($item) { $this->indexed[] = clone $item; } };
            }
            // The item as the list query of the adapter returns it: the item level only, without the level of the type
            public function item($id) {
                $row = $this->items[$id];
                $item = new Result;
                foreach (array("id" => $id, "title" => "Dummy", "summary" => "dummy intro", "body" => "dummy text", "state" => 1,
                    "catid" => $row->catid, "access" => $row->access, "category" => "Dummy category", "cat_state" => 1,
                    "cat_access" => $row->cat_access, "fc_type_id" => $row->type_id, "params" => "", "metadata" => "",
                    "slug" => $id . ":dummy", "catslug" => $row->catid . ":dummy", "language" => "*", "author" => "",
                    "created_by_alias" => "") as $k => $v) $item->$k = $v;
                return $item;
            }
            protected function reindex($id) { $this->calls[] = array("reindex", (int) $id); $this->index($this->item($id)); }
            protected function change($id, $property, $value) { $this->calls[] = array("change", (int) $id, $property, (int) $value); return true; }
            protected function checkItemAccess($row) {}
            protected function checkCategoryAccess($row) {}
            protected function getItemMenuTitle($url) { return ""; }
            private function removeLegacyLinks($id, bool $removeTaxonomies = true): bool { return false; }
            ' . $finder_methods . '
        }');

    // Dummy records: type 7 is Registered (2), type 8 is Public (1)
    $types = array(
        7 => (object) array('id' => 7, 'name' => 'Dummy members', 'published' => 1, 'access' => 2),
        8 => (object) array('id' => 8, 'name' => 'Dummy public', 'published' => 1, 'access' => 1),
    );
    $finder_items = array(
        101 => (object) array('id' => 101, 'state' => 1, 'access' => 1, 'catid' => 3, 'cat_access' => 1, 'type_id' => 7),
        102 => (object) array('id' => 102, 'state' => 1, 'access' => 3, 'catid' => 3, 'cat_access' => 1, 'type_id' => 7),
        103 => (object) array('id' => 103, 'state' => 1, 'access' => 1, 'catid' => 4, 'cat_access' => 2, 'type_id' => 8),
        104 => (object) array('id' => 104, 'state' => 1, 'access' => 1, 'catid' => 3, 'cat_access' => 1, 'type_id' => 8),
        105 => (object) array('id' => 105, 'state' => 1, 'access' => 1, 'catid' => 3, 'cat_access' => 1, 'type_id' => 99),
    );
    $old_type_access = 1;

    // Answers the state query of the adapter from the dummy records above
    $finder_db = \Joomla\CMS\Factory::$db;
    $finder_db->answer = static function ($kind, $sql) use (&$types, &$finder_items, &$old_type_access) {
        if (strpos($sql, 'FROM #__flexicontent_types') !== false)
        {
            return $kind === 'result' ? $old_type_access : $types;
        }
        if (!preg_match('/WHERE (a\.id|c\.id|ie\.type_id) = (\d+)$/', $sql, $m)) throw new \RuntimeException('Unexpected query: ' . $sql);
        $col  = array('a.id' => 'id', 'c.id' => 'catid', 'ie.type_id' => 'type_id')[$m[1]];
        $rows = array();
        foreach ($finder_items as $row)
        {
            if ((int) $row->$col !== (int) $m[2]) continue;
            $row = clone $row;
            $row->type_access = isset($types[$row->type_id]) ? $types[$row->type_id]->access : null;
            $rows[] = $row;
        }
        return $kind === 'object' ? ($rows ? $rows[0] : null) : $rows;
    };

    $fixtureClass = 'Joomla\Plugin\Finder\Flexicontent\Extension\FinderFixture';
    $indexedAccess = static function ($id) use ($fixtureClass, $finder_db, &$finder_items) {
        $f = new $fixtureClass($finder_db);
        $f->items = $finder_items;
        $f->index($f->item($id));
        return count($f->indexer->indexed) === 1 ? $f->indexer->indexed[0]->access : null;
    };

    $check($indexedAccess(101) === 2, 'finder: a public item of a Registered type is indexed as Registered');
    $check($indexedAccess(102) === 3, 'finder: the item level is kept when it is the highest one');
    $check($indexedAccess(103) === 2, 'finder: a public item in a Registered category is indexed as Registered');
    $check($indexedAccess(104) === 1, 'finder: a public item of a public type in a public category stays public');
    $check($indexedAccess(105) === 1, 'finder: an item of an unknown type keeps the level of item and category');

    // Saving the item with a changed access level: the reindex that follows no longer puts back the item level
    $f = new $fixtureClass($finder_db);
    $f->items = $finder_items;
    $f->old_access = 2;
    $f->onFinderAfterSave('com_content.article', (object) array('id' => 101, 'access' => 1), false);
    $check($f->calls === array(array('change', 101, 'access', 2), array('reindex', 101)), 'finder: item save updates and reindexes the link');
    $check(end($f->indexer->indexed)->access === 2, 'finder: the reindex after an item save keeps the level of the type');

    // The category of items 101, 102, 104 and 105 goes from Registered back to Public: the type level must stay
    $f = new $fixtureClass($finder_db);
    $f->old_cataccess = 2;
    $f->onFinderAfterSave('com_categories.category', (object) array('id' => 3, 'access' => 1), false);
    $check($f->calls === array(
        array('change', 101, 'access', 2), array('change', 102, 'access', 3),
        array('change', 104, 'access', 1), array('change', 105, 'access', 1),
    ), 'finder: a category access change keeps the level of the type');

    // The type 7 goes from Public to Registered
    $f = new $fixtureClass($finder_db);
    $types[7]->access = 2; $old_type_access = 1;
    $f->onFinderBeforeSave('com_flexicontent.type', (object) array('id' => 7, 'access' => 2), false);
    $check((int) $f->old_typeaccess === 1 && strpos(end($finder_db->queries), 'id = 7') !== false, 'finder: the access level of the type is read before save');
    $f->onFinderAfterSave('com_flexicontent.type', (object) array('id' => 7, 'access' => 2), false);
    $check($f->calls === array(array('change', 101, 'access', 2), array('change', 102, 'access', 3)), 'finder: a type access change updates the links of its items only');

    // ... an unchanged level or a new type does nothing
    $f = new $fixtureClass($finder_db);
    $old_type_access = 2;
    $f->onFinderBeforeSave('com_flexicontent.type', (object) array('id' => 7, 'access' => 2), false);
    $f->onFinderAfterSave('com_flexicontent.type', (object) array('id' => 7, 'access' => 2), false);
    $f->onFinderAfterSave('com_flexicontent.type', (object) array('id' => 0, 'access' => 2), true);
    $check($f->calls === array(), 'finder: a type saved with the same access level, or a new type, changes no link');


    /**
     * 2. Search plugin, basic index: a user that may not view a text-searchable field gets the per-field index
     */

    $plugin = file_get_contents(JPATH_ROOT . '/plugins/search/flexiadvsearch/flexiadvsearch.php');
    $plugin_methods = 'public ' . methodSource($plugin, 'onContentSearch') . ' public ' . methodSource($plugin, '_buildTextSearch');

    $search_db = new DbFixture;
    $has_hidden_field = null;
    $search_db->answer = static function ($kind, $sql) use (&$has_hidden_field) {
        if ($kind === 'result') return $has_hidden_field;
        if ($kind === 'list') return array((object) array('id' => 5, 'text' => 'dummy text', 'type_id' => 7, 'slug' => '5:dummy', 'categoryslug' => '3:dummy', 'language' => '*'));
        return null;
    };

    // The text search clause is a static of the method: one class per search
    $search = static function ($txtmode, $hidden_field) use ($plugin_methods, $search_db, &$has_hidden_field) {
        static $n = 0;
        $class = 'SearchFixture' . (++$n);
        eval('use Joomla\Utilities\ArrayHelper;
            final class ' . $class . ' {
                public $_params; public $_active_search;
                public function onContentSearchAreas() { return array("flexicontent" => "FLEXICONTENT"); }
                ' . $plugin_methods . '
            }');
        \Joomla\CMS\Factory::$db = $search_db;
        \Joomla\CMS\Factory::$app->input->v = array('option' => 'com_flexicontent', 'view' => 'search');
        $search_db->queries = array(); flexicontent_db::$direct = array(); flexicontent_db::$tables = array();
        $has_hidden_field = $hidden_field;
        FlexicontentFields::$fields = array(12 => (object) array('id' => 12, 'access' => 1), 13 => (object) array('id' => 13, 'access' => 2));

        $fixture = new $class;
        $fixture->_params = new ParamsFixture(array('txtmode' => $txtmode));
        $list = $fixture->onContentSearch('alpha beta', 'all');

        return (object) array(
            'list'    => $list,
            'match'   => flexicontent_db::$direct[0],
            'data'    => end($search_db->queries),
            'checks'  => array_values(array_filter($search_db->queries, static function ($q) { return strpos($q, 'FROM #__flexicontent_fields') !== false; })),
            'tables'  => flexicontent_db::$tables,
        );
    };

    // The user of the fixture is a guest with the view levels 1 and 5
    $levels_ok = 'txtf.access IN (0,1,5)';

    $r = $search(0, null);
    $check(count($r->list) === 1, 'search: the basic index search returns its result');
    $check(count($r->checks) === 1 && strpos($r->checks[0], 'published = 1 AND issearch IN (1,2) AND access NOT IN (0,1,5)') !== false,
        'search: the basic index looks for text-searchable fields that the user may not view');
    $check(strpos($r->match, "JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id AND  MATCH (ie.search_index) AGAINST ('+alpha* +beta*' IN BOOLEAN MODE)") !== false
        && strpos($r->match, 'basicsearch_index') === false && strpos($r->match, 'txtf') === false,
        'search: without such a field the merged basic index is matched as before');
    $check(strpos($r->data, ', ie.search_index AS text') !== false && strpos($r->data, ' ts') === false, 'search: without such a field the merged text is shown as before');
    $check($r->tables === array('flexicontent_items_ext'), 'search: without such a field the words are checked in the merged index');

    $r = $search(0, '1');
    $check(count($r->list) === 1, 'search: the per-field basic index search returns its result');
    $check(strpos($r->match, "JOIN #__flexicontent_basicsearch_index as ts ON ts.item_id = i.id AND  MATCH (ts.search_index) AGAINST ('+alpha* +beta*' IN BOOLEAN MODE)") !== false,
        'search: with such a field the per-field basic index is matched');
    $check(strpos($r->match, 'JOIN #__flexicontent_fields as txtf ON txtf.id=ts.field_id AND txtf.published = 1 AND txtf.issearch IN (1,2) AND ' . $levels_ok) !== false,
        'search: the matched rows are of published text-searchable fields that the user may view');
    $check(strpos($r->match, 'ie.search_index') === false && strpos($r->data, 'ie.search_index') === false, 'search: with such a field the merged text is neither matched nor shown');
    $check(strpos($r->data, "COALESCE(GROUP_CONCAT(ts.search_index ORDER BY txtf.ordering ASC SEPARATOR ' '), '') AS text") !== false
        && strpos($r->data, ' LEFT JOIN (#__flexicontent_basicsearch_index as ts JOIN #__flexicontent_fields as txtf ON txtf.id=ts.field_id'
            . ' AND txtf.published = 1 AND txtf.issearch IN (1,2) AND ' . $levels_ok . ') ON ts.item_id = i.id') !== false,
        'search: the shown text is the text of the fields that the user may view');
    $check(substr_count($r->data, 'basicsearch_index') === 1 && strpos($r->data, 'MATCH') === false, 'search: the result text is not limited to the matching rows');
    $check($r->tables === array('flexicontent_basicsearch_index'), 'search: with such a field the words are checked in the per-field index');

    // Advanced index: not changed
    $r = $search(1, '1');
    $adv_join = ' JOIN #__flexicontent_advsearch_index as ts ON ts.item_id = i.id AND ts.field_id IN (12)';
    $adv_fields = ' JOIN #__flexicontent_fields as txtf ON txtf.id=ts.field_id AND txtf.published = 1 AND txtf.isadvsearch IN (1,2) AND ' . $levels_ok;
    $check($r->checks === array(), 'search: the advanced index does not look for hidden basic fields');
    $check(strpos($r->match, $adv_join . " AND  MATCH (ts.search_index) AGAINST ('+alpha* +beta*' IN BOOLEAN MODE) " . $adv_fields) !== false, 'search: the advanced index is matched as before');
    $check(strpos($r->data, $adv_join . $adv_fields . ' WHERE i.id IN (5)') !== false && strpos($r->data, 'LEFT JOIN') === false, 'search: the advanced index text is joined as before');


    /**
     * 3. Item lists: the main category and its ancestors must be published and viewable
     */

    // 1 (unpublished) > 2 > 3,  4 (unpublished),  5,  6 (Registered) > 7,  8 (trashed) > 9,  10 (access 0) > 11
    $cat = static function ($id, $published, $access, array $ancestors) {
        return (object) array('id' => $id, 'published' => $published, 'access' => $access, 'ancestorsarray' => array_merge($ancestors, array($id)));
    };
    $tree = array(
        1 => $cat(1, 0, 1, array()), 2 => $cat(2, 1, 1, array(1)), 3 => $cat(3, 1, 1, array(1, 2)),
        4 => $cat(4, 0, 1, array()), 5 => $cat(5, 1, 1, array()),
        6 => $cat(6, 1, 2, array()), 7 => $cat(7, 1, 1, array(6)),
        8 => $cat(8, -2, 1, array()), 9 => $cat(9, 1, 1, array(8)),
        10 => $cat(10, 1, 0, array()), 11 => $cat(11, 1, 1, array(10)),
    );

    $models = array(
        'category'   => file_get_contents(JPATH_ROOT . '/site/models/category.php'),
        'tags'       => file_get_contents(JPATH_ROOT . '/site/models/tags.php'),
        'favourites' => file_get_contents(JPATH_ROOT . '/site/models/favourites.php'),
    );

    eval('final class CategoryListFixture {
            public $_params; public $_layout = "tags"; public $_tagid = 4; public $_authorid = 0; public $_uid = 0; public $_id = 0;
            public $_ids = array(); public $_excl_ids = array();
            public $_active_filts = array(); public $_active_search = ""; public $_active_ai = ""; public $_listall = 0;
            public function getState($k) { return "com_flexicontent"; }
            public function _buildFiltersWhere() { return array(); }
            public function _buildAlphaIndexWhere() { return ""; }
            public function sql() { $GLOBALS["fc_catview"] = array(); return $this->_buildItemWhere(); }
            ' . methodSource($models['category'], '_buildItemWhere') . methodSource($models['category'], '_getClosedPathCats') . '
        }
        final class TagsListFixture {
            public $_params; public $_id = 4;
            public function getState($k) { return null; }
            public function sql() { return $this->_buildQuery(); }
            ' . methodSource($models['tags'], '_buildQuery') . methodSource($models['tags'], '_buildItemOrderBy')
              . methodSource($models['tags'], '_buildItemWhere') . methodSource($models['tags'], '_getClosedPathCats') . '
        }
        final class FavouritesListFixture {
            public $_params;
            public function getState($k) { return null; }
            public function sql() { return $this->_buildQuery(); }
            ' . methodSource($models['favourites'], '_buildQuery') . methodSource($models['favourites'], '_buildItemOrderBy')
              . methodSource($models['favourites'], '_buildItemWhere') . methodSource($models['favourites'], '_getClosedPathCats') . '
        }');

    \Joomla\CMS\Factory::$db = new DbFixture;
    $user = \Joomla\CMS\Factory::$app->user;

    $published  = ' AND ( ( c.published = 1 AND i.catid NOT IN (1,2,3,4,8,9) ) )';
    $accessible = ' AND  i.access IN (0,1,5) AND i.catid NOT IN (6,7)';

    foreach (array('category' => 'CategoryListFixture', 'tags' => 'TagsListFixture', 'favourites' => 'FavouritesListFixture') as $name => $class)
    {
        $GLOBALS['globalcats'] = $tree;
        \Joomla\CMS\Factory::$app->input->v = array();
        \Joomla\CMS\Access\Access::$levels = array(1, 5);
        $user->id = 0; $user->can = false;

        $model = new $class;
        $model->_params = new ParamsFixture;

        // The categories that the item view refuses: by published state of the path, by view levels of the path
        $check($model->_getClosedPathCats() === array(1, 2, 3, 4, 8, 9), $name . ': categories with an unpublished or trashed category in their path');
        $check($model->_getClosedPathCats(array(1, 5)) === array(6, 7), $name . ': categories with a non-viewable category in their path');
        $check($model->_getClosedPathCats(array(1, 2)) === array(), $name . ': no category is closed for a user having the levels of all paths');

        // Guest
        $sql = $model->sql();
        $check(strpos($sql, $published) !== false, $name . ': the list requires a published main category path');
        $check(strpos($sql, $accessible) !== false, $name . ': the list requires a viewable main category path');

        // A logged user gets the same exception as for the state of the items: the owned items
        $user->id = 42;
        $sql = $model->sql();
        $check(strpos($sql, ' AND ( ( c.published = 1 AND i.catid NOT IN (1,2,3,4,8,9) )  OR i.created_by = 42)') !== false, $name . ': owned items are listed regardless of the category state');
        $check(strpos($sql, $accessible) !== false, $name . ': owned items still need a viewable main category path');
        $user->id = 0;

        // Listing of unauthorized items enabled: no access condition at all, as before
        $model->_params = new ParamsFixture(array('show_noauth' => 1));
        $sql = $model->sql();
        $check(strpos($sql, $published) !== false && strpos($sql, 'NOT IN (6,7)') === false && strpos($sql, ' AND  i.access IN') === false,
            $name . ': listing unauthorized items skips only the access conditions');

        // Users that may list items in any state
        $model->_params = new ParamsFixture(array('use_list_items_in_any_state_acl' => 1));
        $user->can = true;
        $sql = $model->sql();
        $check(strpos($sql, 'c.published') === false && strpos($sql, $accessible) !== false, $name . ': the any-state privilege also covers the category state');
        $user->can = false;

        // All categories published and public: only the direct check of the main category is added
        $GLOBALS['globalcats'] = array(5 => $tree[5], 10 => $tree[10], 11 => $tree[11]);
        $model->_params = new ParamsFixture;
        $sql = $model->sql();
        $check(strpos($sql, ' AND ( ( c.published = 1 ) )') !== false && strpos($sql, 'NOT IN') === false, $name . ': no category list is added when no path is closed');
    }

    restore_error_handler();

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
