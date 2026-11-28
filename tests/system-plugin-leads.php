<?php
/**
 * System plugin: no login by URL, the module PHP rule check at save time, the state sync of all changed
 * articles and the recording of download links. Runs the real plugin methods against an in-memory
 * database double; no Joomla bootstrap, no database.
 */
namespace Joomla\CMS {
    final class Factory {
        public static $app, $db;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($service) { return Factory::$db; } }; }
    }
}
namespace Joomla\CMS\Table {
    // Record double: load() reads the stored copy
    #[\AllowDynamicProperties]
    class Table {
        public static $stored = array();
        public $id;
        public function __construct(array $values = array()) { foreach ($values as $k => $v) $this->$k = $v; }
        public function load($id) {
            if (!isset(static::$stored[$id])) return false;
            foreach (static::$stored[$id] as $k => $v) $this->$k = $v;
            return true;
        }
        public function getFields() { return isset(static::$stored[$this->id]) ? static::$stored[$this->id] + array('id' => 1) : array(); }
    }
}
namespace Joomla\Utilities {
    final class ArrayHelper { public static function toInteger($a) { return array_map('intval', (array) $a); } }
}
namespace Joomla\Registry {
    // Same reading rules as Joomla's class: a missing, null or empty value gives the default
    final class Registry {
        private $data = array();
        public function __construct($data = null) {
            if ($data instanceof self) $data = $data->data;
            if (is_string($data)) $data = json_decode($data, true);
            $this->data = is_array($data) ? $data : array();
        }
        public function get($name, $default = null) {
            return isset($this->data[$name]) && $this->data[$name] !== '' ? $this->data[$name] : $default;
        }
        public function set($name, $value) { $this->data[$name] = $value; }
        public function toString() { return json_encode((object) $this->data); }
    }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_ROOT', dirname(__DIR__));
    define('JPATH_ADMINISTRATOR', sys_get_temp_dir() . '/fc-system-plugin-' . bin2hex(random_bytes(6)));
    define('FLEXI_J40GE', true);
    define('FLEXI_J16GE', true);
    define('FLEXI_SECTION', 0);
    define('FLEXI_CAT_EXTENSION', 0);

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

    // Query builder and driver doubles: the driver applies select / insert / delete to arrays and logs every statement
    final class FixtureQuery {
        public $type = '', $table = '', $columns = array(), $where = array(), $values = '';
        public function select($columns) { $this->type = 'select'; $this->columns = (array) $columns; return $this; }
        public function delete($table) { $this->type = 'delete'; return $this->from($table); }
        public function insert($table) { $this->type = 'insert'; return $this->from($table); }
        public function from($table) { $this->table = trim($table, '`'); return $this; }
        public function columns($columns) { $this->columns = (array) $columns; return $this; }
        public function values($values) { $this->values = $values; return $this; }
        public function where($condition) { $this->where[] = $condition; return $this; }
        public function __toString() { return $this->type . ' ' . $this->table . ' ' . implode(' AND ', $this->where) . $this->values; }
    }
    final class FixtureDb {
        public $tables = array('#__flexicontent_file_usage' => array(), '#__flexicontent_files' => array(), '#__modules' => array(), '#__users' => array(), '#__flexicontent_fields_item_relations' => array());
        public $log = array();
        private $query;
        public function getQuery($new = false) { return new FixtureQuery; }
        public function qn($name) { return '`' . $name . '`'; }
        public function q($value) { return "'" . str_replace("'", "''", (string) $value) . "'"; }
        public function quote($value) { return $this->q($value); }
        public function getTableColumns($table) { return array('id' => 'int', 'state' => 'tinyint', 'access' => 'int', 'language' => 'char'); }
        public function setQuery($query) { $this->query = $query; $this->log[] = (string) $query; return $this; }
        private function matching() {
            $keys = array();
            foreach ($this->tables[$this->query->table] as $key => $row) {
                foreach ($this->query->where as $condition) {
                    if (preg_match('/^`(\w+)` LIKE \'%(.*)%\'$/s', $condition, $m)) { if (strpos((string) $row[$m[1]], $m[2]) === false) continue 2; continue; }
                    if (!preg_match('/^`(\w+)` = \'?(.*?)\'?$/s', $condition, $m) || (string) $row[$m[1]] !== $m[2]) continue 2;
                }
                $keys[] = $key;
            }
            return $keys;
        }
        public function execute() {
            if (!$this->query instanceof FixtureQuery) return true;
            if ($this->query->type === 'delete') {
                foreach ($this->matching() as $key) unset($this->tables[$this->query->table][$key]);
            } elseif ($this->query->type === 'insert') {
                preg_match_all("/'((?:[^']|'')*)'/", $this->query->values, $m);
                $columns = array_map(static function ($column) { return trim($column, '`'); }, $this->query->columns);
                $this->tables[$this->query->table][] = array_combine($columns, $m[1]);
            }
            return true;
        }
        public function loadColumn() {
            $column = trim($this->query->columns[0], '`');
            $values = array();
            foreach ($this->matching() as $key) $values[] = $this->tables[$this->query->table][$key][$column];
            return $values;
        }
        public function loadResult() {
            if ($this->query instanceof FixtureQuery) { $values = $this->loadColumn(); return $values ? $values[0] : null; }
            if (preg_match('/^SELECT params FROM #__modules WHERE id = (\d+)$/', $this->query, $m)) {
                foreach ($this->tables['#__modules'] as $row) if ((int) $row['id'] === (int) $m[1]) return $row['params'];
            }
            return null;
        }
        public function loadObject() {
            foreach ($this->tables['#__users'] as $row) {
                if (strpos($this->query, $this->q($row['username'])) !== false && strpos($this->query, $this->q($row['password'])) !== false) return (object) $row;
            }
            return null;
        }
    }

