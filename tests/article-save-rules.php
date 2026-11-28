<?php
/**
 * Articles saved through Joomla's own article form or Web Services: the FLEXIcontent rules that apply to such a save
 * (creatable type, author quota, edit permission of the item, approval of changes, owner change), the mark of
 * FLEXIcontent's own events that a request cannot set, and the custom field values of a save that posted none.
 * Runs the real plugin and model methods against doubles; no Joomla bootstrap, no database.
 */
namespace Joomla\CMS {
    final class Factory {
        public static $app, $db;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($service) { return Factory::$db; } }; }
    }
}
namespace Joomla\CMS\Language {
    final class Text {
        public static function _($key) { return $key; }
        public static function sprintf($key, ...$args) { return $key . ' ' . implode(' ', $args); }
    }
}
namespace Joomla\CMS\Component {
    final class ComponentHelper {
        public static function getParams($component) { return new \Joomla\Registry\Registry(array('jarticle_form_typeid' => 2)); }
    }
}
namespace Joomla\Utilities {
    final class ArrayHelper { public static function toInteger($a) { return array_map('intval', (array) $a); } }
}
namespace Joomla\Registry {
    final class Registry {
        private $data;
        public function __construct($data = null) { $this->data = is_array($data) ? $data : array(); }
        public function get($name, $default = null) { return isset($this->data[$name]) && $this->data[$name] !== '' ? $this->data[$name] : $default; }
    }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_ROOT', dirname(__DIR__));
    define('JPATH_ADMINISTRATOR', sys_get_temp_dir() . '/fc-article-save-' . bin2hex(random_bytes(6)));
    define('FLEXI_J40GE', true);

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

    // Database double: the statements of the quota check (count, named lock) and the field name query
    final class FixtureQuery {
        public $columns = '', $table = '', $where = array();
        public function select($columns) { $this->columns = implode(',', (array) $columns); return $this; }
        public function from($table) { $this->table = $table; return $this; }
        public function where($condition) { $this->where[] = $condition; return $this; }
        public function __toString() { return 'select ' . $this->columns . ' from ' . $this->table . ' where ' . implode(' AND ', $this->where); }
    }
    final class FixtureDb {
        public $authored = array(), $locks = array(), $lockFree = true, $fields = array(), $log = array();
        private $sql = '';
        public function getQuery($new = false) { return new FixtureQuery; }
        public function qn($name) { return is_array($name) ? array_map(array($this, 'qn'), $name) : '`' . $name . '`'; }
        public function quote($value) { return "'" . $value . "'"; }
        public function getPrefix() { return 'jos_'; }
        public function setQuery($query) { $this->sql = (string) $query; $this->log[] = $this->sql; return $this; }
        public function loadResult() {
            if ($this->sql === 'SELECT DATABASE()') return 'fixture';
            if (preg_match("/^SELECT GET_LOCK\('(\w+)', 5\)$/", $this->sql, $m)) {
                if (!$this->lockFree) return 0;
                $this->locks[$m[1]] = (isset($this->locks[$m[1]]) ? $this->locks[$m[1]] : 0) + 1;
                return 1;
            }
            if (preg_match("/^SELECT RELEASE_LOCK\('(\w+)'\)$/", $this->sql, $m)) {
                if (empty($this->locks[$m[1]])) return 0;
                if (--$this->locks[$m[1]] === 0) unset($this->locks[$m[1]]);
                return 1;
            }
            if (preg_match('/^SELECT COUNT\(id\) FROM #__content WHERE created_by = (\d+)$/', $this->sql, $m)) {
                return isset($this->authored[$m[1]]) ? $this->authored[$m[1]] : 0;
            }
            throw new \RuntimeException('Unexpected statement: ' . $this->sql);
        }
        public function loadObjectList() {
            if (!preg_match('/^select `id`,`name` from `#__flexicontent_fields` where `iscore` = 0 AND `id` IN \(([\d,]+)\)$/', $this->sql, $m)) {
                throw new \RuntimeException('Unexpected statement: ' . $this->sql);
            }
            $rows = array();
            foreach ($this->fields as $field) {
                if (!$field['iscore'] && in_array((string) $field['id'], explode(',', $m[1]), true)) $rows[] = (object) $field;
            }
            return $rows;
        }
    }

