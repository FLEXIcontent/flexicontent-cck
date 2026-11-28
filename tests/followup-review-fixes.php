<?php
/**
 * Follow-up review fixes: import folders, content author check, file ids posted to file fields, values an item
 * supplies for module parameters, LESS configuration values, placeholder values inside administrator SQL, and the
 * places where a one-line check or encoding was added. Runs the real methods with doubles; no Joomla, no database.
 */
namespace Joomla\CMS {
    class Factory {
        public static $app, $db;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($service) { return Factory::$db; } }; }
    }
}
namespace Joomla\Database {
    interface DatabaseInterface {}
}
namespace Joomla\Registry {
    class Registry {
        private $data;
        public function __construct($data = array()) { $this->data = (array) $data; }
        public function get($name, $default = null) { return isset($this->data[$name]) ? $this->data[$name] : $default; }
    }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_SITE', dirname(__DIR__));
    $root = JPATH_SITE;

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
    function source($path) { return str_replace("\r\n", "\n", file_get_contents(JPATH_SITE . '/' . $path)); }

    final class UserDouble {
        public $id, $guest, $rights, $categories, $levels;
        public function __construct($id, array $rights = array(), array $categories = array(), array $levels = array(1)) {
            $this->id = $id; $this->guest = !$id; $this->rights = $rights; $this->categories = $categories; $this->levels = $levels;
        }
        public function authorise($action, $asset = null) { return in_array($action . '@' . $asset, $this->rights, true); }
        public function getAuthorisedCategories($extension, $action) { return isset($this->categories[$action]) ? $this->categories[$action] : array(); }
        public function getAuthorisedViewLevels() { return $this->levels; }
    }
    // Answers the three queries of canAssignFileToField() from arrays and records every statement
    final class DbDouble implements \Joomla\Database\DatabaseInterface {
        public $relations = array(), $files = array(), $items = array(), $log = array();
        private $sql;
        public function quote($value) { return "'" . str_replace("'", "''", (string) $value) . "'"; }
        public function escape($value) { return addslashes((string) $value); }
        public function setQuery($sql, $offset = 0, $limit = 0) { $this->sql = (string) $sql; $this->log[] = $this->sql; return $this; }
        public function loadResult() {
            if (preg_match("/^SELECT 1 FROM #__flexicontent_fields_item_relations WHERE field_id = (\d+) AND item_id = (\d+) AND value = '(\d+)'$/", $this->sql, $m)) {
                foreach ($this->relations as $r) if ($r[0] == $m[1] && $r[1] == $m[2] && $r[2] == $m[3]) return 1;
                return null;
            }
            if (preg_match('/^SELECT uploaded_by FROM #__flexicontent_files WHERE id = (\d+)$/', $this->sql, $m)) {
                return isset($this->files[$m[1]]) ? $this->files[$m[1]] : null;
            }
            if (preg_match("/rel\.field_id = (\d+) AND rel\.value = '(\d+)' AND c\.access IN \(([\d,]+)\) AND \(c\.state IN \(1, -5\) OR \(c\.created_by = (\d+) AND c\.created_by > 0\)\)$/", $this->sql, $m)) {
                foreach ($this->relations as $r) {
                    if ($r[0] != $m[1] || $r[2] != $m[2] || !isset($this->items[$r[1]])) continue;
                    $item = $this->items[$r[1]];
                    if (in_array($item['access'], explode(',', $m[3])) && (in_array($item['state'], array(1, -5)) || ($item['created_by'] == $m[4] && $m[4] > 0))) return 1;
                }
                return null;
            }
            throw new \RuntimeException('Unexpected query: ' . $this->sql);
        }
    }
    \Joomla\CMS\Factory::$db = $db = new DbDouble;
    \Joomla\CMS\Factory::$app = new class { public $identity; public function getIdentity() { return $this->identity; } };

    require $root . '/site/classes/helpers/security.php';