    final class FixtureUser {
        public $id, $guest, $rights;
        public function __construct($id, array $rights = array()) { $this->id = $id; $this->guest = !$id; $this->rights = $rights; }
        public function authorise($action, $asset = null) { return in_array($action . '@' . $asset, $this->rights, true); }
    }
    final class FixtureApp {
        public $client = 'administrator', $identity, $request = array(), $events = array(), $input;
        public function __construct() {
            $app = $this;
            $this->input = new class($app) {
                private $app;
                public function __construct($app) { $this->app = $app; }
                public function get($name, $default = null, $filter = null) { return isset($this->app->request[$name]) ? $this->app->request[$name] : $default; }
                public function set($name, $value) { $this->app->request[$name] = $value; }
            };
        }
        public function isClient($name) { return $this->client === $name; }
        public function getIdentity() { return $this->identity; }
        public function getSession() { return new class { public function get($name, $default = null, $group = null) { return $default; } public function set(...$a) {} }; }
        public function enqueueMessage(...$a) {}
        public function triggerEvent($name, $arguments = array()) { $this->events[] = $name; return array(); }
    }
    final class FixtureModuleRow {
        public $id, $module = 'mod_flexicontent', $client_id = 0, $params, $error = '';
        public function __construct($id, array $params) { $this->id = $id; $this->params = json_encode((object) $params); }
        public function setError($error) { $this->error = $error; }
        public function getError() { return $this->error; }
    }

    $app = \Joomla\CMS\Factory::$app = new FixtureApp;
    $db  = \Joomla\CMS\Factory::$db = new FixtureDb;

