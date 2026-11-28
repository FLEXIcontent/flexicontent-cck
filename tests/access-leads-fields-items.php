<?php
/**
 * Access rules of: the dependent options request of the list / radio / checkbox fields,
 * the category check of the item save, and the FLEXIcontent entries of the JCE link browser.
 * Runs the real methods and files with dummy users and rows; no Joomla bootstrap, no database.
 * The SQL of the link browser is answered by a small in-memory reader of its conditions, it is not run by MySQL.
 */
namespace Joomla\CMS {
    final class Factory {
        public static $user; public static $session; public static $db;
        public static function getApplication() {
            return new class {
                public function getIdentity() { return Factory::$user; }
                public function getSession() { return Factory::$session; }
            };
        }
        public static function getContainer() { return new class { public function get($name) { return Factory::$db; } }; }
        public static function getDbo() { return self::$db; }
    }
}
namespace Joomla\CMS\Object {
    class CMSObject {}
}
namespace Joomla\CMS\Language {
    final class Text { public static function _($text) { return $text; } }
}
namespace Joomla\Registry {
    final class Registry { public $data; public function __construct($data = null) { $this->data = $data; } }
}
namespace {
    define('_JEXEC', 1);
    define('_WF_EXT', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('JPATH_ROOT', dirname(__DIR__));

    // The link browser files load two helpers from the installed component path: supply them in a temporary folder
    $testRoot  = sys_get_temp_dir() . '/flexi-access-leads-' . bin2hex(random_bytes(8));
    $helperDir = $testRoot . '/components/com_flexicontent/classes/helpers';
    $routeDir  = $testRoot . '/components/com_flexicontent/helpers';
    if (!mkdir($helperDir, 0700, true) || !mkdir($routeDir, 0700, true)) throw new RuntimeException('Cannot create test fixture');
    register_shutdown_function(function () use ($testRoot, $helperDir, $routeDir) {
        foreach (array($helperDir . '/security.php', $routeDir . '/route.php') as $file) if (is_file($file)) unlink($file);
        foreach (array($helperDir, dirname($helperDir), $routeDir, dirname($routeDir), dirname($routeDir, 2), $testRoot) as $dir) @rmdir($dir);
    });
    copy(JPATH_ROOT . '/site/classes/helpers/security.php', $helperDir . '/security.php');
    file_put_contents($routeDir . '/route.php', '<?php final class FlexicontentHelperRoute {'
        . ' public static function getCategoryRoute($slug, $itemid = 0, $vars = array()) { return "cat:" . $slug; }'
        . ' public static function getItemRoute($slug, $catslug) { return "item:" . $slug; } }');
    define('JPATH_SITE', $testRoot);

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

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };

    require JPATH_SITE . '/components/com_flexicontent/classes/helpers/security.php';

    final class TestUser {
        public $id = 0; public $guest = true; public $levels = array(1); public $rights = array();
        public function __construct($id = 0, $levels = array(1), $rights = array()) { $this->id = $id; $this->guest = !$id; $this->levels = $levels; $this->rights = $rights; }
        public function authorise($action, $asset = null) { return in_array($action . '@' . ($asset ?: 'root'), $this->rights, true); }
        public function getAuthorisedViewLevels() { return $this->levels; }
    }
    final class TestSession {
        public $data = array();
        public function get($name, $default = null, $namespace = 'default') { return $this->data[$namespace][$name] ?? $default; }
    }
    final class FlexicontentHelperPerm {
        public static $createAny = false;
        public static function getPermAny($action = null) { return $action === 'core.create' && self::$createAny; }
    }