    // 1. Import folders: site-relative, existing, inside the site
    $check(flexicontent_security::siteRelativeFolder('') === '', 'folder: empty means the site root');
    $check(flexicontent_security::siteRelativeFolder('/site/classes/') === 'site/classes', 'folder: separators around the path are dropped');
    $check(flexicontent_security::siteRelativeFolder('site\\classes\\helpers') === 'site/classes/helpers', 'folder: backslashes are accepted');
    foreach (array('..', '../outside', 'site/../..', 'site/./classes', 'site/../../etc', 'C:/Windows', 'file:///etc', "site\0", 'no-such-folder-here', 'README.md', array('site')) as $folder) {
        $check(flexicontent_security::siteRelativeFolder($folder) === false, 'folder is refused: ' . json_encode($folder));
    }
    $import = source('admin/controllers/import.php');
    $check(substr_count($import, 'flexicontent_security::siteRelativeFolder($jinput->get(') === 2 && strpos($import, "\$jinput->get('media_folder', '', 'string');") === false,
        'import: both folders of the request go through the check');

    // 2. Content author
    $check(!flexicontent_security::isContentAuthor(null) && !flexicontent_security::isContentAuthor(new UserDouble(0, array('core.create@com_flexicontent'))), 'author: a guest is not one');
    $check(!flexicontent_security::isContentAuthor(new UserDouble(9)), 'author: a login alone is not enough');
    $check(flexicontent_security::isContentAuthor(new UserDouble(9, array('core.edit.own@com_flexicontent'))), 'author: a component level right counts');
    $check(flexicontent_security::isContentAuthor(new UserDouble(9, array(), array('core.create' => array(4)))), 'author: a right in one category counts');
    foreach (array('admin/views/itemelement/view.html.php', 'admin/views/fccategoryelement/view.html.php') as $view) {
        $check(strpos(source($view), "if (!\$isAdmin && !flexicontent_security::isContentAuthor(\$user))") !== false, 'picker requires a content author on the site: ' . $view);
    }

    // 3. A posted file id
    $db->files = array(50 => 31, 51 => 77, 52 => 0);
    $db->items = array(200 => array('access' => 1, 'state' => 1, 'created_by' => 77), 201 => array('access' => 3, 'state' => 1, 'created_by' => 77),
        202 => array('access' => 1, 'state' => 0, 'created_by' => 77), 203 => array('access' => 1, 'state' => 0, 'created_by' => 31));
    $author  = new UserDouble(31);
    $manager = new UserDouble(34, array('flexicontent.viewallfiles@com_flexicontent'));
    $assign  = function ($file_id, $item_id, $user, array $relations = array(), $field_id = 7) use ($db) { $db->relations = $relations; return flexicontent_security::canAssignFileToField($field_id, $file_id, $item_id, $user); };
    $check($assign(50, 0, $author), 'file id: own upload is accepted for a new item');
    $check(!$assign(51, 0, $author), 'file id: a file of another uploader is refused');
    $check(!$assign(51, 100, $author), 'file id: also for an existing item that does not have it');
    $check($assign(51, 100, $author, array(array(7, 100, 51))), 'file id: a file that is already a value of the field in the item is kept');
    $check(!$assign(51, 100, $author, array(array(8, 100, 51))), 'file id: a value of another field of the item does not count');
    $check($assign(51, 0, $manager), 'file id: users who may view all files can assign any file');
    $check(!$assign(999, 0, $author) && !$assign(0, 0, $manager) && !$assign('51 OR 1=1', 0, $author), 'file id: missing files and ids that are not numbers are refused');
    $check($assign(51, 0, $author, array(array(7, 200, 51))), 'file id: a file the field offers in a published item the user may view is accepted');
    $check(!$assign(51, 0, $author, array(array(7, 201, 51))), 'file id: not when that item has an access level the user lacks');
    $check(!$assign(51, 0, $author, array(array(7, 202, 51))), 'file id: not when that item is unpublished and of another user');
    $check($assign(51, 0, $author, array(array(7, 203, 51))), 'file id: an unpublished item of the user counts');
    $check($assign(52, 0, new UserDouble(0)) === true && !$assign(51, 0, new UserDouble(0)), 'file id: a guest may use a file without an uploader, as in the file picker');
    $check(!$assign(51, 0, null), 'file id: no user, no file');
    foreach (array('plugins/flexicontent_fields/file/file.php', 'plugins/flexicontent_fields/mediafile/mediafile.php') as $plugin) {
        $check(substr_count(source($plugin), 'flexicontent_security::canAssignFileToField($field->id, ') === 3, 'all three ways to post a file id are checked: ' . $plugin);
    }

