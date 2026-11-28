<?php
/**
 * Batch tasks of Joomla's article manager. They store an article without the before save event, so the FLEXIcontent
 * rules of a save through Joomla's article form are applied at the before batch event of Joomla's models: the rules of
 * a new article (creatable type, author, author quota) to a batch copy, the rules of an existing article (edit
 * permission, approval of changes) to a batch change of its access level or language. After a batch copy or move
 * Joomla fires the after save event itself, without the article data: nothing of another save is written then.
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
namespace Joomla\Event {
    interface EventInterface {
        public function getArgument($name, $default = null);
        public function getName();
        public function isStopped();
    }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_ROOT', dirname(__DIR__));
    define('JPATH_ADMINISTRATOR', sys_get_temp_dir() . '/fc-article-batch-' . bin2hex(random_bytes(6)));
    define('FLEXI_J40GE', true);

    // A notice or warning of the checked code (a missing array key, for example) fails the run; a deprecation notice of
    // a newer PHP version is only shown
    set_error_handler(static function ($severity, $message, $file, $line) {
        if ($severity & (E_DEPRECATED | E_USER_DEPRECATED)) return false;
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

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

    // Database double: the statements of the quota check (count, named lock)
    final class FixtureDb {
        public $authored = array(), $locks = array(), $log = array();
        private $sql = '';
        public function quote($value) { return "'" . $value . "'"; }
        public function getPrefix() { return 'jos_'; }
        public function setQuery($query) { $this->sql = (string) $query; $this->log[] = $this->sql; return $this; }
        public function loadResult() {
            if ($this->sql === 'SELECT DATABASE()') return 'fixture';
            if (preg_match("/^SELECT GET_LOCK\('(\w+)', 5\)$/", $this->sql, $m)) {
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
    }

    // Request, session, user and application doubles
    final class FixtureInput {
        public $data = array(), $post;
        public function get($name, $default = null, $filter = null) { return isset($this->data[$name]) ? $this->data[$name] : $default; }
        public function getInt($name, $default = 0) { return isset($this->data[$name]) ? (int) $this->data[$name] : $default; }
        public function set($name, $value) { $this->data[$name] = $value; }
    }
    final class FixtureSession {
        public $data = array(), $reads = 0;
        public function has($name, $group = null) { return isset($this->data[$name]); }
        public function get($name, $default = null, $group = null) { ++$this->reads; return isset($this->data[$name]) ? $this->data[$name] : $default; }
        public function set($name, $value, $group = null) { $this->data[$name] = $value; }
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
        public $client = 'administrator', $identity, $input, $session;
        public function __construct() { $this->input = new FixtureInput; $this->input->post = new FixtureInput; $this->session = new FixtureSession; }
        public function isClient($name) { return $this->client === $name; }
        public function getIdentity() { return $this->identity; }
        public function getSession() { return $this->session; }
    }

    // The record of a batch task as Joomla hands it to the event, and the event itself
    #[\AllowDynamicProperties]
    final class FixtureTable {
        public $id = 0, $created_by = 0, $catid = 5, $state = 1, $access = 1, $language = '*';
        private $table;
        public function __construct(array $values, $table = '#__content') { $this->table = $table; foreach ($values as $k => $v) $this->$k = $v; }
        public function getTableName() { return $this->table; }
    }
    final class FixtureEvent implements \Joomla\Event\EventInterface {
        private $arguments;
        public function __construct(array $arguments) { $this->arguments = $arguments; }
        public function getArgument($name, $default = null) { return array_key_exists($name, $this->arguments) ? $this->arguments[$name] : $default; }
        public function getName() { return 'onBeforeBatch'; }
        public function isStopped() { return false; }
    }

    // The FLEXIcontent record of what is stored now
    #[\AllowDynamicProperties]
    final class flexicontent_items {
        public static $rows = array(), $writes = 0;
        public $id, $created_by, $catid, $state, $type_id;
        public function __construct($db) {}
        public function load($id) {
            if (!isset(self::$rows[$id])) return false;
            foreach (self::$rows[$id] + array('id' => $id) as $k => $v) $this->$k = $v;
            return true;
        }
        public function bind($data) { ++self::$writes; return true; }
        public function store() { ++self::$writes; return true; }
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
        public static function checkTypeAccess($type_id, $rule) { return true; }
    }
    final class flexicontent_db {
        public static $limits = array();
        public static function getUserConfig($user_id) {
            return new \Joomla\Registry\Registry(array('max_auth_limit' => isset(self::$limits[$user_id]) ? self::$limits[$user_id] : 0));
        }
    }
    final class FlexicontentModelItems {
        public static $synced = array();
        public function updateItemCountingData($rows = false, $catid = 0) { self::$synced[] = array($rows, $catid); }
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

    // The real flag of the base model and the real permission methods of the item model; its save is recorded only
    eval('abstract class FCModelAdmin { private static $internalContentEvents = false;
        public static ' . methodSource($baseSource, 'internalContentEvents') . ' }');
    eval('final class FlexicontentModelItem extends FCModelAdmin {
        public static $stored = array(), $versioning = 1;
        public $type_alias = "com_content.article", $_record = null, $_id = 0;
        public function getTypeslist($type_ids = false, $check_perms = false, $published = true) { return flexicontent_html::getTypesList($type_ids, $check_perms, $published); }
        public function setId($id, ...$more) { $this->_id = (int) $id; }
        public function getComponentTypeParams() { return new \Joomla\Registry\Registry(array("use_versioning" => self::$versioning)); }
        public function mergeJTagsAssignments(...$a) {}
        public function getCustomFieldsValues($item_id = 0, $version = 0) { return array(); }
        public function store($data) { self::$stored[] = $data; return true; }
        public ' . methodSource($itemSource, 'canEdit') . '
        public ' . methodSource($itemSource, 'canEditState') . '
        public ' . methodSource($itemSource, 'canCreateType') . ' }');

    // The real plugin methods, with the plugin's surrounding jobs replaced by no-ops
    $methods = '';
    foreach (array('onBeforeBatch', 'onContentAfterSave', '_isInternalEvent', '_articleSaveRefusal', '_authorQuotaRefusal', '_releaseAuthorQuotaLock', '_storedCustomFieldValues') as $name) {
        if (strpos($pluginSource, 'function ' . $name . '(') !== false) $methods .= ' public ' . methodSource($pluginSource, $name);
    }
    eval('use Joomla\CMS\Factory; use Joomla\CMS\Language\Text; use Joomla\CMS\Component\ComponentHelper; use Joomla\Utilities\ArrayHelper; use Joomla\Registry\Registry;
        final class ArticleBatchPlugin {
            public $helperLoads = 0;
            private $_author_quota_lock = null;
            private function _updateFileUsage_FcFileBtn_DownloadLinks(...$a) {}
            private function _loadFcHelpersAndLanguage() { ++$this->helperLoads; }
            public function onExtensionAfterSave(...$a) {}
            ' . $methods . ' }');
    $plugin = new ArticleBatchPlugin;
    $check(method_exists($plugin, 'onBeforeBatch'), 'the plugin listens to the before batch event of Joomla\'s models');
    if (!method_exists($plugin, 'onBeforeBatch')) { fwrite(STDERR, "the plugin has no onBeforeBatch method\n"); exit(1); }

    // Stored articles: 70, 72 and 73 belong to user 31, 71 to user 50
    flexicontent_items::$rows = array(
        70 => array('created_by' => 31, 'catid' => 5, 'state' => 1, 'type_id' => 2),
        71 => array('created_by' => 50, 'catid' => 5, 'state' => 1, 'type_id' => 2),
        72 => array('created_by' => 31, 'catid' => 5, 'state' => -4, 'type_id' => 2),
    );
    // Types: 2 can be created by any author, 3 only with the create right of the type
    flexicontent_html::$types = array(2 => 0, 3 => 1);

    $author      = new FixtureUser(31, array('core.edit@*'));
    $publisher   = new FixtureUser(32, array('core.edit@*', 'core.edit.state@*'));
    $ownerEditor = new FixtureUser(32, array('core.edit@*', 'core.edit.state@*', 'flexicontent.editcreator@com_flexicontent'));
    $autoApprove = new FixtureUser(31, array('core.edit@*', 'flexicontent.autoapprovechanges@com_flexicontent'));
    $ownOnly     = new FixtureUser(31, array('core.edit.own@*', 'core.edit.state.own@*'));
    $super       = new FixtureUser(33); $super->super = true;

    // One record of a batch task as Joomla's model hands it over just before it stores the record:
    // "allowed", or "refused: <reason>" when the listener ends the batch (code 403)
    $batch = static function ($user, $type, array $record, $table = '#__content', $client = 'administrator') use ($app, $plugin) {
        $app->client = $client; $app->identity = $user;
        try {
            $result = $plugin->onBeforeBatch(new FixtureEvent(array('src' => new FixtureTable($record, $table), 'type' => $type)));
        } catch (\RuntimeException $e) {
            return $e->getCode() === 403 ? 'refused: ' . $e->getMessage() : 'unexpected code ' . $e->getCode();
        }
        return $result === null ? 'allowed' : 'unexpected result';
    };
    $refused = static function ($outcome, $reason) { return strpos($outcome, 'refused: ') === 0 && strpos($outcome, $reason) !== false; };
    // A copy as the batch prepares it: the stored article without its id, unpublished, in the chosen category
    $copyOf = static function ($id) { return array('id' => 0, 'state' => 0, 'catid' => 9) + flexicontent_items::$rows[$id]; };
    $change = static function ($id, array $changes) { return $changes + array('id' => $id) + flexicontent_items::$rows[$id]; };
    // The after save event that com_content fires itself after a batch copy or move: not a new record, no article data
    $afterBatch = static function ($id, $catid = 9) use ($plugin) {
        return $plugin->onContentAfterSave('com_content.article', new FixtureTable(array('id' => $id, 'catid' => $catid)), false, array('com_fields' => array('subtitle' => 'Copied')));
    };

    // 1. Batch copy: the author quota
    flexicontent_db::$limits = array(31 => 2);
    $db->authored = array(31 => 2);
    $check($refused($batch($author, 'copy', $copyOf(70)), 'FLEXI_ALERTNOTAUTH_CREATE_MORE 2'), 'copy: an author at the limit cannot add an article with a batch copy');
    $check($db->locks === array(), 'copy: a refused copy holds no lock');
    $db->authored = array(31 => 1);
    $check($batch($author, 'copy', $copyOf(70)) === 'allowed', 'copy: an author below the limit can');
    $check(count($db->locks) === 1, 'copy: the quota check holds its lock until the copy is stored');
    $check($afterBatch(90) === true && $db->locks === array(), 'copy: the lock is released by the after save event of the copy');

    // A batch of own articles: every stored copy counts for the next one
    $db->authored = array(31 => 0); flexicontent_db::$limits = array(31 => 2);
    $copied = 0; $outcome = '';
    foreach (array(70, 72, 70, 72) as $n => $id) {
        $outcome = $batch($author, 'copy', $copyOf($id));
        if ($outcome !== 'allowed') break;
        ++$db->authored[31]; ++$copied;
        $afterBatch(91 + $n);
    }
    $check($copied === 2 && $refused($outcome, 'FLEXI_ALERTNOTAUTH_CREATE_MORE 2') && $db->locks === array(), 'copy: a batch stops at the record that would exceed the limit, the copies before it are done');
    $db->log = array();
    $check($batch($publisher, 'copy', array('id' => 0, 'created_by' => 32, 'catid' => 9)) === 'allowed' && $db->log === array(), 'copy: users without a limit are not counted');
    flexicontent_db::$limits = array(); $db->authored = array();

    // 2. Batch copy: a type that the user may create, and the author of the copy
    flexicontent_html::$types = array(3 => 1);
    $check($refused($batch($author, 'copy', $copyOf(70)), 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE'), 'copy: refused when the user may create no type');
    $check($batch(new FixtureUser(31, array('core.edit@*', 'core.create@com_flexicontent.type.3')), 'copy', $copyOf(70)) === 'allowed', 'copy: the create right of a type allows it');
    flexicontent_html::$types = array(2 => 0, 3 => 1);
    $check($refused($batch($publisher, 'copy', $copyOf(71)), 'author'), 'copy: a copy keeps the author of the article, for an article of another author it is refused');
    $check($batch($ownerEditor, 'copy', $copyOf(71)) === 'allowed', 'copy: allowed with the right to edit the creator');
    flexicontent_db::$limits = array(32 => 1); $db->authored = array(32 => 1);
    $check($refused($batch($ownerEditor, 'copy', $copyOf(71)), 'FLEXI_ALERTNOTAUTH_CREATE_MORE 1'), 'copy: the limit of the user who makes the copy applies to it');
    flexicontent_db::$limits = array(); $db->authored = array();

    // 3. Batch change of an existing article (access level, language): the rules of an existing article
    $check($batch($publisher, 'access', $change(70, array('access' => 2))) === 'allowed', 'change: an editor who may publish changes the access level');
    $check($batch($publisher, 'language', $change(70, array('language' => 'en-GB'))) === 'allowed', 'change: and the language');
    $check($refused($batch($author, 'access', $change(70, array('access' => 2))), 'need approval'), 'change: the access level of a visible article is not changed without the right to edit its state');
    $check($refused($batch($author, 'language', $change(70, array('language' => 'en-GB'))), 'need approval'), 'change: nor its language');
    $check($refused($batch($author, 'move', $change(70, array('catid' => 9))), 'need approval'), 'change: nor its category, for a model that announces a batch move');
    $check($batch($autoApprove, 'access', $change(70, array('access' => 2))) === 'allowed', 'change: allowed with the right to auto approve changes');
    $check($batch($author, 'access', $change(72, array('access' => 2))) === 'allowed', 'change: no approval is needed for a draft');
    FlexicontentModelItem::$versioning = 0;
    $check($batch($author, 'access', $change(70, array('access' => 2))) === 'allowed', 'change: nor with versioning off');
    FlexicontentModelItem::$versioning = 1;
    $check($refused($batch($ownOnly, 'access', $change(71, array('access' => 2))), 'FLEXI_NO_ACCESS_EDIT'), 'change: an article of another author is refused for an edit-own user');
    flexicontent_db::$limits = array(32 => 1); $db->authored = array(32 => 5); $db->log = array();
    $check($batch($publisher, 'access', $change(70, array('access' => 2))) === 'allowed' && $db->log === array(), 'change: an existing article is not counted for the quota');
    flexicontent_db::$limits = array(); $db->authored = array();

    // 4. Exempt users, other records, FLEXIcontent's own saves
    flexicontent_html::$types = array(3 => 1);
    flexicontent_db::$limits = array(33 => 1, 31 => 1); $db->authored = array(33 => 9, 31 => 9); $db->log = array();
    $check($batch($super, 'copy', $copyOf(71)) === 'allowed' && $batch($super, 'access', $change(71, array('access' => 2))) === 'allowed' && $db->log === array(), 'exempt: a Super User is not restricted');
    $check($batch(null, 'copy', $copyOf(71), '#__content', 'cli') === 'allowed' && $db->log === array(), 'exempt: a command line task is not restricted');
    $plugin->helperLoads = 0;
    $check($batch($author, 'copy', $copyOf(70), '#__categories') === 'allowed' && $batch($author, 'copy', $copyOf(70), '#__modules') === 'allowed' && $plugin->helperLoads === 0 && $db->log === array(),
        'exempt: batch tasks of other records are left alone, nothing of FLEXIcontent is loaded for them');
    $app->identity = $author;
    $plugin->onBeforeBatch(new FixtureEvent(array('type' => 'copy')));
    $plugin->onBeforeBatch(new FixtureEvent(array('src' => 'not a record', 'type' => 'copy')));
    $check($plugin->helperLoads === 0, 'exempt: an event without a record is ignored');
    FCModelAdmin::internalContentEvents(true);
    $check($batch($author, 'copy', $copyOf(70)) === 'allowed' && $plugin->helperLoads === 0, 'exempt: a store that FLEXIcontent code makes itself is left alone');
    FCModelAdmin::internalContentEvents(false);
    $check($refused($batch($author, 'copy', $copyOf(70)), 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE') && $plugin->helperLoads === 1, 'exempt: and a batch copy after it is checked again');
    flexicontent_html::$types = array(2 => 0, 3 => 1);
    flexicontent_db::$limits = array(); $db->authored = array(); $db->log = array();

    // 5. The after save event that Joomla fires after a batch copy or move carries no article data
    $app->identity = $publisher; $app->client = 'administrator';
    $app->session->data = array('flexicontent.item.data' => array('attribs' => '{"of":"another article"}', 'metadata' => '{}'));
    FlexicontentModelItem::$stored = array(); FlexicontentModelItems::$synced = array(); flexicontent_items::$writes = 0; $app->session->reads = 0;
    $check($afterBatch(95, 9) === true, 'after batch: the event of a batch copy is handled without an error');
    $check(FlexicontentModelItem::$stored === array(), 'after batch: no item save is made from an event without article data');
    $check(flexicontent_items::$writes === 0 && $app->session->reads === 0, 'after batch: the article parameters that an earlier save left in the session are not written to the record');
    $check(FlexicontentModelItems::$synced === array(array(false, 9)), 'after batch: the temporary item data of the category are updated');
    FlexicontentModelItems::$synced = array();
    $check($plugin->onContentAfterSave('com_content.article', new FixtureTable(array('id' => 70, 'catid' => 12)), false, array()) === true
        && FlexicontentModelItem::$stored === array() && FlexicontentModelItems::$synced === array(array(false, 12)), 'after batch: the same for the event of a batch move');

    // A save with article data is given to the item model as before
    $plugin->onContentAfterSave('com_content.article', new FixtureTable(array('id' => 70, 'catid' => 5)), false, array('id' => 70, 'title' => 'Changed', 'catid' => 5));
    $check(count(FlexicontentModelItem::$stored) === 1 && FlexicontentModelItem::$stored[0]['id'] === 70 && flexicontent_items::$writes === 2, 'after save: a save of the article form is still given to the item model');
    FlexicontentModelItem::$stored = array();
    $plugin->onContentAfterSave('com_content.article', new FixtureTable(array('id' => 96, 'catid' => 5)), true, array('title' => 'New', 'catid' => 5));
    $check(count(FlexicontentModelItem::$stored) === 1 && FlexicontentModelItem::$stored[0]['id'] === 96, 'after save: and so is a new article');

    // 6. Joomla hands the event object to a listener whose only parameter is named "event" and typed as an event
    $parameters = (new \ReflectionMethod($plugin, 'onBeforeBatch'))->getParameters();
    $check(count($parameters) === 1 && $parameters[0]->getName() === 'event' && $parameters[0]->getType() instanceof \ReflectionNamedType && $parameters[0]->getType()->getName() === 'Joomla\Event\EventInterface',
        'listener: onBeforeBatch takes the event object');

    restore_error_handler();
    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