    /** Dummy rows, read with the conditions that the queries state. */
    final class TestQuery {
        public $parts;
        public function __construct() { $this->clear(); }
        public function clear() { $this->parts = array('select' => array(), 'from' => '', 'join' => array(), 'where' => array(), 'order' => ''); return $this; }
        public function select($columns) { $this->parts['select'][] = $columns; return $this; }
        public function from($table) { $this->parts['from'] = $table; return $this; }
        public function join($type, $table) { $this->parts['join'][] = $type . ' JOIN ' . $table; return $this; }
        public function where($condition) { $this->parts['where'][] = '(' . $condition . ')'; return $this; }
        public function order($order) { $this->parts['order'] = $order; return $this; }
        public function __toString() {
            return 'SELECT ' . implode(',', $this->parts['select']) . ' FROM ' . $this->parts['from'] . ' ' . implode(' ', $this->parts['join'])
                . ($this->parts['where'] ? ' WHERE ' . implode(' AND ', $this->parts['where']) : '') . ' ORDER BY ' . $this->parts['order'];
        }
    }
    final class TestDb {
        public $sql = ''; public $log = array();
        public $users = array(); public $categories = array(); public $items = array();
        public $viewItem = null; public $viewField = null;
        public function getQuery($new = false) { return new TestQuery; }
        public function setQuery($query) { $this->sql = (string) $query; $this->log[] = $this->sql; return $this; }
        public function quote($text) { return "'" . addslashes($text) . "'"; }
        private static function levels($sql, $pattern) { return preg_match($pattern, $sql, $m) ? array_map('intval', explode(',', $m[1])) : null; }

        // The item and field lookups of the shared view check
        public function loadObject() {
            return strpos($this->sql, '#__flexicontent_fields AS f') !== false ? $this->viewField : $this->viewItem;
        }

        // Category with all its parents: published, and viewable when the query asks for it
        public function loadResult() {
            if (!preg_match('/c\.id = (\d+)/', $this->sql, $m) || strpos($this->sql, 'NOT EXISTS') === false) return null;
            $levels = self::levels($this->sql, '/p\.access NOT IN \(([\d,]+)\)/');
            $requiresPublished = strpos($this->sql, 'p.published <> 1') !== false;
            for ($id = (int) $m[1]; $id !== 1; $id = $category->parent_id) {
                if (!isset($this->categories[$id])) return null;
                $category = $this->categories[$id];
                if ($requiresPublished && $category->published !== 1) return null;
                if ($levels !== null && !in_array($category->access, $levels, true)) return null;
            }
            return (int) $m[1];
        }

        public function loadObjectList() {
            $sql = $this->sql; $rows = array();
            if (strpos($sql, 'FROM #__users') !== false) {
                preg_match('/SELECT (.*?) FROM/', $sql, $m);
                $columns = array_map('trim', explode(',', $m[1]));
                foreach ($this->users as $user) {
                    if (strpos($sql, 'block = 0') !== false && $user->block) continue;
                    $rows[] = (object) array_intersect_key((array) $user, array_flip($columns));
                }
            }
            elseif (strpos($sql, 'FROM #__content AS i') !== false) {
                preg_match('/c\.id = (\d+)/', $sql, $m);
                $levels = self::levels($sql, '/i\.access IN \(([\d,]+)\)/');
                foreach ($this->items as $item) {
                    if ($item->catid !== (int) $m[1] || ($levels !== null && !in_array($item->access, $levels, true))) continue;
                    $rows[] = clone $item;
                }
            }
            elseif (preg_match('/parent_id = (\d+)/', $sql, $m)) {
                $levels = self::levels($sql, '/\(access IN \(([\d,]+)\)\)/');
                foreach ($this->categories as $category) {
                    if ($category->parent_id !== (int) $m[1]) continue;
                    if (strpos($sql, 'published = 1') !== false && $category->published !== 1) continue;
                    if ($levels !== null && !in_array($category->access, $levels, true)) continue;
                    $rows[] = (object) array('id' => $category->id, 'title' => $category->title, 'slug' => $category->id, 'lft' => 0);
                }
            }
            return $rows;
        }
    }

    $session = \Joomla\CMS\Factory::$session = new TestSession;
    $db      = \Joomla\CMS\Factory::$db = new TestDb;
    $as      = static function ($user) { return \Joomla\CMS\Factory::$user = $user; };


    /**
     * 1. Dependent options request of the indexed fields
     */

    $indexed = file_get_contents(JPATH_ROOT . '/admin/helpers/fcfield/indexedfield.php');
    eval('final class IndexedFieldFixture { static $field_types = array("select"); public '
        . methodSource($indexed, 'canGetCascadedField') . ' }');
    $plugin = new IndexedFieldFixture;

    $field = static function ($overrides = array()) {
        return (object) ($overrides + array('id' => 31, 'field_type' => 'select', 'published' => 1, 'valueseditable' => 0, 'has_access' => 0));
    };
    $newItem  = (object) array('id' => 0, 'type_id' => 0, 'created_by' => 0);
    $item     = (object) array('id' => 70, 'type_id' => 4, 'created_by' => 55);
    $typeIds  = array('4', '9');
    $viewable = static function ($yes, $state = 1) use ($db) {
        $db->viewItem  = $yes ? (object) array('id' => 70, 'type_id' => 4, 'state' => $state, 'publish_up' => null, 'publish_down' => null, 'created_by' => 55) : null;
        $db->viewField = $yes ? (object) array('id' => 31, 'attribs' => '{}') : null;
    };