    // 4. Load module field
    $loadmodule = source('plugins/flexicontent_fields/fcloadmodule/fcloadmodule.php');
    preg_match('/protected static \$unsafe_mod_params = [^;]+;/', $loadmodule, $unsafe);
    eval('final class LoadModuleFixture { public static $field_types = array("fcloadmodule"); ' . $unsafe[0]
        . ' public static ' . methodSource($loadmodule, 'isItemModuleParam') . ' public static ' . methodSource($loadmodule, 'cleanItemModuleParamValue')
        . ' public ' . methodSource($loadmodule, 'onBeforeSaveField') . ' }');
    foreach (array('php_rule', 'ENABLE_PHP_RULE', 'dynamic_catids', 'layout', 'Layout', 'a b', 'a[b]', '', null, array('x')) as $name) {
        $check(!LoadModuleFixture::isItemModuleParam($name), 'module parameter is never set per item: ' . json_encode($name));
    }
    $check(LoadModuleFixture::isItemModuleParam('more_title') && LoadModuleFixture::isItemModuleParam('catids'), 'ordinary module parameters can be set per item');
    $check(LoadModuleFixture::cleanItemModuleParamValue('Plain text 12, ok') === 'Plain text 12, ok', 'module value: plain text is unchanged');
    $clean = LoadModuleFixture::cleanItemModuleParamValue("<b>x</b>\" onload=\"a`b\\c\r\nd'e</script>");
    $check(is_string($clean) && !preg_match('/[<>"`\\\\\'\r\n]/', $clean) && strpos($clean, 'onload=a') !== false, 'module value: markup, quotes, backslashes and line breaks are removed');
    $check(LoadModuleFixture::cleanItemModuleParamValue(array('1) OR (1')) === null && LoadModuleFixture::cleanItemModuleParamValue(null) === null, 'module value: a list is not a value');
    $check(mb_strlen(LoadModuleFixture::cleanItemModuleParamValue(str_repeat('é', 3000)), 'UTF-8') === 1000, 'module value: the length is limited');
    $field = (object) array('field_type' => 'fcloadmodule', 'parameters' => new \Joomla\Registry\Registry(array('mod_params' => "Title !! more_title %% Rule !! php_rule %% On !! enable_php_rule\n%% Cats!!catids %% Bad !! a b")));
    $post = array(array('more_title' => '<i>More</i> "news"', 'php_rule' => 'return true;', 'enable_php_rule' => '1', 'catids' => array('1) OR (1'), 'other' => 'x', 'a b' => 'y'));
    $file = null; $item = new stdClass;
    (new LoadModuleFixture)->onBeforeSaveField($field, $post, $file, $item);
    $check($post === array(array('more_title' => 'More news')), 'module values: only offered names are stored, as plain text: ' . json_encode($post));
    $check(substr_count($loadmodule, 'if (!static::isItemModuleParam($param_name)) continue;') === 2 && strpos($loadmodule, 'static::cleanItemModuleParamValue($value[$param_name])') !== false,
        'module values: the form and the display apply the same rule to stored values');

