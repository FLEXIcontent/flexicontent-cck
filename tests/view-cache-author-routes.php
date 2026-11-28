<?php
/**
 * The view cache id is built from a fixed list of request variables, the author layout names an author only when the
 * visitor is listed an item of that author, and the author segment of a SEF URL does not confirm login names.
 * Runs the real methods (controller display(), category model getAuthorName(), the two record lookups of the router)
 * with doubles; no Joomla, no database.
 */
namespace Joomla\CMS {
    class Factory {
        public static $app, $services = array();
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($service) { return Factory::$services[$service]; } }; }
    }
}
namespace Joomla\Registry {
    class Registry {
        private $data;
        public function __construct($data = array()) { $this->data = (array) $data; }
        public function get($name, $default = null) { return isset($this->data[$name]) ? $this->data[$name] : $default; }
    }
}
namespace Joomla\CMS\Component {
    class ComponentHelper {
        public static $params = array();
        public static function getParams($component) { return new \Joomla\Registry\Registry(self::$params); }
    }
}
namespace Joomla\CMS\Language {
    class Text {
        public static function _($key) { return $key; }
        public static function sprintf($key, ...$args) { return $key . ':' . implode(',', $args); }
    }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) { throw new \ErrorException($message, 0, $severity, $file, $line); });
    define('_JEXEC', 1);
    define('JPATH_SITE', dirname(__DIR__));

    $checks = 0;
    $failures = array();
    $check = function ($condition, $message) use (&$checks, &$failures) {
        $checks++;
        if (!$condition) $failures[] = $message;
    };
    function methodSource($source, $name) {
        $start = strpos($source, 'function ' . $name . '(');
        if ($start === false) throw new \RuntimeException('Method not found: ' . $name);
        $method = ''; $depth = 0; $opened = false;
        foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
            $method .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { ++$depth; $opened = true; }
            if ($token === '}' && --$depth === 0 && $opened) break;
        }
        return $method;
    }
    function source($path) { return str_replace("\r\n", "\n", file_get_contents(JPATH_SITE . '/' . $path)); }

    final class InputDouble {
        public $data;
        public function __construct(array $data) { $this->data = $data; }
        public function get($name, $default = null, $filter = 'cmd') {
            if (!isset($this->data[$name])) return $default;
            return strtolower((string) $filter) === 'int' ? (int) $this->data[$name] : $this->data[$name];
        }
        public function set($name, $value) { $this->data[$name] = $value; }
        public function getArray() { return $this->data; }
    }
    final class AppDouble {
        public $user_id = 0, $menu = null, $session = array(), $input;
        public function getIdentity() { $id = $this->user_id; return new class($id) { public $id; public function __construct($id) { $this->id = $id; } public function get($name) { return $this->$name; } }; }
        public function getMenu() { $menu = $this->menu; return new class($menu) { private $m; public function __construct($m) { $this->m = $m; } public function getActive() { return $this->m; } }; }
        public function getSession() { $data = $this->session; return new class($data) { private $d; public function __construct($d) { $this->d = $d; }
            public function has($name, $ns = 'default') { return isset($this->d[$name]); } public function get($name, $default = null, $ns = 'default') { return isset($this->d[$name]) ? $this->d[$name] : $default; } }; }
        public function getLanguage() { return new class { public function load() { return true; } public function getTag() { return 'en-GB'; } }; }
    }
    final class flexicontent_html { public static $mobile = false; public static function getMobileDetector() { $m = self::$mobile; return new class($m) { private $m; public function __construct($m) { $this->m = $m; } public function isMobile() { return $this->m; } public function isTablet() { return false; } }; } }


    /**
     * 1. View cache id
     */

    // What the parent controller and Joomla's Cache::makeId() do with the registered names
    class ControllerBaseDouble {
        public $input, $cachable, $names, $id, $edited = false;
        public function display($cachable = false, $urlparams = array()) {
            $this->cachable = $cachable;
            $this->names = array_keys($urlparams);
            $values = array();
            foreach ($urlparams + array('format' => 'CMD', 'option' => 'CMD', 'view' => 'CMD', 'layout' => 'CMD', 'tpl' => 'CMD', 'id' => 'STRING') as $name => $filter) $values[$name] = $this->input->get($name, null, $filter);
            ksort($values);
            $this->id = md5(serialize($values));
        }
        public function edit() { $this->edited = true; }
    }
    $controller = source('site/controller.php');
    eval('final class DisplayFixture extends ControllerBaseDouble { ' . methodSource($controller, 'display') . ' }');

    /**
     * One request: $get and $post are the request, $routed are variables that URL routing added, $cookies are cookies.
     * $cookies_in_request puts the cookies into the request variables too, as PHP does with a request_order that has "C"
     */
    $request = function (array $get, array $post = array(), array $routed = array(), array $options = array()) {
        $options += array('user' => 0, 'menu' => null, 'session' => array(), 'cookies' => array(), 'cookies_in_request' => false, 'mobile' => false);
        $_GET = $get; $_POST = $post; $_COOKIE = $options['cookies'];
        $app = \Joomla\CMS\Factory::$app = new AppDouble;
        $app->user_id = $options['user']; $app->menu = $options['menu']; $app->session = $options['session'];
        flexicontent_html::$mobile = $options['mobile'];
        \Joomla\CMS\Component\ComponentHelper::$params = array('use_mobile_layouts' => 1);
        $fixture = new DisplayFixture;
        $fixture->input = $app->input = new InputDouble($get + $post + $routed + ($options['cookies_in_request'] ? $options['cookies'] : array()));
        $fixture->display();
        return $fixture;
    };
    $category = array('option' => 'com_flexicontent', 'view' => 'category', 'cid' => '5', 'Itemid' => '101');

    $plain = $request($category);
    $check($plain->cachable === true, 'cache: an ordinary category page is cached');
    $check($request($category + array('limitstart' => '20'))->cachable === true && $request($category + array('limitstart' => '20'))->id !== $plain->id, 'cache: another page of the list has its own cache entry');
    $check($request(array('option' => 'com_flexicontent', 'view' => 'item', 'cid' => '5', 'id' => '31', 'Itemid' => '101'))->cachable === true, 'cache: an ordinary item page is cached');
    $check($request(array('option' => 'com_flexicontent', 'view' => 'tags', 'id' => '7', 'Itemid' => '101'))->cachable === true, 'cache: an ordinary tags page is cached');

    // A name that the views do not read: not cached, and never a part of the cache id
    $unknown = $request($category + array('anything' => '1'));
    $check($unknown->cachable === false, 'cache: a request with an unknown variable is displayed without the cache');
    $check(!in_array('anything', $unknown->names, true) && $unknown->names === $plain->names, 'cache: the unknown name is not registered as a part of the cache id');
    $names = array();
    for ($i = 0; $i < 40; $i++) {
        $made_up = $request($category + array('x' . $i . '_' . md5((string) $i) => (string) $i, 'y' . $i => array('a' => 'b')));
        $check($made_up->cachable === false, 'cache: made-up variable ' . $i . ' is not cached');
        $names[serialize($made_up->names)] = 1;
        $names[$made_up->id] = 1;
    }
    $check(count($names) === 2 && isset($names[$plain->id]), 'cache: made-up names give neither new registered names nor new cache ids');
    $check($request($category, array('posted_name' => '1'))->cachable === false, 'cache: an unknown variable of a form (POST) is not cached either');
    $check($request($category, array(), array('added_by_plugin' => '1'))->cachable === false, 'cache: an unknown variable added during routing is not cached');

    // The variables that the views read keep the page in the cache, each value with its own entry
    $layout_values = array('layout' => 'author', 'format' => 'html', 'lang' => 'en', 'language' => 'en-GB', 'tmpl' => 'component');
    foreach (array('view', 'cid', 'catid', 'id', 'layout', 'clayout', 'ilayout', 'limit', 'limitstart', 'start', 'letter', 'tagid', 'authorid',
        'lang', 'language', 'Itemid', 'print', 'pop', 'format', 'cids', 'excl_cids', 'rootcat', 'orderby', 'orderby_2nd', 'listall', 'showall', 'tmpl', 'type', 'task', 'cc') as $name) {
        $value = isset($layout_values[$name]) ? $layout_values[$name] : '3';
        $known = $request(array($name => $value) + $category);
        $check($known->cachable === true && in_array($name, $known->names, true), 'cache: a page with the variable "' . $name . '" is cached, the variable is a part of the cache id');
        $other = $request(array($name => $value . '1') + $category);
        $check($other->id !== $known->id, 'cache: another value of "' . $name . '" is another cache entry');
    }

    // SEF and non-SEF requests of the same page share the entry
    $sef = $request(array(), array(), $category);
    $check($sef->cachable === true && $sef->id === $plain->id, 'cache: a SEF URL and its non-SEF URL have the same cache id');
    $check($request(array(), array(), $category + array('lang' => 'en', 'language' => 'en-GB'))->cachable === true, 'cache: the variables of the language filter keep the page cached');

    // The query of the active menu item
    $menu = (object) array('query' => array('option' => 'com_flexicontent', 'view' => 'category', 'layout' => 'author', 'authorid' => '42', 'menu_only_var' => '1'));
    $menu_request = $request(array(), array(), $menu->query + array('Itemid' => '101'), array('menu' => $menu));
    $check($menu_request->cachable === true && in_array('menu_only_var', $menu_request->names, true), 'cache: the variables of the active menu item are known variables');
    $check($request(array(), array(), $menu->query + array('Itemid' => '101'))->cachable === false, 'cache: the same variable without that menu item is an unknown variable');
    $check($request($category, array(), array(), array('menu' => (object) array('query' => null)))->cachable === true, 'cache: a menu item without query is accepted');

    // The rules that existed before
    $check($request($category + array('filter_12' => 'a'))->cachable === false, 'cache: a field filter in the URL is not cached');
    $check($request($category, array('filter_12' => 'a'))->cachable === false, 'cache: a field filter of a form is not cached');
    $check($request($category + array('filter_order' => 'i.title', 'filter_order_Dir' => 'ASC'))->cachable === false, 'cache: as before, a list order in the URL is not cached');
    $ordered = $request($category, array('filter_order' => 'i.title', 'filter_order_Dir' => 'ASC'));
    $check($ordered->cachable === true && $ordered->id !== $plain->id, 'cache: as before, a list order of a form has its own cache entry');
    $check($request($category + array('filter' => 'word'))->cachable === false, 'cache: a text search is not cached');
    $check($request($category + array('q' => 'word'))->cachable === false, 'cache: a text search with the variable q is not cached');
    foreach (array(array('view' => 'search'), array('view' => 'favourites'), array('view' => 'review'), array('view' => 'category', 'layout' => 'favs')) as $vars) {
        $check($request($vars + $category)->cachable === false, 'cache: view is never cached: ' . json_encode($vars));
    }
    $check($request($category, array(), array(), array('session' => array('rendered_uneditable' => array(31 => 1))))->cachable === false, 'cache: a session with an edit grant is never served from the cache');
    $check($request($category, array(), array(), array('session' => array('rendered_uneditable' => array())))->cachable === true, 'cache: an empty list of edit grants does not matter');
    $form = $request(array('layout' => 'form') + $category);
    $check($form->edited === true && $form->cachable === null, 'cache: the item form is not displayed by display()');

    // The identity of the cache entry belongs to the server
    $member = $request($category, array(), array(), array('user' => 31));
    $forged = $request($category + array('__fc_user_id__' => '31'));
    $check($member->cachable === true && $member->id !== $plain->id, 'cache: a member has an own cache entry');
    $check($forged->id === $plain->id && $forged->id !== $member->id && $forged->input->data['__fc_user_id__'] === 0, 'cache: a guest can not select the cache entry of a member');
    $check($forged->cachable === true, 'cache: the variable that display() itself replaces is not an unknown variable');
    $mobile = $request($category, array(), array(), array('mobile' => true));
    $check($mobile->cachable === true && $mobile->id !== $plain->id, 'cache: a mobile device has an own cache entry');
    $check($request($category + array('__fc_client__' => 'Mobile'))->cachable === false, 'cache: a desktop request can not store its page as the mobile page');

    // Cookies are not URL variables (PHP may list them in the request variables)
    $cookies = array('0123456789abcdef' => 'session', 'fc_uid' => 'x');
    $with_cookies = $request($category, array(), array(), array('cookies' => $cookies, 'cookies_in_request' => true));
    $check($with_cookies->cachable === true && $with_cookies->id === $plain->id, 'cache: cookies in the request variables neither stop caching nor change the cache id');
    $check($request($category + array('fc_uid' => 'x'), array(), array(), array('cookies' => $cookies, 'cookies_in_request' => true))->cachable === false, 'cache: a cookie name sent as URL variable is an unknown variable');

    $display = methodSource($controller, 'display');
    $check(strpos($display, 'foreach($_GET as $_varname => $_ignore)') === false && strpos($display, "\$safeurlparams[\$_varname] = 'STRING';\n\t\t\t}\n\t\t}\n\n\n\t\t// If component") === false,
        'cache: the names of the request are no longer registered as they come');


    /**
     * 2. Author layout: who is named in the heading
     */

    $model = source('site/models/category.php');
    eval('final class AuthorFixture { public $_layout = "author", $_authorid = 0, $_db, $total = 0; public function getTotal() { return $this->total; } public ' . methodSource($model, 'getAuthorName') . ' }');

    final class ListDbDouble {
        public $log = array(), $result = null;
        public function setQuery($sql, $offset = 0, $limit = 0) { $this->log[] = array((string) $sql, $offset, $limit); return $this; }
        public function loadResult() { return $this->result; }
    }
    final class UserFactoryDouble {
        public $loaded = array();
        public function loadUserById($id) { $this->loaded[] = $id; $names = array(42 => 'Ann Author', 43 => 'Sam Staff'); return (object) array('id' => isset($names[$id]) ? $id : 0, 'name' => isset($names[$id]) ? $names[$id] : null); }
    }
    $author_name = function ($authorid, $visitor, $total, $list_without_filters = null, $layout = 'author') use (&$users, &$list_db) {
        global $fc_catview;
        $fc_catview = array('join_clauses' => ' JOIN #__categories AS c ON c.id = i.catid', 'where_conf_only' => ' WHERE 1 AND i.created_by = \'' . (int) $authorid . '\' AND i.state IN (1, -5)');
        \Joomla\CMS\Factory::$app = new AppDouble;
        \Joomla\CMS\Factory::$app->user_id = $visitor;
        \Joomla\CMS\Factory::$services['Joomla\CMS\User\UserFactoryInterface'] = $users = new UserFactoryDouble;
        $fixture = new AuthorFixture;
        $fixture->_layout = $layout; $fixture->_authorid = $authorid; $fixture->total = $total;
        $fixture->_db = $list_db = new ListDbDouble;
        $list_db->result = $list_without_filters;
        return $fixture->getAuthorName();
    };
    $check($author_name(42, 0, 3) === 'Ann Author' && $list_db->log === array(), 'author: an author with listed items is named');
    $check($author_name(43, 0, 0) === '' && $users->loaded === array(), 'author: a user without an item in the list of the visitor is not named, the name is not even loaded');
    $check($list_db->log === array(array('SELECT 1 FROM #__flexicontent_items_tmp AS i  JOIN #__categories AS c ON c.id = i.catid WHERE 1 AND i.created_by = \'43\' AND i.state IN (1, -5)', 0, 1)),
        'author: an empty list is checked again without the filters of the request');
    $check($author_name(999, 0, 0) === '' && $author_name(999, 0, 0) === $author_name(43, 0, 0), 'author: a user id that does not exist looks like a user without items');
    $check($author_name(42, 0, 0, 1) === 'Ann Author', 'author: filters of the request that empty the list do not remove the name of a real author');
    $check($author_name(43, 43, 0) === 'Sam Staff' && $list_db->log === array(), 'author: the own page names its user');
    $check($author_name(43, 31, 0) === '', 'author: another member learns no more than a guest');
    $check($author_name(42, 0, null) === '' && $author_name(42, 0, '0') === '', 'author: no total, no name');
    $check($author_name(0, 0, 5) === '' && $author_name(-4, 0, 5) === '' && $author_name(42, 0, 5, null, 'tags') === '', 'author: only the author layout with an author id names a user');
    $fc_catview = array();
    \Joomla\CMS\Factory::$app = new AppDouble;
    \Joomla\CMS\Factory::$services['Joomla\CMS\User\UserFactoryInterface'] = $users = new UserFactoryDouble;
    $fixture = new AuthorFixture; $fixture->_authorid = 43; $fixture->_db = new ListDbDouble; $fixture->_db->result = 1;
    $check($fixture->getAuthorName() === '' && $fixture->_db->log === array(), 'author: without the clauses of the list there is no second look and no name');

    $view = source('site/views/category/view.html.php');
    $check(strpos($view, "loadUserById((int) \$authorid)") === false, 'author: the view no longer loads the name of the requested user id');
    $check(strpos($view, "(\$author_name = \$model->getAuthorName()) !== '' ? Text::sprintf('FLEXI_ITEMS_OF_USER', \$author_name) : Text::_('FLEXI_AUTHOR')") !== false, 'author: the heading uses the name that the model gives');
    $check(strpos(source('site/language/en-GB/en-GB.com_flexicontent.ini'), "\nFLEXI_AUTHOR=\"") !== false, 'author: the heading without name has a language string');


    /**
     * 3. Author segment of a SEF URL
     */

    final class QueryDouble {
        private $select = array(), $from = '', $join = '', $where = array();
        public function select($columns) { $this->select[] = $columns; return $this; }
        public function from($table) { $this->from = $table; return $this; }
        public function join($type, $condition) { $this->join .= ' ' . $type . ' JOIN ' . $condition; return $this; }
        public function where($condition) { $this->where[] = $condition; return $this; }
        public function __toString() { return 'SELECT ' . implode(',', $this->select) . ' FROM ' . $this->from . $this->join . ' WHERE ' . implode(' AND ', $this->where); }
    }
    // Answers the queries of the two router methods from arrays, the way the database would
    final class RouterDbDouble {
        public $log = array();
        public $users = array(42 => 'Editor', 43 => 'ghost', 44 => 'drafter');
        public $content = array(array('id' => 5, 'alias' => 'my-item', 'created_by' => 42, 'state' => 1), array('id' => 6, 'alias' => 'a-draft', 'created_by' => 44, 'state' => 0));
        private $sql;
        public function getQuery($new = false) { return new QueryDouble; }
        public function quoteName($name) { return '`' . $name . '`'; }
        public function quote($value) { return "'" . addslashes((string) $value) . "'"; }
        public function setQuery($sql) { $this->sql = (string) $sql; $this->log[] = $this->sql; return $this; }
        public function loadResult() {
            if (preg_match('/^SELECT `(alias|username)` FROM `(#__content|#__users)` WHERE `id` = (\d+)$/', $this->sql, $m)) {
                if ($m[2] === '#__users') return isset($this->users[$m[3]]) ? $this->users[$m[3]] : null;
                foreach ($this->content as $row) if ($row['id'] == $m[3]) return $row['alias'];
                return null;
            }
            throw new \RuntimeException('Unexpected query: ' . $this->sql);
        }
        public function loadObjectList() {
            if (preg_match("/^SELECT i\.id FROM `#__users` AS i WHERE i\.`username` = '([^']*)'(.*)$/", $this->sql, $m)) {
                $published_only = $m[2] === ' AND EXISTS (SELECT 1 FROM `#__content` AS a WHERE a.created_by = i.id AND a.state IN (1, -5))';
                if ($m[2] !== '' && !$published_only) throw new \RuntimeException('Unexpected query: ' . $this->sql);
                $rows = array();
                foreach ($this->users as $id => $username) {
                    if (strcasecmp($username, $m[1]) !== 0) continue;
                    $published = false;
                    foreach ($this->content as $row) if ($row['created_by'] == $id && in_array($row['state'], array(1, -5))) $published = true;
                    if (!$published_only || $published) $rows[] = (object) array('id' => $id);
                }
                return $rows;
            }
            if (preg_match("/^SELECT i\.id,i\.language FROM `#__content` AS i WHERE i\.`alias` = '([^']*)' AND \(i\.language = 'en-GB' OR i\.language = '\*'\)$/", $this->sql, $m)) {
                $rows = array();
                foreach ($this->content as $row) if ($row['alias'] === $m[1]) $rows[] = (object) array('id' => $row['id'], 'language' => '*');
                return $rows;
            }
            throw new \RuntimeException('Unexpected query: ' . $this->sql);
        }
    }
    $router = source('site/router.php');
    eval('use Joomla\CMS\Component\ComponentHelper; use Joomla\CMS\Factory; use Joomla\CMS\Language\Text;
        final class RouterFixture { public ' . methodSource($router, '_fc_route_addRecordIdPrefix') . ' public ' . methodSource($router, '_fc_route_getRecordIdByAlias') . ' }');

    \Joomla\CMS\Factory::$app = new AppDouble;
    $segment = function ($value, $table, array $params = array(), $alias_col = 'alias') use (&$route_db) {
        \Joomla\CMS\Factory::$services['Joomla\Database\DatabaseInterface'] = $route_db = new RouterDbDouble;
        \Joomla\CMS\Component\ComponentHelper::$params = $params;
        $tbl = null; $parent_id = null;
        try {
            return (new RouterFixture)->_fc_route_addRecordIdPrefix(array('authored', $value), 1, $table, $tbl, $parent_id, null, $alias_col);
        } catch (\Exception $e) {
            return 'error ' . $e->getCode();
        }
    };

    // Default configuration: the id selects the author, the name behind it is not compared
    foreach (array('42-editor', '42-Editor', '42-wrong', '42-', '43-ghost', '43-admin', '999-nobody', '42:editor') as $value) {
        $check($segment($value, '#__users', array(), 'username') === $value && $route_db->log === array(), 'route: author segment is accepted without a look at the login name: ' . $value);
    }
    $check($segment('42-wrong', '#__users', array('add_item_sef_segment' => 0), 'username') === '42-wrong' && $route_db->log === array(), 'route: the same without the item segment option');
    $check($segment('5-my-item', '#__content') === '5-my-item' && count($route_db->log) === 1, 'route: the alias of an item is still compared');
    $check($segment('5-wrong', '#__content') === 'error 404', 'route: a wrong item alias is still a 404');

    // Item segment option 2: the lookup of the name can not be told apart either
    $loose = array('add_item_sef_segment' => 2);
    $check($segment('42-editor', '#__users', $loose, 'username') === '42-editor' && $segment('42-wrong', '#__users', $loose, 'username') === '42-wrong'
        && $segment('43-ghost', '#__users', $loose, 'username') === '43-ghost', 'route: with option 2 every author segment is returned as it came');

    // URLs without ids: a login name becomes an id only for an author of published content
    $no_ids = array('sef_ids' => 1);
    $check($segment('editor', '#__users', $no_ids, 'username') === '42-editor', 'route: without ids, the login name of an author with published content is resolved');
    $check(strpos($route_db->log[0], ' AND EXISTS (SELECT 1 FROM `#__content` AS a WHERE a.created_by = i.id AND a.state IN (1, -5))') !== false, 'route: the lookup of a login name asks for published content');
    $check($segment('ghost', '#__users', $no_ids, 'username') === 'ghost', 'route: the login name of an account without content is not resolved');
    $check($segment('drafter', '#__users', $no_ids, 'username') === 'drafter', 'route: the login name of an account with unpublished content only is not resolved');
    $check($segment('nobody', '#__users', $no_ids, 'username') === 'nobody', 'route: a name that is not a login name gives the same answer');
    $check($segment('42-editor', '#__users', $no_ids, 'username') === '42-editor' && $segment('42-wrong', '#__users', $no_ids, 'username') === '42-wrong', 'route: without ids, old URLs with an id are returned as they came');
    $check($segment('my-item', '#__content', $no_ids) === '5-my-item' && strpos(implode(' ', $route_db->log), 'EXISTS') === false, 'route: the lookup of an item alias is unchanged');

    restore_error_handler();
    if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