    // New item (item id 0)
    $as(new TestUser);
    $check($plugin->canGetCascadedField($field(), $newItem, $typeIds) === false, 'new item: a guest gets no options of a field that is not viewable');
    $check($plugin->canGetCascadedField($field(array('has_access' => 1)), $newItem, $typeIds) === true, 'new item: a viewable published field gives its options');
    $check($plugin->canGetCascadedField($field(array('has_access' => 1, 'published' => 0)), $newItem, $typeIds) === false, 'new item: an unpublished field gives no options');
    $check($plugin->canGetCascadedField($field(array('has_access' => 1, 'field_type' => 'radio')), $newItem, $typeIds) === false, 'new item: a field of another field type gives no options');

    $session->data['flexicontent']['item_submit_conf'] = array('abc' => array('menu_id' => 5, 'type_ids' => array(9)));
    $check($plugin->canGetCascadedField($field(), $newItem, $typeIds) === true, 'new item: a visitor that was given a submission form of the type gets the options');
    $session->data['flexicontent']['item_submit_conf'] = array('abc' => array('menu_id' => 5, 'type_ids' => array(2)));
    $check($plugin->canGetCascadedField($field(), $newItem, $typeIds) === false, 'new item: a submission form of another type does not give the options');
    $session->data = array();

    FlexicontentHelperPerm::$createAny = true;
    $check($plugin->canGetCascadedField($field(), $newItem, $typeIds) === true, 'new item: a user that can create items gets the options');
    $check($plugin->canGetCascadedField($field(array('published' => 0)), $newItem, $typeIds) === false, 'new item: creators get no options of an unpublished field');
    $check($plugin->canGetCascadedField($field(array('valueseditable' => 1)), $newItem, $typeIds) === false, 'new item: creators get no options of a field that they may not edit');
    $as(new TestUser(55, array(1, 2), array('flexicontent.editfieldvalues@com_flexicontent.field.31')));
    $check($plugin->canGetCascadedField($field(array('valueseditable' => 1)), $newItem, $typeIds) === true, 'new item: creators with the edit-values right of the field get the options');
    FlexicontentHelperPerm::$createAny = false;

    // Existing item
    $viewable(false);
    $as(new TestUser);
    $db->log = array();
    $check($plugin->canGetCascadedField($field(array('has_access' => 1)), $item, $typeIds) === false && count($db->log) > 0, 'existing item: a guest gets no options for an item that is not viewable');
    $viewable(true);
    $check($plugin->canGetCascadedField($field(array('has_access' => 1)), $item, $typeIds) === true, 'existing item: a viewable field of a viewable published item gives its options');
    $viewable(true, 0);
    $check($plugin->canGetCascadedField($field(array('has_access' => 1)), $item, $typeIds) === false, 'existing item: an unpublished item gives no options to a visitor');
    $viewable(false);

    $as(new TestUser(8, array(1, 2), array('core.edit@com_content.article.70')));
    $db->log = array();
    $check($plugin->canGetCascadedField($field(), $item, $typeIds) === true && $db->log === array(), 'existing item: a user that can edit the item gets the options');
    $check($plugin->canGetCascadedField($field(array('published' => 0)), $item, $typeIds) === false, 'existing item: editors get no options of an unpublished field');
    $check($plugin->canGetCascadedField($field(), $item, array('9')) === false, 'existing item: editors get no options of a field that is not assigned to the type of the item');
    $check($plugin->canGetCascadedField($field(array('valueseditable' => 1)), $item, $typeIds) === false, 'existing item: editors get no options of a field that they may not edit');
    $check($plugin->canGetCascadedField($field(), (object) array('id' => 71, 'type_id' => 4, 'created_by' => 55), $typeIds) === false, 'existing item: the edit right of one item does not open another item');