    // 5. LESS configuration values
    $system = source('plugins/system/flexisystem/flexisystem.php');
    eval('final class LessFixture { public ' . methodSource($system, '_cleanLessValue') . ' }');
    $less = new LessFixture;
    $fieldDouble = function (array $attributes) { return new class($attributes) { private $a; public function __construct($a) { $this->a = $a; } public function getAttribute($name) { return isset($this->a[$name]) ? $this->a[$name] : ''; } }; };
    $number = $fieldDouble(array('type' => 'number', 'default' => '36', 'min' => '20', 'max' => '80'));
    $check($less->_cleanLessValue('40', $number) === '40' && $less->_cleanLessValue(' 44 ', $number) === '44', 'less: a number inside the range is kept');
    $check($less->_cleanLessValue('5', $number) === '20' && $less->_cleanLessValue('900', $number) === '80', 'less: a number is moved into the range of the field');
    foreach (array('36; @import (inline) "../../../../configuration.php"', '36;} body{x:y', 'data-uri("x")', '', 'abc', array('36')) as $value) {
        $check($less->_cleanLessValue($value, $number) === '36', 'less: the default replaces an unusable number: ' . json_encode($value));
    }
    $text = $fieldDouble(array('type' => 'text', 'default' => ''));
    foreach (array('#fff', '#A1B2C3', '12px', '1.5em', '50%', 'inherit', 'sans-serif') as $value) {
        $check($less->_cleanLessValue($value, $text) === $value, 'less: plain value is kept: ' . $value);
    }
    foreach (array('red; @import "x"', '~"x"', 'e("x")', 'url(x)', '@other', "a\nb", '{x}', 'data-uri(a.png)') as $value) {
        $check($less->_cleanLessValue($value, $text) === null, 'less: value is left out: ' . json_encode($value));
    }
    $check(strpos($system, "\$v = \$this->_cleanLessValue(\$v, \$field);") !== false && strpos($system, "\$less_data .= '@' . \$field->fieldname . ': ' . \$v . \";\\n\";") !== false, 'less: every written value goes through the check');
    $check(strpos($system, "if (!\$config_xml || isset(\$config_fields[(string) \$i])) {") !== false, 'configuration: layout parameters do not replace component options');
    $check(strpos($system, "preg_match('/^(?:[A-Za-z0-9_-]+:)?[A-Za-z0-9_-]+\$/D', \$layout)") !== false, 'module save: the layout name is a plain name');
    $check(substr_count($system, '!$this->_canSaveModulePhpRule($table, $isNew)') === 2, 'module save: the PHP rule is checked again after the layout parameters are merged');

    // 6. The record that com_content authorised is the one that is saved
    $check(strpos($system, '$item->id = $_id;') === false, 'article save: the record is never pointed at the request id');
    $check(strpos($system, "if (\$_id && Factory::getApplication()->isClient('site') && !empty(\$item->id) && (int) \$item->id === \$_id) {") !== false, 'article save: a_id is used only on the site and only when it is the id of the record');
    $check(strpos($system, "if (Factory::getApplication()->isClient('site') && Factory::getApplication()->input->getInt('a_id', 0)) {") !== false, 'article form: a_id is used only on the site');

