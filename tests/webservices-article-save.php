<?php
/**
 * Articles saved through Joomla's Web Services (/api/index.php/v1/content/articles). The API application saves with
 * the back-end article model, so the FLEXIcontent plugin gets the same events as for the back-end article form. Its
 * folder (JPATH_BASE) is "api", which has no FLEXIcontent files: the item model and the forms of the back end are used
 * there, the article form is not prepared for display, and the item model asks for no menu. The rules of the article
 * form (creatable type, author quota, edit permission, approval of changes, owner change) then apply to these saves.
 * Runs the real plugin and model methods against doubles and a temporary folder tree; no Joomla, no database.
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
        public static function getComponent($component) { return (object) array('params' => new \Joomla\Registry\Registry(array('comments' => 1))); }
    }
}
namespace Joomla\CMS\Form {
    class Form {
        public static $formPaths = array(), $fieldPaths = array(), $loads = 0;
        private $name;
        public function __construct($name = '', array $options = array()) { $this->name = $name; }
        public static function addFormPath($path) { self::$formPaths[] = $path; }
        public static function addFieldPath($path) { self::$fieldPaths[] = $path; }
        public function getName() { return $this->name; }
        public function load($xml) { ++self::$loads; return true; }
        public function filter($data) { return $data; }
    }
}
namespace Joomla\Filesystem {
    final class Path { public static function clean($path) { return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path); } }
}
namespace Joomla\Utilities {
    final class ArrayHelper { public static function toInteger($a) { return array_map('intval', (array) $a); } }
}
namespace Joomla\Registry {
    final class Registry implements \ArrayAccess {
        private $data = array();
        public function __construct($data = null) {
            if ($data instanceof self) $this->data = $data->data;
            elseif (is_array($data)) $this->data = $data;
            elseif (is_string($data) && $data !== '') $this->data = (array) json_decode($data, true);
        }
        public function get($name, $default = null) { return isset($this->data[$name]) && $this->data[$name] !== '' ? $this->data[$name] : $default; }
        public function set($name, $value) { $this->data[$name] = $value; return $value; }
        public function def($name, $default = '') { if (!isset($this->data[$name])) $this->data[$name] = $default; return $this->data[$name]; }
        public function merge($source) { foreach ($source->data as $name => $value) { if ($value !== null && $value !== '') $this->data[$name] = $value; } return $this; }
        public function loadArray(array $values) { foreach ($values as $name => $value) $this->data[$name] = $value; return $this; }
        public function toArray() { return $this->data; }
        public function toString() { return json_encode($this->data); }
        public function offsetExists($offset): bool { return isset($this->data[$offset]); }
        public function offsetGet($offset): mixed { return isset($this->data[$offset]) ? $this->data[$offset] : null; }
        public function offsetSet($offset, $value): void { $this->data[$offset] = $value; }
        public function offsetUnset($offset): void { unset($this->data[$offset]); }
    }
}
namespace {
    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('FLEXI_J40GE', true);

    // The folders of a site as Joomla's API application defines them: its own folder is "api"
    $repo = dirname(__DIR__);
    $site = sys_get_temp_dir() . '/fc-webservices-' . bin2hex(random_bytes(6));
    define('JPATH_SITE', $site);
    define('JPATH_ADMINISTRATOR', $site . '/administrator');
    define('JPATH_BASE', $site . '/api');

    // A notice or warning of the checked code fails the run (a deprecation notice of a newer PHP version is only shown)
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
    // How often code (not a comment) names a constant
    function constantUses($source, $constant) {
        $uses = 0;
        foreach (token_get_all($source) as $token) { if (is_array($token) && $token[0] === T_STRING && $token[1] === $constant) ++$uses; }
        return $uses;
    }

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };

    // The files that the plugin loads. Each one records that it was loaded; the folder that Joomla's installer creates for
    // the component under "api" is empty
    $loaded = array();
    $files  = array(
        'administrator/components/com_flexicontent/tables/flexicontent_items.php',
        'administrator/components/com_flexicontent/defineconstants.php',
        'administrator/components/com_flexicontent/models/item.php',
        'administrator/components/com_flexicontent/models/items.php',
        'components/com_flexicontent/classes/flexicontent.fields.php',
        'components/com_flexicontent/classes/flexicontent.helper.php',
        'components/com_flexicontent/classes/flexicontent.categories.php',
        'components/com_flexicontent/helpers/permission.php',
    );
    $write = static function ($file, $content) use ($site) {
        if (!is_dir(dirname($site . '/' . $file))) mkdir(dirname($site . '/' . $file), 0777, true);
        file_put_contents($site . '/' . $file, $content);
    };
    foreach ($files as $file) $write($file, '<?php $GLOBALS["loaded"][] = ' . var_export($file, true) . ';');
    $write('administrator/components/com_flexicontent/models/forms/item.xml', '<form><fields name="attribs"><fieldset name="params-basic"><field name="ilayout" type="text" /></fieldset></fields></form>');
    $write('administrator/components/com_content/forms/article.xml', '<form><fields name="attribs"><fieldset name="basic"><field name="show_title" type="text" /></fieldset></fields></form>');
    mkdir($site . '/api/components/com_flexicontent', 0777, true);
    register_shutdown_function(static function () use ($site) {
        $remove = static function ($dir) use (&$remove) {
            foreach (array_diff((array) @scandir($dir), array('.', '..')) as $entry) { is_dir($dir . '/' . $entry) ? $remove($dir . '/' . $entry) : @unlink($dir . '/' . $entry); }
            @rmdir($dir);
        };
        $remove($site);
    });

    // Database double: the statements of the quota check (count, named lock)
    final class FixtureDb {
        public $authored = array(), $locks = array(), $log = array();
        private $sql = '';
        public function quote($value) { return "'" . $value . "'"; }
        public function getPrefix() { return 'jos_'; }
        public function setQuery($query) { $this->sql = (string) $query; $this->log[] = $this->sql; return $this; }
        public function loadResult() {
            if ($this->sql === 'SELECT DATABASE()') return 'fixture';
            if (preg_match("/^SELECT GET_LOCK\('(\w+)', 5\)$/", $this->sql, $m)) { $this->locks[$m[1]] = 1; return 1; }
            if (preg_match("/^SELECT RELEASE_LOCK\('(\w+)'\)$/", $this->sql, $m)) { unset($this->locks[$m[1]]); return 1; }
            if (preg_match('/^SELECT COUNT\(id\) FROM #__content WHERE created_by = (\d+)$/', $this->sql, $m)) {
                return isset($this->authored[$m[1]]) ? $this->authored[$m[1]] : 0;
            }
            throw new \RuntimeException('Unexpected statement: ' . $this->sql);
        }
    }

    // Request, session, user and application doubles. As in Joomla, the API application has no menu: asking for it throws
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
        public $client = 'api', $identity, $input, $session, $languageLoads = array(), $menuRequests = 0;
        public function __construct() { $this->input = new FixtureInput; $this->input->post = new FixtureInput; $this->session = new FixtureSession; }
        public function isClient($name) { return $this->client === $name; }
        public function getIdentity() { return $this->identity; }
        public function getSession() { return $this->session; }
        public function getDocument() { return null; }
        public function getLanguage() { $app = $this; return new class($app) { private $app; public function __construct($app) { $this->app = $app; } public function load($extension, $path) { $this->app->languageLoads[] = $extension . '@' . $path; return true; } }; }
        public function getMenu() {
            ++$this->menuRequests;
            if ($this->client === 'api') throw new \InvalidArgumentException('JLIB_APPLICATION_ERROR_MENU_LOAD api', 500);
            return new class { public function getActive() { return null; } };
        }
    }

    // The article record that Joomla is about to store, and the FLEXIcontent record of what is stored now
    #[\AllowDynamicProperties]
    final class FixtureArticle {
        public $id = 0, $created_by = 0, $catid = 5, $state = 1, $error = '';
        public function __construct(array $values) { foreach ($values as $k => $v) { if (!is_array($v)) $this->$k = $v; } }
        public function setError($error) { $this->error = $error; }
        public function getError() { return $this->error; }
    }
    #[\AllowDynamicProperties]
    final class flexicontent_items {
        public static $rows = array();
        public $id, $created_by, $catid, $state, $type_id, $attribs = '', $metadata = '';
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
    final class flexicontent_tmpl { public static function mergeLayoutParams(&$record, &$data, $options) { return array(); } }
    final class flexicontent_security {
        // The first thing that the article form does for display, after it has loaded the helpers
        public static function getOrCreateTemporaryItemId($app, $context) { throw new \LogicException('the article form is prepared for display'); }
    }
    final class FlexicontentHelperPerm { public static function checkTypeAccess($type_id, $rule) { return true; } }
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

    $pluginSource = file_get_contents($repo . '/plugins/system/flexisystem/flexisystem.php');
    $baseSource   = file_get_contents($repo . '/admin/models/base/base.php');
    $itemSource   = file_get_contents($repo . '/admin/models/parentclassitem.php');

    // The real base model methods that choose the folder of the forms, and the real merge of the article parameters
    $baseMethods = '';
    foreach (array('internalContentEvents', 'clientBasePath', 'getForm', 'mergeAttributes', 'maintainDbData') as $name) {
        if (strpos($baseSource, 'function ' . $name . '(') !== false) $baseMethods .= ' public ' . ($name === 'internalContentEvents' || $name === 'clientBasePath' ? 'static ' : '') . methodSource($baseSource, $name);
    }
    $check(strpos($baseSource, 'function clientBasePath(') !== false, 'model: the base model tells the folder of the application that it works for');
    if (strpos($baseSource, 'function clientBasePath(') === false) { fwrite(STDERR, "the base model has no clientBasePath method\n"); exit(1); }
    eval('abstract class FCModelAdmin { private static $internalContentEvents = false;
        public $events_context = "com_flexicontent.item";
        public function getName() { return "item"; }
        public function loadForm($name, $source, $options) { return new \Joomla\CMS\Form\Form($name); }
        protected function handlePartialForm($form, &$data) {}
        protected function _canonicalData($form, &$data) {}
        ' . $baseMethods . ' }');

    // The real permission methods and the real parameter loading of the item model; its save is recorded only.
    // The layout choice of the site (decideLayout) is a method of the site model only, as in FLEXIcontent
    eval('final class FlexicontentModelItem extends FCModelAdmin {
        public static $stored = array(), $versioning = 1;
        public $type_alias = "com_content.article", $_record = null, $_id = 0, $_cid = 0, $_db = null, $layout = null;
        public $menu_matches = null, $isForm = false, $mergeMenuParams = true, $_cparams = null, $_typeid = 0;
        public function getItemType() { return (object) array("id" => 2); }
        protected function _loadRecord($pk = null, $no_cache = false, $force_version = 0) { return (bool) $this->_record; }
        public function getTypeslist($type_ids = false, $check_perms = false, $published = true) { return flexicontent_html::getTypesList($type_ids, $check_perms, $published); }
        public function setId($id, ...$more) { $this->_id = (int) $id; }
        public function getComponentTypeParams() { return new \Joomla\Registry\Registry(array("use_versioning" => self::$versioning)); }
        public function getTypeparams() { return \'{"ilayout":"grid","robots":"index, follow"}\'; }
        public function setItemLayout($name = null) { $this->layout = $name; }
        public function getLayoutparams($force = false) { return ""; }
        public function getItemAccess() { return new \Joomla\Registry\Registry(array("access-edit" => 1)); }
        public function mergeJTagsAssignments(...$a) {}
        public function getCustomFieldsValues($item_id = 0, $version = 0) { return array(); }
        public function store($data) { self::$stored[] = $data; return true; }
        public ' . methodSource($itemSource, 'canEdit') . '
        public ' . methodSource($itemSource, 'canEditState') . '
        public ' . methodSource($itemSource, 'canCreateType') . '
        public ' . methodSource($itemSource, 'getItem') . '
        public ' . methodSource($itemSource, '_loadItemParams') . '
        private ' . methodSource($itemSource, '_new_JRegistry') . ' }');

    // The real plugin methods, with the plugin's surrounding jobs replaced by no-ops
    $methods = '';
    foreach (array('onContentPrepareForm', 'onContentBeforeSave', 'onContentAfterSave', '_isInternalEvent', '_articleSaveRefusal', '_authorQuotaRefusal', '_releaseAuthorQuotaLock', '_storedCustomFieldValues', '_loadFcHelpersAndLanguage') as $name) {
        if (strpos($pluginSource, 'function ' . $name . '(') !== false) $methods .= ' public ' . methodSource($pluginSource, $name);
    }
    eval('use Joomla\CMS\Factory; use Joomla\CMS\Language\Text; use Joomla\CMS\Component\ComponentHelper; use Joomla\Utilities\ArrayHelper; use Joomla\Registry\Registry; use Joomla\CMS\Form\Form;
        final class WebServicesPlugin {
            public $_subject = null;
            private $_author_quota_lock = null;
            private function _rememberStoredFileLinks(...$a) {} private function _updateFileUsage_FcFileBtn_DownloadLinks(...$a) {}
            public function onExtensionBeforeSave(...$a) {} public function onExtensionAfterSave(...$a) {}
            ' . $methods . ' }');
    $plugin = new WebServicesPlugin;

    $itemModelOf = static function ($folder) { return $folder . '/components/com_flexicontent/models/item.php'; };

    // 1. The helpers and the item model: the API application has none of its own
    $check(!is_file($itemModelOf(JPATH_BASE)) && is_dir(JPATH_BASE . '/components/com_flexicontent'), 'fixture: the "api" folder has an empty component folder and no item model');
    $app->client = 'api'; $loaded = array();
    $error = null;
    try { $plugin->_loadFcHelpersAndLanguage(); } catch (\Throwable $e) { $error = get_class($e) . ': ' . $e->getMessage(); }
    $check($error === null, 'helpers: they load for a Web Services request' . ($error ? ' (' . $error . ')' : ''));
    $check(in_array('administrator/components/com_flexicontent/models/item.php', $loaded, true), 'helpers: the item model of the back end is loaded for it');
    $check(count($loaded) === 7 && !in_array('administrator/components/com_flexicontent/models/items.php', $loaded, true), 'helpers: with the table, the constants and the four helper classes');
    $check($app->languageLoads === array('com_flexicontent@' . JPATH_ADMINISTRATOR), 'helpers: and the language file of the back end');

    // The site and the back end keep loading the item model of their own folder (JPATH_BASE)
    $write('api/components/com_flexicontent/models/item.php', '<?php $GLOBALS["loaded"][] = "the item model of the application folder";');
    $app->client = 'administrator'; $loaded = array();
    $plugin->_loadFcHelpersAndLanguage();
    $check($loaded === array('the item model of the application folder'), 'helpers: the back end loads the item model of its own folder');
    $app->client = 'site'; $loaded = array();
    $plugin->_loadFcHelpersAndLanguage();
    $check($loaded === array(), 'helpers: and so does the site (the model of the application folder is loaded already)');
    $check(constantUses($pluginSource, 'JPATH_BASE') === 1 && strpos(methodSource($pluginSource, '_loadFcHelpersAndLanguage'), 'JPATH_BASE') !== false,
        'helpers: no other code of the plugin builds a path from the application folder');

    // 2. The article form: prepared for display on the site and in the back end only
    $prepare = static function ($client, $formName = 'com_content.article') use ($app, $plugin) {
        $app->client = $client; $app->input->data = array('option' => 'com_content');
        \Joomla\CMS\Form\Form::$loads = 0;
        try {
            $result = $plugin->onContentPrepareForm(new \Joomla\CMS\Form\Form($formName), array());
        } catch (\LogicException $e) {
            return $e->getMessage();
        }
        return $result === true && \Joomla\CMS\Form\Form::$loads === 0 && !isset($app->input->data['unique_tmp_itemid']) ? 'left alone' : 'unexpected';
    };
    $check($prepare('api') === 'left alone', 'form: a Web Services request gets the article form of Joomla as it is');
    $check($prepare('cli') === 'left alone', 'form: so does every other application without a page');
    $check($prepare('administrator') === 'the article form is prepared for display', 'form: the back end still gets the fields of the item');
    $check($prepare('site') === 'the article form is prepared for display', 'form: and so does the site');
    $check($prepare('administrator', 'com_users.user') === 'left alone', 'form: other forms are not touched');
    $app->input->data = array();

    // 3. The forms of the models
    $app->client = 'api';
    $check(FCModelAdmin::clientBasePath() === JPATH_ADMINISTRATOR, 'model: Web Services work with the back-end folder');
    foreach (array('administrator', 'site', 'cli') as $client) { $app->client = $client; $check(FCModelAdmin::clientBasePath() === JPATH_BASE, 'model: the ' . $client . ' application works with its own folder'); }
    $check(constantUses($baseSource, 'JPATH_BASE') === 1 && constantUses($itemSource, 'JPATH_BASE') === 0, 'model: no other code of the base and item models builds a path from the application folder');
    $app->client = 'api'; \Joomla\CMS\Form\Form::$formPaths = \Joomla\CMS\Form\Form::$fieldPaths = array();
    $model = new FlexicontentModelItem;
    $form  = $model->getForm();
    $check($form instanceof \Joomla\CMS\Form\Form && \Joomla\CMS\Form\Form::$formPaths === array(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent/models/forms')
        && \Joomla\CMS\Form\Form::$fieldPaths === array(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent/models/fields'), 'model: the item form and its field types are taken from the back end for Web Services');

    // The merge of the article parameters reads the form files of FLEXIcontent and of com_content
    $merge = static function ($client) use ($app) {
        $app->client = $client; \Joomla\CMS\Form\Form::$loads = 0;
        $model  = new FlexicontentModelItem;
        $record = (object) array('attribs' => '{"ilayout":"news","show_title":"0"}', 'metadata' => '{"robots":"index"}');
        $data   = array('attribs' => array('show_title' => '1'), 'metadata' => array('robots' => 'noindex'));
        try {
            $model->mergeAttributes($record, $data, array('attribs', 'metadata'), array('model_names' => array('com_flexicontent' => 'item', 'com_content' => 'article')));
        } catch (\Exception $e) {
            return 'refused: ' . $e->getMessage();
        }
        return \Joomla\CMS\Form\Form::$loads === 2 ? array(json_decode($record->attribs, true), json_decode($record->metadata, true)) : 'unexpected';
    };
    $check($merge('api') === array(array('ilayout' => 'news', 'show_title' => '1'), array('robots' => 'noindex')), 'model: for Web Services the article parameters are merged with the form files of the back end');
    $outcome = $merge('administrator');
    $check(is_string($outcome) && strpos($outcome, 'refused: ') === 0 && strpos($outcome, str_replace('/', DS, JPATH_BASE)) !== false && strpos($outcome, 'file not found') !== false,
        'model: the other applications read the form files of their own folder');

    // 4. The parameters of an item: no menu and no layout choice of the site for Web Services
    $params = static function ($client) use ($app) {
        $app->client = $client; $app->menuRequests = 0;
        $model = new FlexicontentModelItem;
        $model->_id = 70;
        $model->_record = (object) array('id' => 70, 'title' => 'Stored', 'attribs' => '{"show_title":"1"}', 'metadata' => '{}', 'parameters' => null);
        $model->_loadItemParams();
        return $model;
    };
    $error = null;
    try { $model = $params('api'); } catch (\Throwable $e) { $error = get_class($e) . ': ' . $e->getMessage(); }
    $check($error === null, 'params: the parameters of an item load for a Web Services request' . ($error ? ' (' . $error . ')' : ''));
    $check($error === null && $app->menuRequests === 0, 'params: without asking for a menu, which the API application does not have');
    $check($error === null && $model->layout === 'grid' && $model->menu_matches === false, 'params: with the layout of the item or its type, as in the back end');
    $check($error === null && $model->_record->parameters instanceof \Joomla\Registry\Registry && $model->_record->parameters->get('show_title') === '1'
        && $model->_record->parameters->get('comments') === 1 && $model->_record->metadata->get('robots') === 'index, follow', 'params: and with the component, type and item parameters merged');
    $model = $params('administrator');
    $check($app->menuRequests === 1 && $model->layout === 'grid', 'params: the back end asks for its menu as before');

    // Loading an item: the view access check is a method of the site model, the back-end model that Web Services use has none
    $load = static function ($client) use ($app) {
        $app->client = $client;
        $model = new FlexicontentModelItem;
        $model->_id = 70;
        $model->isForm = true;  // the layout is the one of the item, the site does not choose one for a form
        $model->_record = (object) array('id' => 70, 'title' => 'Stored', 'attribs' => '{}', 'metadata' => '{}', 'parameters' => null);
        try {
            $item = $model->getItem();
        } catch (\Throwable $e) {
            return get_class($e) . ': ' . $e->getMessage();
        }
        return is_object($item) && $item->id === 70 && $item->type_id === 2 ? 'loaded' : 'unexpected';
    };
    $check($load('api') === 'loaded', 'item: an item loads for a Web Services request, without the view access check of the site');
    $check($load('administrator') === 'loaded', 'item: as it does in the back end');
    $check(strpos($load('site'), '_check_viewing_access') !== false, 'item: the site still makes its view access check');
    $check(strpos(methodSource($itemSource, '_loadRecord'), "(\$app->isClient('administrator') || \$app->isClient('api')) && \$view !== 'itemcompare'") !== false,
        'item: for Web Services the record is read like the back end reads it');

    // 5. The rules of the article form apply to a save through Web Services
    flexicontent_items::$rows = array(
        70 => array('created_by' => 31, 'catid' => 5, 'state' => 1, 'type_id' => 2, 'attribs' => '{"ilayout":"news"}', 'metadata' => '{}'),
        71 => array('created_by' => 50, 'catid' => 5, 'state' => 1, 'type_id' => 2, 'attribs' => '{"ilayout":"news"}', 'metadata' => '{}'),
        72 => array('created_by' => 31, 'catid' => 5, 'state' => -4, 'type_id' => 2, 'attribs' => '', 'metadata' => ''),
    );
    // Types: 2 can be created by any author, 3 only with the create right of the type
    flexicontent_html::$types = array(2 => 0, 3 => 1);

    $author      = new FixtureUser(31, array('core.edit.own@*'));
    $ownState    = new FixtureUser(31, array('core.edit.own@*', 'core.edit.state.own@*'));
    $publisher   = new FixtureUser(32, array('core.edit@*', 'core.edit.state@*'));
    $ownerEditor = new FixtureUser(32, array('core.edit@*', 'core.edit.state@*', 'flexicontent.editcreator@com_flexicontent'));
    $super       = new FixtureUser(33); $super->super = true;

    // One save as the API controller makes it: "stored" when the event allows it and has prepared the article parameters,
    // "refused: <reason>" when it returns false with the reason on the record and has prepared nothing
    $save = static function ($user, array $article) use ($app, $plugin) {
        $app->client = 'api'; $app->identity = $user; $app->session->sets = 0; $app->session->data = array();
        $app->input->data = array('option' => 'com_content', 'jform' => $article);
        $row = new FixtureArticle($article);
        try {
            $result = $plugin->onContentBeforeSave('com_content.article', $row, empty($article['id']), $article);
        } catch (\Throwable $e) {
            return 'failed: ' . get_class($e) . ': ' . $e->getMessage();
        }
        if ($result === true && $row->error === '') return $app->session->sets === 1 ? 'stored' : 'not handled';
        return $result === false && $row->error !== '' && $app->session->sets === 0 ? 'refused: ' . $row->error : 'unexpected';
    };
    $refused = static function ($outcome, $reason) { return strpos($outcome, 'refused: ') === 0 && strpos($outcome, $reason) !== false; };
    $edit = static function ($id, array $changes = array()) { return $changes + array('id' => $id, 'attribs' => array('show_title' => '1')) + array_diff_key(flexicontent_items::$rows[$id], array('attribs' => 1, 'metadata' => 1)); };

    // A Super User token is not restricted, and the save completes
    $check($save($super, array('title' => 'New', 'catid' => 5)) === 'stored', 'super user: a new article is saved through Web Services');
    $check($save($super, $edit(71, array('title' => 'Changed', 'created_by' => 33))) === 'stored', 'super user: an article of another author is changed, with its author');
    $prepared = $app->session->get('flexicontent.item.data');
    $check(is_array($prepared) && json_decode($prepared['attribs'], true) === array('ilayout' => 'news', 'show_title' => '1'), 'super user: the FLEXIcontent parameters of the article are kept with the ones that were sent');
    flexicontent_html::$types = array(3 => 1);
    flexicontent_db::$limits = array(33 => 1); $db->authored = array(33 => 9); $db->log = array();
    $check($save($super, array('title' => 'New', 'catid' => 5)) === 'stored' && $db->log === array(), 'super user: no type right and no quota is asked of a Super User');
    flexicontent_db::$limits = array(); $db->authored = array();

    // Other tokens follow the rules of the article form
    $check($refused($save($author, array('title' => 'New', 'catid' => 5)), 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE'), 'new: refused when the user may create no type');
    flexicontent_html::$types = array(2 => 0, 3 => 1);
    $check($save($author, array('title' => 'New', 'catid' => 5)) === 'stored', 'new: an author who may create a type saves a new article');
    $check($refused($save($publisher, array('title' => 'New', 'catid' => 5, 'created_by' => 50)), 'author'), 'new: another user as author is refused');
    $check($save($ownerEditor, array('title' => 'New', 'catid' => 5, 'created_by' => 50)) === 'stored', 'new: and allowed with the right to edit the creator');
    flexicontent_db::$limits = array(31 => 2); $db->authored = array(31 => 2);
    $check($refused($save($author, array('title' => 'New', 'catid' => 5)), 'FLEXI_ALERTNOTAUTH_CREATE_MORE 2') && $db->locks === array(), 'quota: an author at the limit cannot add an article');
    $db->authored = array(31 => 1);
    $check($save($author, array('title' => 'New', 'catid' => 5)) === 'stored' && count($db->locks) === 1, 'quota: an author below the limit can, the check holds its lock until the article is stored');
    $plugin->onContentAfterSave('com_content.article', new FixtureArticle(array('id' => 90)), true, array('title' => 'New', 'catid' => 5));
    $check($db->locks === array(), 'quota: the lock is released after the article is stored');
    flexicontent_db::$limits = array(); $db->authored = array();

    $check($save($publisher, $edit(70, array('title' => 'Changed'))) === 'stored', 'edit: an editor who may publish changes an article');
    $check($refused($save($author, $edit(71, array('title' => 'Changed'))), 'FLEXI_NO_ACCESS_EDIT'), 'edit: an article of another author is refused for an edit-own user');
    $check($refused($save($author, $edit(70, array('title' => 'Changed'))), 'need approval'), 'approval: a visible article is not changed directly without the right to edit its state');
    $check($save($ownState, $edit(70, array('title' => 'Changed'))) === 'stored', 'approval: not needed with the right to edit the state of own articles');
    $check($save($author, $edit(72, array('title' => 'Changed'))) === 'stored', 'approval: not needed for a draft');
    $check($refused($save($publisher, $edit(70, array('created_by' => 32))), 'author'), 'owner: changing the author of an article is refused');
    $check($save($ownerEditor, $edit(70, array('created_by' => 32))) === 'stored', 'owner: allowed with the right to edit the creator');

    // 6. After the save the article is given to the item model, as for the article form
    $app->client = 'api'; $app->identity = $publisher; $app->input->post->data = array();
    FlexicontentModelItem::$stored = array(); FlexicontentModelItems::$synced = array();
    $plugin->onContentAfterSave('com_content.article', new FixtureArticle(array('id' => 70, 'catid' => 5)), false, array('id' => 70, 'title' => 'Changed', 'catid' => 5));
    $check(count(FlexicontentModelItem::$stored) === 1 && FlexicontentModelItem::$stored[0]['id'] === 70 && FlexicontentModelItem::$stored[0]['type_id'] === 2,
        'after save: the changed article is saved as an item, with its type');
    $check(FlexicontentModelItems::$synced === array(array(false, 5)), 'after save: and the temporary item data are updated');
    $check(FCModelAdmin::internalContentEvents() === false, 'after save: the mark of the item save ends with it');
    FlexicontentModelItem::$stored = array();
    $plugin->onContentAfterSave('com_content.article', new FixtureArticle(array('id' => 96, 'catid' => 5)), true, array('title' => 'New', 'catid' => 5));
    $check(count(FlexicontentModelItem::$stored) === 1 && FlexicontentModelItem::$stored[0]['id'] === 96 && FlexicontentModelItem::$stored[0]['type_id'] === 2, 'after save: a new article gets the type of the article form');

    restore_error_handler();
    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