    $as(new TestUser(55, array(1, 2), array('core.edit.own@com_content.article.70')));
    $check($plugin->canGetCascadedField($field(), $item, $typeIds) === true, 'existing item: the owner with the edit-own right gets the options');
    $as(new TestUser(56, array(1, 2), array('core.edit.own@com_content.article.70')));
    $check($plugin->canGetCascadedField($field(), $item, $typeIds) === false, 'existing item: the edit-own right is not enough for the item of another user');

    $as(new TestUser);
    $session->data['flexicontent']['rendered_uneditable'] = array(70 => -1);
    $check($plugin->canGetCascadedField($field(), $item, $typeIds) === true, 'existing item: the submitter of an item that is editable until the session ends gets the options');
    $session->data['flexicontent']['rendered_uneditable'] = array(71 => -1);
    $check($plugin->canGetCascadedField($field(), $item, $typeIds) === false, 'existing item: the session edit flag of another item does not give the options');
    $session->data = array();

    $handler = methodSource($indexed, 'getCascadedField');
    $guard   = strpos($handler, '$this->canGetCascadedField(');
    $check($guard !== false && $guard < strpos($handler, 'loadFieldConfig(') && $guard < strpos($handler, '->onDisplayField('), 'the request handler checks access before it loads the configuration and renders the field');


    /**
     * 2. Category check of the item save
     */

    $model = file_get_contents(JPATH_ROOT . '/admin/models/parentclassitem.php');
    $from  = strpos($model, '$checkNewSiteCategory = ');
    $to    = strpos($model, "/**\n\t\t\t * SECURITY concern: Prevent users from changing the item owner", (int) $from);
    if ($to === false) $to = strpos($model, "/**\r\n\t\t\t * SECURITY concern: Prevent users from changing the item owner", (int) $from);
    $check($from !== false && $to !== false, 'the category check of store() was found');
    eval('final class CategoryCheckFixture { public $error = null;
        public function setError($message) { $this->error = $message; }
        public function run($isSite, $isNew, $option, $overridecatperms, $allowed_cid, $postcats, $item, $data) {
            $this->error = null;
            ' . substr($model, (int) $from, (int) $to - (int) $from) . '
            return $data;
        } }');
    $store    = new CategoryCheckFixture;
    $existing = static function () { return (object) array('id' => 70, 'catid' => 7, 'categories' => array(7, 8)); };
    $created  = static function ($requested) { return (object) array('id' => 0, 'catid' => $requested, 'categories' => array()); };
    $refused  = static function ($result) use ($store) { return $result === false && strpos((string) $store->error, 'main category is not in allowed list') === 0; };
    $post     = static function ($catid, $categories) { return array('catid' => $catid, 'categories' => $categories); };

    foreach (array('frontend' => true, 'backend' => false) as $client => $isSite) {
        // User that may create in no category
        $result = $store->run($isSite, false, 'com_flexicontent', 0, array(), 0, $existing(), $post(99, array(99)));
        $check($refused($result), $client . ': no create right in any category, moving an existing item to another main category is refused');
        $result = $store->run($isSite, false, 'com_flexicontent', 0, array(), 0, $existing(), $post(7, array(7, 8, 99)));
        $check(is_array($result) && $result['catid'] === 7 && $result['categories'] === array(7, 8), $client . ': no create right in any category, the current categories are kept and an added one is dropped');
        $result = $store->run($isSite, false, 'com_flexicontent', 0, array(), 0, $existing(), $post(8, array(8)));
        $check(is_array($result) && $result['catid'] === 8 && $result['categories'] === array(8), $client . ': no create right in any category, a current secondary category may become the main one');

        // User that may create in one category
        $result = $store->run($isSite, false, 'com_flexicontent', 0, array(5), 0, $existing(), $post(99, array(99)));
        $check($refused($result), $client . ': create right in one category, moving an existing item to a category without it is refused');
        $result = $store->run($isSite, false, 'com_flexicontent', 0, array(5), 0, $existing(), $post(5, array(5, 7, 99)));
        $check(is_array($result) && $result['catid'] === 5 && $result['categories'] === array(5, 7), $client . ': create right in one category, the item may move there and keep a current category');

        // User with the create right in every category
        $result = $store->run($isSite, false, 'com_flexicontent', 0, array(5, 7, 8, 99), 0, $existing(), $post(99, array(99, 5)));
        $check(is_array($result) && $result['catid'] === 99 && $result['categories'] === array(99, 5), $client . ': create right in all categories, any change of an existing item is kept');
        $result = $store->run($isSite, true, 'com_flexicontent', 0, array(5, 7, 8, 99), 0, $created(99), $post(99, array(99, 5)));
        $check(is_array($result) && $result['catid'] === 99 && $result['categories'] === array(99, 5), $client . ': create right in all categories, a new item is saved in the chosen categories');

        // New item: its "current" main category is the requested one
        $result = $store->run($isSite, true, 'com_flexicontent', 0, array(), 0, $created(99), $post(99, array(99)));
        $check($refused($result), $client . ': no create right in any category, a new item is refused');
        $result = $store->run($isSite, true, 'com_flexicontent', 0, array(5), 0, $created(99), $post(99, array(99)));
        $check($refused($result), $client . ': create right in one category, a new item in another category is refused');
        $result = $store->run($isSite, true, 'com_flexicontent', 0, array(5), 0, $created(5), $post(5, array(5, 99)));
        $check(is_array($result) && $result['categories'] === array(5), $client . ': create right in one category, a new item is saved there only');
    }