    // 7. Placeholder values inside administrator SQL
    $fields = source('site/classes/flexicontent.fields.php');
    eval('final class ReplaceFixture { static ' . methodSource($fields, 'doQueryReplacements') . ' }
        final class flexicontent_html { public static function getUserCurrentLang($short = true) { return $short ? "en" : "en-GB"; } }');
    $replace = function ($query, $item, $field = null, $sql = true) { $item_pros = true; $cache = null; return ReplaceFixture::doQueryReplacements($query, $field, $item, $item_pros, $cache, $sql); };
    $item = (object) array('id' => 12, 'title' => "O'Brien\\", 'language' => "en-GB' OR '1'='1", 'list' => array('x'), 'catid' => 4);
    $check($replace("SELECT id FROM t WHERE c = {item->catid} AND lang = '{item->language}' AND t LIKE '%{item->title}%'", $item)
        === "SELECT id FROM t WHERE c = 4 AND lang = 'en-GB\\' OR \\'1\\'=\\'1' AND t LIKE '%O\\'Brien\\\\%'", 'sql placeholders: item values are escaped');
    $check($replace('x = {item->list} y = {item->missing} z = {item->id}', $item) === 'x =  y =  z = 12', 'sql placeholders: lists and missing properties give an empty value');
    $check($replace("f = '{field->name}'", $item, (object) array('name' => "a'b")) === "f = ' a\\'b'", 'sql placeholders: field values are escaped, with the space they always had');
    $check($replace('<b>{item->title}</b>', $item, null, false) === "<b>O'Brien\\</b>", 'html templates: values are not SQL-escaped');
    $check(strpos($fields, 'eval (') === false || strpos(methodSource($fields, 'doQueryReplacements'), 'eval') === false, 'placeholders are replaced without eval');

    // 8. One-line checks and encodings
    $check(strpos(source('site/views/tags/view.html.php'), "\$curr_url   = htmlspecialchars(\$_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8');") !== false, 'tags view: the request URI is encoded');
    $check(strpos(source('plugins/flexicontent_fields/image/tmpl/value_gallery_jce_mediabox.php'), "' title=\"' . \$title_attr . '\">") !== false, 'image field, MediaBox layout: the title is a quoted attribute');
    $check(strpos(source('site/views/category/view.feed.php'), "alt=\"'.\$_title_attr.'\" title=\"'.\$_title_attr.'\"") !== false, 'category feed: the title is encoded inside the image attributes');
    $controller = source('site/controller.php');
    $check(strpos($controller, 'hash_equals($cart->token, $cart_token)') !== false && strpos($controller, '$cart_token==$cart->token') === false, 'download cart: the token is compared as a real token');
    $check(strpos(source('site/models/item.php'), "\$isOwner = (int) \$user->get('id') > 0 && (int) \$this->_record->created_by === (int) \$user->get('id');") !== false, 'item view: a guest owns nothing');
    $model = source('admin/models/parentclassitem.php');
    $check(strpos($model, "ugm.group_id IN (' . implode(',', \$_group_ids) . ')'") !== false && strpos($model, "implode(',', \$nConf->{\$ugrps[\$ntype]})") === false, 'notifications: group ids reach the query as integers');
    $check(strpos($model, "unset(\$data['notify_owner']);") !== false && strpos($model, "unset(\$data['notify']);") !== false, 'item save: notify requests are dropped when the form may not offer them');
    $check(strpos(source('admin/controllers/items.raw.php'), "\$query->where(\$db->quoteName('f.uploaded_by') . ' = ' . (int) \$user->id);") !== false, 'file search: other uploaders are listed only for users who may view all files');
    $check(strpos(source('plugins/flexicontent_fields/comments/comments.php'), "preg_match('/^[A-Za-z0-9-]+\$/D', \$forum_shortname)") !== false, 'comments: the Disqus shortname is a host name label');
    $list = source('admin/models/base/baselist.php');
    $check(strpos($list, "isClient('site') && property_exists(\$table, \$this->state_col ?? '') && !\$user->authorise('core.manage', 'com_flexicontent')") !== false, 'record lists on the site: published or own records only');
    $check(strpos($list, '(?:password|email|username|activation|otpKey|otep|authProvider|params|registerDate|lastvisitDate)') !== false, 'record lists: credential columns are not sort keys');
    $check(substr_count($list, "\$this->_triggerFinderSaveEvent('onFinder") === 2 && strpos($list, "'flexicontent_types'      => 'com_flexicontent.type'") !== false, 'access toggle: Smart Search is told about items, categories and types');
    $modhelper = source('modules/mod_flexicontent/helper.php');
    $check(strpos($modhelper, "@unserialize(\$dynamic_cids, array('allowed_classes' => false))") !== false && strpos($modhelper, "' AND c.published = 1'") !== false, 'module category data: no objects, published categories only');
    $check(strpos(source('site/classes/flexicontent.categories.php'), "\$all_cats = array_values(array_unique(array_filter(array_map('intval', array_filter(\$all_cats, 'is_scalar')))));") !== false, 'category helper: ids are integers');
    $check(strpos(source('modules/mod_flexitagcloud/helper.php'), "array_values(array_filter(array_map('intval', array_filter(\$cids, 'is_scalar'))))") !== false, 'tag cloud: category ids are integers');

    if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