    // Request, session, user and application doubles
    final class FixtureInput {
        public $data = array(), $post;
        public function get($name, $default = null, $filter = null) { return isset($this->data[$name]) ? $this->data[$name] : $default; }
        public function getInt($name, $default = 0) { return isset($this->data[$name]) ? (int) $this->data[$name] : $default; }
        public function set($name, $value) { $this->data[$name] = $value; }
    }
    final class FixtureSession {
        public $data = array(), $sets = 0;
        public function has($name, $group = null) { return isset($this->data[$name]); }
        public function get($name, $default = null, $group = null) { return isset($this->data[$name]) ? $this->data[$name] : $default; }
        public function set($name, $value, $group = null) { ++$this->sets; $this->data[$name] = $value; }
    }
    final class FixtureUser {
        public $id, $guest, $rights, $super = false;
        public function __construct($id, array $rights = array()) { $this->id = $id; $this->guest = !$id; $this->rights = $rights; }
        // A right is given for one asset ("action@asset") or for all of them ("action@*")
        public function authorise($action, $asset = null) {
            return $this->super || in_array($action . '@' . $asset, $this->rights, true) || in_array($action . '@*', $this->rights, true);
        }
        public function get($name) { return $this->$name; }
    }
    final class FixtureApp {
        public $client = 'administrator', $identity, $input, $session, $messages = array();
        public function __construct() { $this->input = new FixtureInput; $this->input->post = new FixtureInput; $this->session = new FixtureSession; }
        public function isClient($name) { return $this->client === $name; }
        public function getIdentity() { return $this->identity; }
        public function getSession() { return $this->session; }
        public function enqueueMessage($message, $type = 'message') { $this->messages[] = $message; }
    }

    // The article record that Joomla is about to store, and the FLEXIcontent record of what is stored now
    #[\AllowDynamicProperties]
    final class FixtureArticle {
        public $id = 0, $created_by = 0, $catid = 5, $state = 1, $error = '';
        public function __construct(array $values) { foreach ($values as $k => $v) $this->$k = $v; }
        public function setError($error) { $this->error = $error; }
        public function getError() { return $this->error; }
    }
    #[\AllowDynamicProperties]
    final class flexicontent_items {
        public static $rows = array();
        public $id, $created_by, $catid, $state, $type_id;
        public function __construct($db) {}
        public function load($id) {
            if (!isset(self::$rows[$id])) return false;
            foreach (self::$rows[$id] + array('id' => $id) as $k => $v) $this->$k = $v;
            return true;
        }
        public function bind($data) { return true; }
        public function store() { return true; }
    }

    // FLEXIcontent helpers that the checked methods call
    final class flexicontent_html {
        public static $types = array();
        public static function getTypesList($type_ids = false, $check_perms = false, $published = true) {
            $types = array();
            foreach (self::$types as $id => $itemscreatable) {
                if ($check_perms && $itemscreatable && !\Joomla\CMS\Factory::$app->identity->authorise('core.create', 'com_flexicontent.type.' . $id)) continue;
                $types[$id] = (object) array('id' => $id, 'itemscreatable' => $itemscreatable);
            }
            return $types;
        }
    }
    final class FlexicontentHelperPerm {
        public static $denied = array();
        public static function checkTypeAccess($type_id, $rule) { return !$type_id || !in_array($rule . '@' . $type_id, self::$denied, true); }
    }
    final class flexicontent_db {
        public static $limits = array();
        public static function getUserConfig($user_id) {
            return new \Joomla\Registry\Registry(array('max_auth_limit' => isset(self::$limits[$user_id]) ? self::$limits[$user_id] : 0));
        }
    }
    final class FlexicontentModelItems {
        public static $synced = 0;
        public function updateItemCountingData(...$a) { ++self::$synced; }
    }