    // Article saved by the Joomla article form: the row already has its new main category
    $result = $store->run(false, false, 'com_content', 0, array(), 0, (object) array('id' => 70, 'catid' => 7, 'categories' => array(7)), $post(7, array(7)));
    $check(is_array($result) && $result['categories'] === array(7), 'article form: an unchanged article of a user without create rights is saved');

    // Submission menu that overrides the category rights of new frontend items
    $result = $store->run(true, true, 'com_flexicontent', 1, array(5, 6), 2, $created(6), $post(6, array(6, 99)));
    $check(is_array($result) && $result['categories'] === array(6), 'submission menu: its categories are accepted and others are dropped');
    $result = $store->run(true, true, 'com_flexicontent', 1, array(5, 6), 2, $created(99), $post(99, array(99)));
    $check($refused($result), 'submission menu: a main category outside its list is refused');
    $result = $store->run(true, true, 'com_flexicontent', 1, array(5, 6), 0, $created(5), $post(5, array(5)));
    $check(is_array($result) && $result['categories'] === array(5, 6), 'submission menu: fixed categories are still assigned');


    /**
     * 3. FLEXIcontent entries of the JCE link browser
     */

    final class WFEditorPlugin {
        public static $allowed = true; public static $asked = array();
        public static function getInstance() { return new self; }
        public function checkAccess($option, $default = 0) { self::$asked[] = $option; return self::$allowed; }
        public function getParam($name, $default = null) { return $default; }
    }
    require JPATH_ROOT . '/site/librairies/JCE/links/flexicontentlinks.php';
    require JPATH_ROOT . '/site/librairies/JCE/links/flexicontentlinks/authored_items.php';
    require JPATH_ROOT . '/site/librairies/JCE/links/flexicontentlinks/items.php';

    $names = static function ($links, $class = null) {
        $names = array();
        foreach ($links as $link) if ($class === null || strpos($link['class'], $class) !== false) $names[] = $link['name'];
        return $names;
    };
    $login = 'login_of_';

    // Items by author
    $db->users = array(
        (object) array('id' => 40, 'name' => 'Dummy Super User', 'username' => $login . 'super', 'block' => 0),
        (object) array('id' => 41, 'name' => 'Dummy Author', 'username' => $login . 'author', 'block' => 0),
        (object) array('id' => 42, 'name' => 'Dummy Blocked', 'username' => $login . 'blocked', 'block' => 1),
    );
    $authors = new FlexicontentlinksAuthored_items;
    $args = (object) array('option' => 'com_flexicontent_authored_items', 'layout' => 'author');

    $as(new TestUser(41, array(1, 2)));
    $db->log = array();
    $links = $authors->getLinks($args);
    $check($names($links) === array('Dummy Super User', 'Dummy Author'), 'authors: an editor sees the display names only');
    $check(strpos(json_encode($links), $login) === false && strpos(implode(' ', $db->log), 'username') === false, 'authors: the login names are not read for an editor');
    $check(strpos($links[0]['id'], 'authorid=40') !== false, 'authors: the link of each author is unchanged');

    $as(new TestUser(40, array(1, 2, 3), array('core.manage@com_users')));
    $check($names($authors->getLinks($args)) === array('Dummy Super User [' . $login . 'super]', 'Dummy Author [' . $login . 'author]'), 'authors: a user manager still sees the login names');