    // The real plugin methods, with the plugin's surrounding jobs replaced by no-ops
    $source = file_get_contents(JPATH_ROOT . '/plugins/system/flexisystem/flexisystem.php');
    $methods = '';
    foreach (array('onAfterInitialise', 'onExtensionBeforeSave', '_canSaveModulePhpRule', 'onContentChangeState', '_isInternalEvent', '_updateFileUsage_FcFileBtn_DownloadLinks', '_canLinkFile', '_downloadLinkFileIds', '_rememberStoredFileLinks', '_mayRecordFileLink', 'loginUser') as $name) {
        if (strpos($source, 'function ' . $name . '(') !== false) $methods .= ' public ' . methodSource($source, $name);
    }
    eval('use Joomla\CMS\Factory; use Joomla\Registry\Registry; use Joomla\Utilities\ArrayHelper;
        final class PluginHelper { public static function importPlugin(...$a) {} }
        final class SystemPluginFixture {
            public $extension = "com_flexicontent", $cparams, $params;
            public function __construct() { $this->cparams = new Registry(array("autoflogin" => 1)); $this->params = new Registry; }
            private function handleSerialized() {} private function checkinRecords() {} private function handleExpiredItems() {}
            private function handleFileTranfers() {} private function _loadFcHelpersAndLanguage() {}
            public $_stored_file_links = array(), $_file_link_rule = array("stored" => null, "owner" => 0), $users = array();
            public function _getUserById($id) { return isset($this->users[$id]) ? $this->users[$id] : null; }
            public function recordLinks($context, $item, $data) { $this->_updateFileUsage_FcFileBtn_DownloadLinks($context, $item, false, $data); }
            ' . $methods . ' }');
    $plugin = new SystemPluginFixture;

    // 1. A request that carries a user name and that user's stored password value logs nobody in
    $stored = md5('fixture');
    $db->tables['#__users'][] = array('id' => 42, 'username' => 'editor', 'password' => $stored);
    foreach (array('site', 'administrator') as $client) {
        $app->client = $client; $app->identity = new FixtureUser(0); $app->events = array(); $db->log = array();
        $app->request = array('fcu' => 'editor', 'fcp' => $stored);
        $plugin->onAfterInitialise();
        $check($app->events === array(), $client . ': fcu / fcp with the stored password value triggers no login event');
        $check(!preg_grep('/#__users/', $db->log), $client . ': fcu / fcp does not look up the users table');
    }
    $app->request = array();

    // 2. Module PHP rule: checked on the record that is stored, for every way of submitting it
    $_POST = array('jform' => array('params' => array('layout' => '')));
    $rule = 'return false;';
    $db->tables['#__modules'] = array(
        array('id' => 5, 'params' => json_encode(array('enable_php_rule' => '0', 'php_rule' => '', 'count' => '5'))),
        array('id' => 6, 'params' => json_encode(array('enable_php_rule' => '1', 'php_rule' => $rule, 'count' => '5'))),
    );
    $manager   = new FixtureUser(31);
    $templates = new FixtureUser(32, array('core.admin@com_templates'));
    $super     = new FixtureUser(33, array('core.admin@com_templates', 'core.admin@'));
    $save = static function ($user, $client, $id, array $params, $isNew = false, $context = 'com_modules.module') use ($app, $plugin) {
        $app->client = $client; $app->identity = $user;
        $row = new FixtureModuleRow($id, $params);
        $result = $plugin->onExtensionBeforeSave($context, $row, $isNew);
        return $result === false && $row->error !== '' ? 'refused' : ($result === null && $row->error === '' ? 'stored' : 'unexpected');
    };
    $enable = array('enable_php_rule' => '1', 'php_rule' => $rule, 'count' => '5');
    $check($save($manager, 'administrator', 5, $enable) === 'refused', 'php rule: a module manager cannot enable a rule');
    $check($save($manager, 'api', 5, $enable) === 'refused', 'php rule: nor through a client that posts no form');
    $check($save($manager, 'administrator', 5, $enable, false, 'com_advancedmodules.module') === 'refused', 'php rule: nor through the advanced module manager');
    $check($save($manager, 'administrator', 0, $enable, true) === 'refused', 'php rule: nor in a new module');
    $check($save($manager, 'administrator', 6, array('enable_php_rule' => '1', 'php_rule' => 'return true;')) === 'refused', 'php rule: an active rule cannot be replaced');
    $check($save($manager, 'administrator', 6, array('enable_php_rule' => '0', 'php_rule' => $rule)) === 'refused', 'php rule: the enabled flag cannot be changed alone');
    $check($save($manager, 'administrator', 6, array('count' => '9')) === 'refused', 'php rule: a save that would drop the stored rule is refused');
    $check($save($manager, 'administrator', 6, array('enable_php_rule' => '1', 'php_rule' => array($rule))) === 'refused', 'php rule: a non-text rule value is refused');
    $check($save($manager, 'administrator', 6, array('enable_php_rule' => '1', 'php_rule' => $rule, 'count' => '9')) === 'stored', 'php rule: other parameters of a module with a rule can be saved');
    $check($save($manager, 'administrator', 5, array('count' => '9')) === 'stored', 'php rule: a module without a rule can be saved');
    $check($save($manager, 'administrator', 0, array('count' => '9'), true) === 'stored', 'php rule: a new module without a rule can be saved');
    $check($save(null, 'cli', 5, $enable) === 'refused' && $save(null, 'cli', 5, array('count' => '9')) === 'stored', 'php rule: without a user only unchanged rules are stored');
    $check($save($templates, 'administrator', 5, $enable) === 'stored', 'php rule: templates administrators can change it in backend');
    $check($save($templates, 'site', 5, $enable) === 'refused' && $save($templates, 'api', 5, $enable) === 'refused', 'php rule: outside the backend that privilege is not enough');
    $check($save($super, 'site', 5, $enable) === 'stored' && $save($super, 'api', 5, $enable) === 'stored', 'php rule: super users can change it everywhere');
    $check($save($manager, 'administrator', 5, $enable, false, 'com_plugins.plugin') === 'stored', 'php rule: other extension records are not checked');

    // 3. State change: every changed article is copied again, whatever the request holds
    mkdir(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models', 0777, true);
    file_put_contents(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/items.php', '<?php ');
    register_shutdown_function(static function () {
        @unlink(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/items.php');
        foreach (array('/components/com_flexicontent/models', '/components/com_flexicontent', '/components', '') as $dir) @rmdir(JPATH_ADMINISTRATOR . $dir);
    });
    eval('final class FlexicontentModelItems { public $_db;
        public function __construct() { $this->_db = \Joomla\CMS\Factory::$db; }
        public ' . methodSource(file_get_contents(JPATH_ROOT . '/admin/models/items.php'), 'updateItemCountingData') . ' }');
    $app->client = 'administrator'; $app->identity = $manager;
    $synced = static function ($pks, array $request, $context = 'com_content.article') use ($app, $db, $plugin) {
        $app->request = $request; $db->log = array();
        $ok = $plugin->onContentChangeState($context, $pks, 0);
        $ids = array();
        foreach ($db->log as $sql) {
            if (preg_match('/^REPLACE INTO #__flexicontent_items_tmp .* WHERE c\.id IN \(([\d,]+)\)$/', $sql, $m)) $ids = array_merge($ids, explode(',', $m[1]));
            else $ids[] = 'other statement: ' . $sql;
        }
        return $ok === true ? implode(',', $ids) : 'event result is not true';
    };
    $check($synced(array(11, 12, 13), array('cid' => array(11, 12, 13))) === '11,12,13', 'state sync: all changed articles are refreshed');
    $check($synced(array(12, 13), array('cid' => array(11, 12, 13))) === '12,13', 'state sync: the event ids are used, not the first request id');
    $check($synced(array(12, 13), array()) === '12,13', 'state sync: works for a request without cid');
    $check($synced(array('7', 'x', 0), array()) === '7', 'state sync: ids are integers above zero');
    $check($synced(array(), array('cid' => array(11))) === '', 'state sync: no ids, no statement');
    $check($synced(array(11, 12), array(), 'com_categories.category') === '', 'state sync: other contexts are left alone');
    $check($synced(array(11, 12), array('isflexicontent' => 'yes')) === '11,12', 'state sync: a request variable does not switch it off');
    eval('final class FCModelAdmin { private static $internalContentEvents = false;
        public static ' . methodSource(file_get_contents(JPATH_ROOT . '/admin/models/base/base.php'), 'internalContentEvents') . ' }');
    FCModelAdmin::internalContentEvents(true);
    $check($synced(array(11, 12), array()) === '', 'state sync: FLEXIcontent own state changes are left alone');
    FCModelAdmin::internalContentEvents(false);
    $app->request = array();

    // 4. Download links: a usage row is added only for a file that the saving user may use
    $db->tables['#__flexicontent_files'] = array(
        array('id' => 4242, 'uploaded_by' => 50),
        array('id' => 4300, 'uploaded_by' => 31),
        array('id' => 4400, 'uploaded_by' => 0),
    );
    $link  = static function ($id) { return '<p>Get <a href="index.php?option=com_flexicontent&amp;task=download_file&amp;id=' . $id . '">it</a></p>'; };
    $plain = static function ($id) { return 'Get it href="index.php?option=com_flexicontent&task=download_file&id=' . $id . '" here'; };
    $usage = static function ($id, $context = 'com_content.article') use ($db) {
        $rows = array();
        foreach ($db->tables['#__flexicontent_file_usage'] as $row) {
            if ((int) $row['id'] === $id && $row['context'] === $context) $rows[] = (int) $row['file_id'] . ' in ' . $row['prop'];
        }
        sort($rows);
        return $rows;
    };
    $record = static function ($user, $id, array $data, $context = 'com_content.article') use ($app, $plugin, $usage) {
        $app->client = 'site'; $app->identity = $user;
        $plugin->recordLinks($context, (object) array('id' => $id), $data);
        return $usage($id, $context);
    };
    $author   = new FixtureUser(31);
    $files    = new FixtureUser(34, array('flexicontent.viewallfiles@com_flexicontent'));
    $fcadmin  = new FixtureUser(35, array('core.admin@com_flexicontent'));

    $check($record($author, 77, array('introtext' => $link(4242))) === array(), 'links: no row for a file of another uploader');
    $check($record($author, 77, array('introtext' => $plain(4242))) === array(), 'links: nor for the plain text form of the link');
    $check($record($author, 77, array('introtext' => $link(4242) . $link(4300), 'fulltext' => $plain(4300))) === array('4300 in fulltext', '4300 in introtext'), 'links: own uploads are recorded');
    $check($record($author, 77, array('attribs' => array('note' => $link(4300), 'more' => array('deep' => $link(4242))))) === array('4300 in attribs.note'), 'links: the same rule applies to nested values');
    $check($record($author, 77, array('introtext' => $link(9999) . $link(0) . $link('4242abc') . str_replace('id=', 'id[]=', $link(4242)))) === array(), 'links: no row for a missing file or an id that is not a plain number');
    $check($record(new FixtureUser(0), 77, array('introtext' => $link(4400) . $link(4242))) === array(), 'links: a guest is not the uploader of files without an uploader');
    $check($record(null, 77, array('introtext' => $link(4300))) === array(), 'links: no row without a user');
    $check($record($files, 77, array('introtext' => $link(4242))) === array('4242 in introtext'), 'links: users who can view all files can link any file');
    $check($record($author, 77, array('fulltext' => $link(4242))) === array('4242 in fulltext'), 'links: a recorded link is kept when another user saves the record');
    $check($record($author, 78, array('introtext' => $link(4242))) === array(), 'links: a link recorded for one record does not carry over to another');
    $check($usage(77) === array('4242 in fulltext'), 'links: rows of other records are untouched');
    $check($record($author, 77, array('introtext' => $link(4242)), 'com_categories.category') === array() && $usage(77) === array('4242 in fulltext'), 'links: recorded links are kept per context');
    $check($record($author, 77, array('introtext' => 'No link')) === array(), 'links: a removed link is removed from the usage');
    $check($record($author, 77, array('introtext' => $link(4242))) === array(), 'links: and cannot be added again by that user');
    $check($record($fcadmin, 77, array('introtext' => $link(4242))) === array('4242 in introtext'), 'links: component administrators can link any file');

    // 5. Download links: a link that the stored copy already had, without a usage row, was refused when it was added.
    //    Another user saving the record does not grant it, unless the owner of the record may link the file too
    $db->tables['#__flexicontent_file_usage'] = array();
    $plugin->users = array(31 => $author, 34 => $files, 35 => $fcadmin);
    $save = static function ($user, $id, $owner, array $stored, array $data, $context = 'com_content.article') use ($app, $plugin, $usage) {
        $app->client = 'administrator'; $app->identity = $user;
        \Joomla\CMS\Table\Table::$stored = $stored === array() ? array() : array($id => $stored);
        $record = new \Joomla\CMS\Table\Table(array('id' => $id) + ($owner ? array('created_by' => $owner) : array()) + $data);
        $plugin->_rememberStoredFileLinks($context, $record, false);
        $plugin->recordLinks($context, $record, $data);
        return $usage($id, $context);
    };
    $planted = array('introtext' => $link(4242));
    $check($save($author, 80, 31, array('introtext' => 'No link'), $planted) === array(), 'stored links: the author cannot link a file of another uploader');
    $check($save($fcadmin, 80, 31, $planted, $planted) === array(), 'stored links: an administrator saving the record again does not grant the author\'s link');
    $check($save($files, 80, 31, $planted, $planted) === array(), 'stored links: nor does a file manager');
    $check($save($fcadmin, 80, 31, $planted, array('introtext' => $link(4242) . $link(4400))) === array('4400 in introtext'), 'stored links: a link the administrator adds is recorded, the stored one is not');
    $check($save($fcadmin, 80, 31, array('introtext' => $link(4242) . $link(4400)), array('introtext' => $link(4242) . $link(4400))) === array('4400 in introtext'), 'stored links: the recorded link is kept on the next save');
    $check($save($fcadmin, 80, 31, array('introtext' => 'No link'), $planted) === array('4242 in introtext'), 'stored links: after the link was removed, an administrator can add it');
    $db->tables['#__flexicontent_file_usage'] = array();
    $check($save($fcadmin, 81, 34, $planted, $planted) === array('4242 in introtext'), 'stored links: a stored link is recorded when the owner of the record may link the file');
    $check($save($fcadmin, 82, 31, array('attribs' => json_encode(array('note' => $link(4242)))), array('attribs' => array('note' => $link(4242)))) === array(), 'stored links: a link inside stored JSON parameters counts as stored');
    $db->tables['#__flexicontent_fields_item_relations'] = array(array('item_id' => 83, 'value' => $link(4242)), array('item_id' => 84, 'value' => 'plain text'));
    $check($save($fcadmin, 83, 31, array('introtext' => 'No link'), array('custom' => array('text1' => $link(4242)))) === array(), 'stored links: a link inside a stored field value counts as stored');
    $check($save($fcadmin, 84, 31, array('introtext' => 'No link'), array('custom' => array('text1' => $link(4242)))) === array('4242 in custom.text1'), 'stored links: field values of other items do not count');
    $check($save($fcadmin, 85, 0, $planted, $planted, 'com_modules.module') === array(), 'stored links: a record without an owner keeps a stored link refused');
    $check($save($fcadmin, 85, 0, $planted, array('introtext' => $link(4242) . $link(4300)), 'com_modules.module') === array('4300 in introtext'), 'stored links: and takes a new one');
    $check($save($fcadmin, 86, 99, $planted, $planted) === array(), 'stored links: an owner that no longer exists grants nothing');
    $check($plugin->_stored_file_links === array(), 'stored links: the remembered copy is used for one save only');
    $check($save($fcadmin, 87, 31, array(), $planted) === array('4242 in introtext'), 'stored links: without a stored copy the saving user\'s right decides, as before');
    $check($plugin->_downloadLinkFileIds(array('a' => $link(7) . $plain(8), 'b' => array('c' => (object) array('d' => $link(9))), 'e' => 12, 'f' => str_replace('download_file', 'other', $link(10)), 'g' => $link('0'), 'h' => str_replace('id=', 'id[]=', $link(11)))) === array(7, 8, 9), 'stored links: ids are read from strings, arrays and objects only');

    // The route handler runs on pages of other components, where the helper classes of the component are not loaded:
    // the user form of the back end failed with a missing class
    $handler = str_replace("\r\n", "\n", file_get_contents(dirname(__DIR__) . '/plugins/system/flexisystem/flexisystem.php'));
    $from = strpos($handler, 'function onAfterRoute()');
    $to = strpos($handler, "\n\tfunction ", $from + 10) ?: strpos($handler, "\n\tpublic function ", $from + 10);
    $body = substr($handler, $from, $to - $from);
    $use = strpos($body, 'flexicontent_html::');
    $load = strpos($body, "require_once(JPATH_SITE . '/components/com_flexicontent/classes/flexicontent.helper.php');");
    $check($from !== false && $to > $from && $use !== false, 'route handler: the use of the helper class was not found');
    $check($load !== false && $load < $use, 'route handler: the helper class is used before the helper file is loaded');
    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