    $app = \Joomla\CMS\Factory::$app = new FixtureApp;
    $db  = \Joomla\CMS\Factory::$db = new FixtureDb;

    mkdir(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models', 0777, true);
    file_put_contents(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/items.php', '<?php ');
    register_shutdown_function(static function () {
        @unlink(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/items.php');
        foreach (array('/components/com_flexicontent/models', '/components/com_flexicontent', '/components', '') as $dir) @rmdir(JPATH_ADMINISTRATOR . $dir);
    });

    $pluginSource = file_get_contents(JPATH_ROOT . '/plugins/system/flexisystem/flexisystem.php');
    $baseSource   = file_get_contents(JPATH_ROOT . '/admin/models/base/base.php');
    $itemSource   = file_get_contents(JPATH_ROOT . '/admin/models/parentclassitem.php');
    $viewSource   = file_get_contents(JPATH_ROOT . '/admin/views/item/view.html.php');

    // The real flag of the base model and the real permission methods of the item model; its save is recorded only
    eval('abstract class FCModelAdmin { private static $internalContentEvents = false;
        public static ' . methodSource($baseSource, 'internalContentEvents') . ' }');
    eval('final class FlexicontentModelItem extends FCModelAdmin {
        public static $stored = array(), $fieldValues = array(), $versioning = 1, $itemLoads = 0;
        public $type_alias = "com_content.article", $_record = null, $_id = 0;
        public function getTypeslist($type_ids = false, $check_perms = false, $published = true) { return flexicontent_html::getTypesList($type_ids, $check_perms, $published); }
        public function setId($id, ...$more) { $this->_id = (int) $id; }
        public function getComponentTypeParams() { return new \Joomla\Registry\Registry(array("use_versioning" => self::$versioning)); }
        public function mergeAttributes(&$record, &$data, $properties, $options) {}
        public function mergeJTagsAssignments(...$a) {}
        public function getItem(...$a) { ++self::$itemLoads; return false; }
        public function getCustomFieldsValues($item_id = 0, $version = 0) { return isset(self::$fieldValues[$item_id]) ? self::$fieldValues[$item_id] : array(); }
        public function store($data) { self::$stored[] = $data; self::internalContentEvents(true); return true; }
        public ' . methodSource($itemSource, 'canEdit') . '
        public ' . methodSource($itemSource, 'canEditState') . '
        public ' . methodSource($itemSource, 'canCreateType') . ' }');

    // The real plugin methods, with the plugin's surrounding jobs replaced by no-ops
    $methods = '';
    foreach (array('onContentBeforeSave', 'onContentAfterSave', 'renderFields', '_isInternalEvent', '_articleSaveRefusal', '_authorQuotaRefusal', '_releaseAuthorQuotaLock', '_storedCustomFieldValues') as $name) {
        if (strpos($pluginSource, 'function ' . $name . '(') !== false) $methods .= ' public ' . methodSource($pluginSource, $name);
    }
    eval('use Joomla\CMS\Factory; use Joomla\CMS\Language\Text; use Joomla\CMS\Component\ComponentHelper; use Joomla\Utilities\ArrayHelper; use Joomla\Registry\Registry;
        final class ArticleSavePlugin {
            private $_author_quota_lock = null;
            private function _rememberStoredFileLinks(...$a) {} private function _updateFileUsage_FcFileBtn_DownloadLinks(...$a) {}
            private function _loadFcHelpersAndLanguage() {}
            public function onExtensionBeforeSave(...$a) {} public function onExtensionAfterSave(...$a) {}
            ' . $methods . ' }');
    $plugin = new ArticleSavePlugin;

    // Stored articles: 70 to 75 belong to user 31, 71 to user 50
    flexicontent_items::$rows = array(
        70 => array('created_by' => 31, 'catid' => 5, 'state' => 1, 'type_id' => 2),
        71 => array('created_by' => 50, 'catid' => 5, 'state' => 1, 'type_id' => 2),
        72 => array('created_by' => 31, 'catid' => 5, 'state' => -4, 'type_id' => 2),
        73 => array('created_by' => 31, 'catid' => 5, 'state' => -3, 'type_id' => 2),
        74 => array('created_by' => 31, 'catid' => 5, 'state' => 1, 'type_id' => 3),
        75 => array('created_by' => 31, 'catid' => 5, 'state' => 1, 'type_id' => 0),
    );
    // Types: 2 can be created by any author, 3 only with the create right of the type
    flexicontent_html::$types = array(2 => 0, 3 => 1);

    $author      = new FixtureUser(31, array('core.edit.own@*'));
    $ownState    = new FixtureUser(31, array('core.edit.own@*', 'core.edit.state.own@*'));
    $autoApprove = new FixtureUser(31, array('core.edit.own@*', 'flexicontent.autoapprovechanges@com_flexicontent'));
    $publisher   = new FixtureUser(32, array('core.edit@*', 'core.edit.state@*'));
    $ownerEditor = new FixtureUser(32, array('core.edit@*', 'core.edit.state@*', 'flexicontent.editcreator@com_flexicontent'));
    $oneArticle  = new FixtureUser(34, array('core.edit@com_content.article.70', 'core.edit.state@com_content.article.70'));
    $super       = new FixtureUser(33); $super->super = true;

    // One save through Joomla: "stored" when the event allows it and has prepared the article parameters,
    // "refused: <reason>" when it returns false with the reason on the record and has prepared nothing
    $save = static function ($user, array $article, $context = 'com_content.article', $client = 'administrator') use ($app, $plugin) {
        $app->client = $client; $app->identity = $user; $app->session->sets = 0;
        $row    = new FixtureArticle($article);
        $result = $plugin->onContentBeforeSave($context, $row, empty($article['id']), $article);
        if ($result === true && $row->error === '') return $app->session->sets === 1 ? 'stored' : 'not handled';
        return $result === false && $row->error !== '' && $app->session->sets === 0 ? 'refused: ' . $row->error : 'unexpected';
    };
    $refused = static function ($outcome, $reason) { return strpos($outcome, 'refused: ') === 0 && strpos($outcome, $reason) !== false; };
    $edit = static function ($id, array $changes = array()) { return $changes + array('id' => $id) + flexicontent_items::$rows[$id]; };

    // 1. New article: a type that the user may create, and the author quota
    $check($save($author, array('title' => 'New')) === 'stored', 'new: an author who may create a type saves a new article');
    $check($save($author, array('title' => 'New'), 'com_content.form', 'site') === 'stored', 'new: also through the frontend form');
    flexicontent_html::$types = array(3 => 1);
    $check($refused($save($author, array('title' => 'New')), 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE'), 'new: refused when the user may create no type');
    $check($refused($save($author, array('title' => 'New'), 'com_content.form', 'site'), 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE'), 'new: the frontend form is checked too');
    $check($save(new FixtureUser(31, array('core.create@com_flexicontent.type.3')), array('title' => 'New')) === 'stored', 'new: the create right of the type allows it');
    flexicontent_html::$types = array(2 => 0, 3 => 1);

    flexicontent_db::$limits = array(31 => 2);
    $db->authored = array(31 => 2);
    $check($refused($save($author, array('title' => 'New')), 'FLEXI_ALERTNOTAUTH_CREATE_MORE 2'), 'quota: an author at the limit cannot add an article');
    $check($db->locks === array(), 'quota: a refused save holds no lock');
    $db->authored = array(31 => 1);
    $check($save($author, array('title' => 'New')) === 'stored', 'quota: an author below the limit can');
    $check(count($db->locks) === 1, 'quota: the check holds its lock until the article is stored');
    $plugin->onContentAfterSave('com_content.article', new FixtureArticle(array('id' => 90)), true, array('title' => 'New', 'catid' => 5));
    $check($db->locks === array(), 'quota: the lock is released after the article is stored');
    $check($save($author, array('title' => 'New')) === 'stored' && $save($author, array('title' => 'New')) === 'stored' && count($db->locks) === 1 && reset($db->locks) === 1,
        'quota: a save that did not reach the store does not leave its lock for the next one');
    $plugin->onContentAfterSave('com_content.article', new FixtureArticle(array('id' => 91)), true, array('title' => 'New', 'catid' => 5));
    $db->lockFree = false;
    $check($refused($save($author, array('title' => 'New')), 'in progress'), 'quota: refused while another save of the author holds the lock');
    $db->lockFree = true;
    $db->log = array();
    $check($save($publisher, array('title' => 'New')) === 'stored' && $db->log === array(), 'quota: users without a limit are not counted');
    $check($save($author, $edit(72, array('title' => 'Changed'))) === 'stored' && $db->locks === array(), 'quota: an existing article is not counted');
    $db->authored = array(31 => 2);

    // 2. Owner of a new article
    $check($save($publisher, array('title' => 'New', 'created_by' => 32)) === 'stored', 'owner: the saving user as author of a new article');
    $check($save($publisher, array('title' => 'New', 'created_by' => '')) === 'stored', 'owner: no author given for a new article');
    $check($refused($save($publisher, array('title' => 'New', 'created_by' => 50)), 'author'), 'owner: another user as author of a new article is refused');
    $check($save($ownerEditor, array('title' => 'New', 'created_by' => 50)) === 'stored', 'owner: allowed with the right to edit the creator');

    // 3. Existing article: the edit permission of the FLEXIcontent item
    $check($save($publisher, $edit(70, array('title' => 'Changed'))) === 'stored', 'edit: an editor who may publish saves an article');
    $check($refused($save($author, $edit(71, array('title' => 'Changed'))), 'FLEXI_NO_ACCESS_EDIT'), 'edit: an article of another author is refused for an edit-own user');
    $check($save($oneArticle, $edit(70, array('title' => 'Changed'))) === 'stored', 'edit: a right given on one article allows that article');
    $check($refused($save($oneArticle, $edit(71, array('title' => 'Changed'))), 'FLEXI_NO_ACCESS_EDIT'), 'edit: and no other article');
    FlexicontentHelperPerm::$denied = array('core.edit@3', 'core.edit.own@3');
    $check($refused($save($publisher, $edit(74, array('title' => 'Changed'))), 'FLEXI_NO_ACCESS_EDIT'), 'edit: refused when the type does not let the user edit its items');
    $check($save($publisher, $edit(70, array('title' => 'Changed'))) === 'stored', 'edit: items of other types are not affected');
    FlexicontentHelperPerm::$denied = array();
    $check($save($publisher, $edit(75, array('title' => 'Changed'))) === 'stored', 'edit: an article without a type is checked by its own permissions');
    $check($refused($save(new FixtureUser(0), $edit(70, array('title' => 'Changed'))), 'FLEXI_NO_ACCESS_EDIT'), 'edit: a guest cannot edit');

    // 4. Existing article: changes of a user who cannot edit the state wait for approval in FLEXIcontent
    $check($refused($save($author, $edit(70, array('title' => 'Changed'))), 'need approval'), 'approval: a visible article cannot be changed directly without the right to edit its state');
    $check($refused($save($author, $edit(70, array('title' => 'Changed')), 'com_content.form', 'site'), 'need approval'), 'approval: nor through the frontend form');
    $check($save($ownState, $edit(70, array('title' => 'Changed'))) === 'stored', 'approval: not needed with the right to edit the state of own articles');
    $check($save($autoApprove, $edit(70, array('title' => 'Changed'))) === 'stored', 'approval: not needed with the right to auto approve changes');
    $check($save($author, $edit(72, array('title' => 'Changed'))) === 'stored', 'approval: not needed for a draft');
    $check($save($author, $edit(73, array('title' => 'Changed'))) === 'stored', 'approval: not needed for an article that is pending approval');
    FlexicontentModelItem::$versioning = 0;
    $check($save($author, $edit(70, array('title' => 'Changed'))) === 'stored', 'approval: not needed with versioning off');
    FlexicontentModelItem::$versioning = 1;
    FlexicontentHelperPerm::$denied = array('core.edit.state@2', 'core.edit.state.own@2');
    $check($refused($save($publisher, $edit(70, array('title' => 'Changed'))), 'need approval'), 'approval: needed when the type does not let the user edit the state');
    FlexicontentHelperPerm::$denied = array();

    // 5. Existing article: the owner
    $check($refused($save($publisher, $edit(70, array('created_by' => 32))), 'author'), 'owner: changing the author of an article is refused');
    $check($save($ownerEditor, $edit(70, array('created_by' => 32))) === 'stored', 'owner: allowed with the right to edit the creator');
    $check($save($publisher, $edit(70, array('created_by' => '31'))) === 'stored', 'owner: the same author posted again is no change');
    $check($refused($save($ownState, $edit(71, array('created_by' => 31))), 'FLEXI_NO_ACCESS_EDIT'), 'owner: taking over an article of another author does not make it editable');
    $check($refused($save(new FixtureUser(31, array('core.edit@*', 'core.edit.state.own@*')), $edit(71, array('created_by' => 31))), 'author'),
        'owner: nor publishable as an own article');

    // 6. Super Users, saves without a user, other records
    flexicontent_html::$types = array(3 => 1);
    FlexicontentHelperPerm::$denied = array('core.edit@2', 'core.edit.own@2');
    $db->log = array();
    $check($save($super, array('title' => 'New', 'created_by' => 50)) === 'stored' && $save($super, $edit(71, array('created_by' => 33))) === 'stored' && $db->log === array(),
        'exempt: a Super User is not restricted');
    $check($save(null, array('title' => 'New', 'created_by' => 50), 'com_content.article', 'cli') === 'stored' && $save(null, $edit(71, array('title' => 'Changed')), 'com_content.article', 'cli') === 'stored',
        'exempt: a command line save is not restricted');
    $check($save($author, $edit(71, array('title' => 'Changed')), 'com_categories.category') === 'not handled', 'exempt: other records are not checked');
    flexicontent_html::$types = array(2 => 0, 3 => 1);
    FlexicontentHelperPerm::$denied = array();

    // 7. FLEXIcontent's own events are marked by its code, not by the request
    $check(FCModelAdmin::internalContentEvents() === false, 'mark: off by default');
    $app->input->data = array('isflexicontent' => 'yes');
    $app->input->post->data = array('isflexicontent' => 'yes');
    $check($refused($save($author, $edit(71, array('title' => 'Changed'))), 'FLEXI_NO_ACCESS_EDIT'), 'mark: a request variable does not switch the check off');
    FlexicontentModelItem::$stored = array(); FlexicontentModelItems::$synced = 0;
    $plugin->onContentAfterSave('com_content.article', new FixtureArticle(array('id' => 70)), false, $edit(70));
    $check(count(FlexicontentModelItem::$stored) === 1 && FlexicontentModelItems::$synced === 1, 'mark: nor the copy of the article to the FLEXIcontent tables');
    $check(FCModelAdmin::internalContentEvents() === false, 'mark: the mark of the copy itself ends with it');
    $plugin->onContentAfterSave('com_content.article', new FixtureArticle(array('id' => 71)), false, $edit(71));
    $check(count(FlexicontentModelItem::$stored) === 2, 'mark: a following save of the same request is copied too');
    $app->input->data = array('option' => 'com_content', 'view' => 'article', 'isflexicontent' => 'yes');
    $params = null; $row = (object) array('id' => 70);
    $plugin->renderFields('com_content.article', $row, $params, 0, 'beforeContent');
    $check(FlexicontentModelItem::$itemLoads === 1, 'mark: nor the fields of the Joomla article view');
    $app->input->data = array(); $app->input->post->data = array();

    $check(FCModelAdmin::internalContentEvents(true) === false && FCModelAdmin::internalContentEvents() === true, 'mark: set by code, the call returns the value before it');
    FlexicontentModelItem::$stored = array(); FlexicontentModelItems::$synced = 0; $app->session->sets = 0;
    $own = new FixtureArticle($edit(71, array('title' => 'Changed', 'created_by' => 31)));
    $app->identity = $author;
    $check($plugin->onContentBeforeSave('com_content.article', $own, false, $edit(71)) === true && $own->error === '' && $app->session->sets === 0, 'mark: the save of the FLEXIcontent item model is left alone');
    $check($plugin->onContentAfterSave('com_content.article', $own, false, $edit(71)) === true && FlexicontentModelItem::$stored === array() && FlexicontentModelItems::$synced === 0,
        'mark: and is not copied a second time');
    $app->input->data = array('option' => 'com_content', 'view' => 'article');
    $row = (object) array('id' => 71);
    $plugin->renderFields('com_content.article', $row, $params, 0, 'beforeContent');
    $check(FlexicontentModelItem::$itemLoads === 1, 'mark: the item view of FLEXIcontent gets no second copy of its fields');
    FCModelAdmin::internalContentEvents(false);
    $row = (object) array('id' => 72);
    $plugin->renderFields('com_content.article', $row, $params, 0, 'beforeContent');
    $check(FlexicontentModelItem::$itemLoads === 2, 'mark: the Joomla article view gets them');
    $app->input->data = array();

    $check(strpos(methodSource($itemSource, 'store'), 'self::internalContentEvents(true);') !== false, 'mark: set by the save of the item model');
    $check(strpos(methodSource($baseSource, 'setitemstate'), 'self::internalContentEvents(true);') !== false, 'mark: set by the state change of the models');
    $check(strpos(methodSource($viewSource, '_displayItem'), 'FCModelAdmin::internalContentEvents(true);') !== false, 'mark: set by the item view');
    $check(!preg_match('/->get\(\s*[\'"]isflexicontent/', $pluginSource), 'mark: the plugin does not read the request variable');

    // 8. Custom field values: a save that posted none keeps the stored values
    $db->fields = array(
        array('id' => 1, 'name' => 'text', 'iscore' => 1),
        array('id' => 21, 'name' => 'subtitle', 'iscore' => 0),
        array('id' => 22, 'name' => 'gallery', 'iscore' => 0),
    );
    FlexicontentModelItem::$fieldValues = array(70 => array(1 => array('Body'), 21 => array('Stored subtitle'), 22 => array(array('a.jpg', 'A'), array('b.jpg', 'B')), 99 => array('No such field')));
    $custom = static function ($id, array $post, $isNew = false) use ($app, $plugin, $publisher) {
        $app->client = 'administrator'; $app->identity = $publisher; $app->input->post->data = $post;
        FlexicontentModelItem::$stored = array();
        $data = $isNew ? array('title' => 'New', 'catid' => 5) : array('id' => $id, 'title' => 'Changed', 'catid' => 5);
        $plugin->onContentAfterSave('com_content.article', new FixtureArticle(array('id' => $id)), $isNew, $data);
        $app->input->post->data = array();
        return count(FlexicontentModelItem::$stored) === 1 ? FlexicontentModelItem::$stored[0]['custom'] : 'not saved once';
    };
    $stored = array('subtitle' => array('Stored subtitle'), 'gallery' => array(array('a.jpg', 'A'), array('b.jpg', 'B')));
    $check($custom(70, array()) === $stored, 'fields: a save without field values gives the stored values of the custom fields to the model');
    $check($custom(70, array('jform' => array('title' => 'Changed'))) === $stored, 'fields: also when only the article form was posted');
    $check($custom(70, array('custom' => array('subtitle' => array('Posted')))) === array('subtitle' => array('Posted')), 'fields: posted values are used as posted');
    $check($custom(70, array('custom' => array())) === array(), 'fields: a posted empty list clears the fields');
    $check($custom(70, array('unique_tmp_itemid' => '70')) === array(), 'fields: so does the fields box of the form when it posted no value');
    $check($custom(71, array()) === array(), 'fields: an item without stored values has none to keep');
    $check($custom(95, array(), true) === array(), 'fields: a new article has none either');
    $check($custom(95, array('custom' => array('subtitle' => array('Posted'))), true) === array('subtitle' => array('Posted')), 'fields: posted values of a new article are used');

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