    WFEditorPlugin::$allowed = false;
    $db->log = array();
    $check($authors->getLinks($args) === array() && $db->log === array(), 'authors: nothing is listed when the editor profile turns the content links off');
    WFEditorPlugin::$allowed = true;

    // Items by category
    $category = static function ($id, $parent, $published, $access) {
        return (object) array('id' => $id, 'parent_id' => $parent, 'published' => $published, 'access' => $access, 'title' => 'Category ' . $id);
    };
    $db->categories = array(
        20 => $category(20, 1, 1, 1),
        21 => $category(21, 20, 1, 1),
        22 => $category(22, 20, 1, 3),
        23 => $category(23, 20, 0, 1),
        30 => $category(30, 1, 0, 1),
        31 => $category(31, 30, 1, 1),
        40 => $category(40, 1, 1, 3),
        41 => $category(41, 40, 1, 1),
    );
    $content = static function ($id, $catid, $state, $access, $up = null, $down = null) {
        return (object) array('id' => $id, 'ititle' => 'Item ' . $id, 'catid' => $catid, 'access' => $access, 'language' => '*', 'state' => $state,
            'publish_up' => $up, 'publish_down' => $down, 'created_by' => 41, 'slug' => (string) $id, 'catslug' => (string) $catid);
    };
    $db->items = array(
        $content(101, 20, 1, 1),
        $content(102, 20, 0, 1),
        $content(103, 20, 1, 3),
        $content(104, 20, 1, 1, null, '2000-01-01 00:00:00'),
        $content(105, 20, 1, 1, '2999-01-01 00:00:00'),
        $content(106, 20, -5, 1),
        $content(107, 20, -2, 1),
        $content(108, 31, 1, 1),
        $content(109, 41, 1, 1),
        $content(110, 23, 1, 1),
    );
    $browser = new FlexicontentlinksItems;
    $list = static function ($cid = null) use ($browser) {
        $args = (object) array('option' => 'com_flexicontent_items', 'view' => 'category');
        if ($cid !== null) $args->cid = $cid;
        return $browser->getLinks($args);
    };

    $as(new TestUser(50, array(1, 2)));
    $check($names($list()) === array('Category 20'), 'items: only published top-level categories of the user\'s view levels are listed');
    $links = $list(20);
    $check($names($links, 'flexicat') === array('Category 21'), 'items: unpublished and access-restricted child categories are not listed');
    $check($names($links, 'flexiitem') === array('Item 101(id=101)', 'Item 106(id=106)'), 'items: only published, current items of the user\'s view levels are listed');
    $check($list(40) === array() && $list(41) === array(), 'items: an access-restricted category, or a category below one, lists nothing');
    $check($list(30) === array() && $list(31) === array() && $list(23) === array(), 'items: an unpublished category, or a category below one, lists nothing');
    $check($list(999) === array(), 'items: an unknown category lists nothing');

    $as(new TestUser(41, array(1, 2), array('core.edit.own@com_content.article.102', 'core.edit.own@com_content.article.104')));
    $check($names($list(20), 'flexiitem') === array('Item 101(id=101)', 'Item 102(id=102)', 'Item 104(id=104)', 'Item 106(id=106)'), 'items: authors still see their own unpublished and expired items that they can edit');
    $as(new TestUser(51, array(1, 2), array('core.edit@com_content.article.105')));
    $check($names($list(20), 'flexiitem') === array('Item 101(id=101)', 'Item 105(id=105)', 'Item 106(id=106)'), 'items: editors still see a scheduled item that they can edit');

    $as(new TestUser(40, array(1, 2), array('core.admin@root')));
    $links = $list(20);
    $check($names($links, 'flexicat') === array('Category 21', 'Category 22'), 'items: a super user sees the categories of every access level, but no unpublished ones');
    $check($names($links, 'flexiitem') === array('Item 101(id=101)', 'Item 103(id=103)', 'Item 106(id=106)'), 'items: a super user sees the items of every access level');
    $check($names($list(41), 'flexiitem') === array('Item 109(id=109)') && $list(31) === array(), 'items: a super user can open a restricted category, but not one below an unpublished category');

    $as(new TestUser(50, array(1, 2)));
    WFEditorPlugin::$allowed = false;
    $db->log = array();
    $check($list(20) === array() && $db->log === array(), 'items: nothing is listed when the editor profile turns the content links off');
    WFEditorPlugin::$allowed = true;

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
