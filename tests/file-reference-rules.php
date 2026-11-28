<?php
/**
 * File references made from the item form: the upload folder of an item (folder-mode images), image names posted to an
 * image field in DB mode, and files that are deleted because their value was removed from an item.
 * Runs the real methods with doubles; no Joomla, no database (a small in-memory double answers the queries).
 */
namespace Joomla\CMS {
    class Factory {
        public static $app, $db;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($service) { return Factory::$db; } }; }
    }
}
namespace Joomla\CMS\Session {
    class Session { public static function checkToken($method = 'post') { return true; } }
}
namespace Joomla\CMS\Language {
    class Text { public static function _($key) { return $key; } public static function sprintf($key, ...$args) { return $key; } }
}
namespace Joomla\CMS\Component {
    class ComponentHelper { public static function getParams($option) { return new \Joomla\Registry\Registry; } }
}
namespace Joomla\Database {
    interface DatabaseInterface {}
}
namespace Joomla\Utilities {
    class ArrayHelper { public static function toInteger($array) { return array_map('intval', (array) $array); } }
}
namespace Joomla\Filesystem {
    class Path { public static function clean($path) { return preg_replace('#[/\\\\]+#', DIRECTORY_SEPARATOR, $path); } }
    class File { public static function delete($file) { return @unlink($file); } }
    class Folder {}
}
namespace Joomla\Registry {
    class Registry {
        private $data;
        public function __construct($data = array()) { $this->data = is_string($data) ? (array) json_decode($data, true) : (array) $data; }
        public function get($name, $default = null) { return isset($this->data[$name]) ? $this->data[$name] : $default; }
    }
}
namespace {
    use Joomla\CMS\Factory;
    use Joomla\Registry\Registry;

    error_reporting(E_ALL);
    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    $root = dirname(__DIR__);
    $scratch = sys_get_temp_dir() . DS . 'fc-file-reference-' . bin2hex(random_bytes(6));
    define('JPATH_SITE', $scratch . DS . 'site');
    define('COM_FLEXICONTENT_FILEPATH', JPATH_SITE . DS . 'uploads');
    define('COM_FLEXICONTENT_MEDIAPATH', JPATH_SITE . DS . 'medias');
    foreach (array($scratch, JPATH_SITE, COM_FLEXICONTENT_FILEPATH, COM_FLEXICONTENT_MEDIAPATH, JPATH_SITE . DS . 'thumbs') as $folder) mkdir($folder, 0700);

    $checks = 0;
    $failures = array();
    $check = function ($condition, $message) use (&$checks, &$failures) {
        $checks++;
        if (!$condition) $failures[] = $message;
    };
    $php_errors = array();
    set_error_handler(static function ($severity, $message, $file, $line) use (&$php_errors) {
        if (error_reporting() & $severity) $php_errors[] = $message . ' (' . basename($file) . ':' . $line . ')';
        return true;
    });

    function source($path) { return str_replace("\r\n", "\n", file_get_contents(dirname(__DIR__) . '/' . $path)); }
    function methodSource($source, $name) {
        $start = strpos($source, 'function ' . $name . '(');
        if ($start === false) throw new \RuntimeException('Method not found: ' . $name);
        $method = ''; $depth = 0; $opened = false;
        foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
            $method .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) { ++$depth; $opened = true; }
            if ($token === '}' && --$depth === 0 && $opened) break;
        }
        return $method;
    }
    function removeTree($path) {
        foreach (array_diff(scandir($path), array('.', '..')) as $name) is_dir($path . DS . $name) ? removeTree($path . DS . $name) : unlink($path . DS . $name);
        rmdir($path);
    }

    final class UserDouble {
        public $id, $guest, $rights, $levels;
        public function __construct($id, array $rights = array(), array $levels = array(1)) { $this->id = $id; $this->guest = !$id; $this->rights = $rights; $this->levels = $levels; }
        public function authorise($action, $asset = null) { return in_array($action . '@' . $asset, $this->rights, true); }
        public function getAuthorisedViewLevels() { return $this->levels; }
    }
    final class SessionDouble {
        public $data = array(), $id = 'session-a';
        public function get($key, $default = null, $namespace = 'default') { return isset($this->data[$namespace . '.' . $key]) ? $this->data[$namespace . '.' . $key] : $default; }
        public function set($key, $value, $namespace = 'default') { $this->data[$namespace . '.' . $key] = $value; }
        public function getId() { return $this->id; }
    }
    final class InputDouble {
        public $data = array(), $files;
        public function __construct() { $this->files = new class { public function get($name, $default = null, $filter = 'raw') { return $default; } }; }
        public function get($name, $default = null, $filter = 'cmd') { return isset($this->data[$name]) ? $this->data[$name] : $default; }
        public function set($name, $value) { $this->data[$name] = $value; }
    }
    final class AppDouble {
        public $identity, $session, $input, $state = array(), $messages = array();
        public function __construct() { $this->session = new SessionDouble; $this->input = new InputDouble; }
        public function getIdentity() { return $this->identity; }
        public function getSession() { return $this->session; }
        public function get($name, $default = null) { return $name === 'secret' ? 'fixture-secret' : $default; }
        public function getUserState($key, $default = null) { return isset($this->state[$key]) ? $this->state[$key] : $default; }
        public function setUserState($key, $value) { $this->state[$key] = $value; }
        public function enqueueMessage($message, $type = 'message') { $this->messages[] = $type . ': ' . $message; }
    }

    /** Builds the one query that the file manager model writes with the query builder */
    final class QueryDouble {
        private $select = array(), $from = '', $where = array();
        public function select($columns) { $this->select[] = $columns; return $this; }
        public function from($table) { $this->from = $table; return $this; }
        public function where($condition) { $this->where[] = $condition; return $this; }
        public function __toString() { return 'SELECT ' . implode(',', $this->select) . ' FROM ' . $this->from . ' WHERE ' . implode(' AND ', $this->where); }
    }

    /**
     * Tables as arrays. Every statement of the code under test has an answer here, anything else is an error.
     * Names are compared without regard to case and LIKE is evaluated, as the database does.
     */
    final class MemoryDb implements \Joomla\Database\DatabaseInterface {
        public $content = array(), $files = array(), $fields = array(), $relations = array(), $usage = array(), $log = array();
        private $sql = '';

        public function quote($text, $escape = true) { return "'" . ($escape ? $this->escape($text) : $text) . "'"; }
        public function escape($text, $extra = false) { $text = addcslashes((string) $text, "\\'\""); return $extra ? addcslashes($text, '%_') : $text; }
        public function quoteName($name) { return '`' . $name . '`'; }
        public function getQuery($new = false) { return new QueryDouble; }
        public function setQuery($sql, $offset = 0, $limit = 0) { $this->sql = (string) $sql; $this->log[] = $this->sql; return $this; }
        public function loadResult() { $rows = $this->rows(); return $rows ? current($rows[0]) : null; }
        public function loadObject() { $rows = $this->rows(); return $rows ? (object) $rows[0] : null; }
        public function loadColumn() { return array_map('current', $this->rows()); }
        public function loadObjectList($key = null) {
            $list = array();
            foreach ($this->rows() as $row) { if ($key === null) $list[] = (object) $row; else $list[$row[$key]] = (object) $row; }
            return $list;
        }
        public function execute() {
            if (preg_match('/^DELETE FROM #__flexicontent_files  WHERE `id` IN \(([\d,]+)\)$/', $this->sql, $m)) {
                foreach (explode(',', $m[1]) as $id) unset($this->files[(int) $id]);
                return true;
            }
            throw new \LogicException('Statement without an answer: ' . $this->sql);
        }

        // The value of a string literal, as the database reads it (a backslash before % or _ stays, LIKE needs it)
        private static function literal($text) { return preg_replace('/\\\\([\\\\\'"])/', '$1', $text); }
        private static function like($value, $pattern) {
            $regex = '';
            for ($i = 0, $n = strlen($pattern); $i < $n; $i++) {
                $c = $pattern[$i];
                if ($c === '\\' && $i + 1 < $n) $regex .= preg_quote($pattern[++$i], '/');
                elseif ($c === '%') $regex .= '.*';
                elseif ($c === '_') $regex .= '.';
                else $regex .= preg_quote($c, '/');
            }
            return (bool) preg_match('/^' . $regex . '$/is', $value);
        }
        private function filesNamed($name, $secure, $published = null) {
            $rows = array();
            foreach ($this->files as $id => $file) {
                if (strcasecmp($file['filename'], $name) || (int) $file['secure'] !== (int) $secure || (int) $file['url'] !== 0) continue;
                if ($published !== null && (int) $file['published'] !== $published) continue;
                $rows[] = array('id' => $id, 'filename' => $file['filename'], 'uploaded_by' => $file['uploaded_by']);
            }
            return $rows;
        }
        private function ignoredItems($pattern) {
            if (strpos($this->sql, ' WHERE ') === false) return array();
            if (!preg_match($pattern, $this->sql, $m)) throw new \LogicException('Usage query with an unknown condition: ' . $this->sql);
            return array_map('intval', explode(',', $m[1]));
        }
        private function counts(array $per_file) {
            $rows = array();
            foreach ($per_file as $file_id => $count) if ($count) $rows[] = array('file_id' => $file_id, 'item_count' => $count);
            return $rows;
        }

        private function rows() {
            $sql = $this->sql;

            if (preg_match('/^SELECT created_by FROM #__content WHERE id = (\d+)$/', $sql, $m)) {
                return isset($this->content[$m[1]]) ? array(array('created_by' => $this->content[$m[1]]['created_by'])) : array();
            }
            if (preg_match('/^SELECT \* FROM #__flexicontent_fields WHERE id=(\d+)$/', $sql, $m)) {
                return isset($this->fields[$m[1]]) ? array(array('id' => (int) $m[1]) + $this->fields[$m[1]]) : array();
            }
            if (preg_match("/^SELECT filename, uploaded_by FROM #__flexicontent_files WHERE filename = '(.*?)' AND secure = (\d) AND url = 0 AND published = 1$/s", $sql, $m)) {
                return array_map(static function ($row) { unset($row['id']); return $row; }, $this->filesNamed(self::literal($m[1]), $m[2], 1));
            }
            if (preg_match("/^SELECT id, filename, uploaded_by FROM #__flexicontent_files WHERE filename = '(.*?)'  AND secure = (\d)  AND url = 0$/s", $sql, $m)) {
                return $this->filesNamed(self::literal($m[1]), $m[2]);
            }
            if (preg_match('/^SELECT id, uploaded_by FROM #__flexicontent_files WHERE id=(\d+)$/', $sql, $m)) {
                return isset($this->files[$m[1]]) ? array(array('id' => (int) $m[1], 'uploaded_by' => $this->files[$m[1]]['uploaded_by'])) : array();
            }
            // Values of one field that name an image, in any item or in the items that the user may view
            if (preg_match("/^SELECT rel\.value FROM #__flexicontent_fields_item_relations AS rel( JOIN #__content AS c ON c\.id = rel\.item_id)?"
                . " WHERE rel\.field_id = (\d+) AND \(rel\.value = '(.*?)' OR rel\.value LIKE '(.*?)'\)"
                . "(?: AND c\.access IN \(([\d,]+)\) AND \(c\.state IN \(1, -5\) OR \(c\.created_by = (\d+) AND c\.created_by > 0\)\))?$/s", $sql, $m)) {
                if (($m[1] !== '') !== isset($m[5])) throw new \LogicException('Item conditions without the item table: ' . $sql);
                $rows = array();
                foreach ($this->relations as $rel) {
                    if ($rel[0] != $m[2] || (strcasecmp($rel[2], self::literal($m[3])) && !self::like($rel[2], self::literal($m[4])))) continue;
                    if ($m[1] !== '') {
                        if (!isset($this->content[$rel[1]])) continue;
                        $item = $this->content[$rel[1]];
                        if (!in_array($item['access'], explode(',', $m[5])) || !(in_array($item['state'], array(1, -5)) || ($item['created_by'] == $m[6] && $m[6] > 0))) continue;
                    }
                    $rows[] = array('value' => $rel[2]);
                }
                return $rows;
            }
            // A value of an image field that is the file name alone
            if (preg_match("/^SELECT rel\.value FROM #__flexicontent_fields_item_relations AS rel JOIN #__flexicontent_fields AS fi ON fi\.id = rel\.field_id AND fi\.field_type = 'image' WHERE rel\.value = '(.*?)'$/s", $sql, $m)) {
                $rows = array();
                foreach ($this->relations as $rel) {
                    if ($this->fields[$rel[0]]['field_type'] === 'image' && !strcasecmp($rel[2], self::literal($m[1]))) $rows[] = array('value' => $rel[2]);
                }
                return $rows;
            }
            // Image fields that take their files from the file manager
            if (strpos($sql, "FROM #__flexicontent_fields WHERE field_type = 'image' AND attribs LIKE") !== false) {
                $rows = array();
                foreach ($this->fields as $id => $field) if ($field['field_type'] === 'image' && strpos($field['attribs'], '"image_source":"0"') !== false) $rows[] = array('id' => $id);
                return $rows;
            }
            // Usage of files: by file and media file fields, ...
            if (strpos($sql, 'JOIN #__flexicontent_files AS a ON a.id=rel.value') !== false) {
                preg_match("/AND a\.id IN \('([\d',]+)'\)/", $sql, $ids);
                $ignored = $this->ignoredItems('/ WHERE\s+i\.id NOT IN \(([\d,]+)\) GROUP BY a\.id$/');
                $counts = array();
                foreach (explode("','", $ids[1]) as $file_id) {
                    $counts[$file_id] = 0;
                    foreach ($this->relations as $rel) {
                        if (in_array($this->fields[$rel[0]]['field_type'], array('file', 'mediafile'), true) && $rel[2] === (string) $file_id
                            && isset($this->content[$rel[1]]) && !in_array($rel[1], $ignored)) $counts[$file_id]++;
                    }
                }
                return $this->counts($counts);
            }
            // ... by image fields in DB mode (the value holds the file name), ...
            if (strpos($sql, 'JOIN #__flexicontent_files AS a ON rel.value LIKE CONCAT(') !== false) {
                preg_match('/CONCAT\("%","\\\\"(\w+)\\\\";s:%:%\\\\"",a\.filename,"\\\\"%"\)/', $sql, $prop);
                preg_match('/AND a\.id IN \(([\d,]+)\)/', $sql, $ids);
                if (!$prop || !$ids) throw new \LogicException('Image usage query not understood: ' . $sql);
                $ignored = $this->ignoredItems('/ WHERE\s+i\.id NOT IN \(([\d,]+)\) GROUP BY a\.id$/');
                $counts = array();
                foreach (explode(',', $ids[1]) as $file_id) {
                    $counts[$file_id] = 0;
                    if (!isset($this->files[$file_id])) continue;
                    foreach ($this->relations as $rel) {
                        $field = $this->fields[$rel[0]];
                        if ($field['field_type'] === 'image' && strpos($field['attribs'], '"image_source":"0"') !== false && isset($this->content[$rel[1]]) && !in_array($rel[1], $ignored)
                            && self::like($rel[2], '%"' . $prop[1] . '";s:%:%"' . addcslashes($this->files[$file_id]['filename'], '%_\\') . '"%')) $counts[$file_id]++;
                    }
                }
                return $this->counts($counts);
            }
            // ... and by download links inside texts
            if (strpos($sql, 'FROM #__flexicontent_file_usage AS i') !== false) {
                preg_match("/AND a\.id IN \('([\d',]+)'\)/", $sql, $ids);
                $ignored = $this->ignoredItems("/ WHERE\s+NOT \(i\.id IN \(([\d,]+)\) AND i\.context = 'com_content\.article'\) GROUP BY a\.id$/");
                $counts = array();
                foreach (explode("','", $ids[1]) as $file_id) {
                    $counts[$file_id] = 0;
                    foreach ($this->usage as $row) {
                        if ($row[2] == $file_id && !($row[1] === 'com_content.article' && in_array($row[0], $ignored))) $counts[$file_id]++;
                    }
                }
                return $this->counts($counts);
            }

            throw new \LogicException('Query without an answer: ' . $sql);
        }
    }

    final class flexicontent_html { public static function dataFilter($value, $maxlength = 0, $type = 'STRING', $decode = 0) { return is_string($value) ? trim($value) : $value; } }
    final class flexicontent_db {
        public static function unserialize_array($v, $force_array = false, $force_value = true) {
            if (is_array($v)) return $v;
            $result = is_string($v) && preg_match('/^a:\d+:{.*}$/s', $v) ? flexicontent_security::unserialize($v) : false;
            return $result !== false ? $result : ($force_value && $v !== null && $v !== '' ? ($force_array ? array($v) : $v) : ($force_array ? array() : false));
        }
    }
    final class FlexicontentFields {
        public static function getFieldValsById($field_ids, $item_ids) {
            $values = array();
            foreach (Factory::$db->relations as $rel) if (in_array($rel[1], $item_ids)) $values[$rel[1]][$rel[0]][] = $rel[2];
            return $values;
        }
    }

    Factory::$db = $db = new MemoryDb;
    Factory::$app = $app = new AppDouble;
    require $root . '/site/classes/helpers/security.php';

    $as = static function ($user) use ($app) { $app->identity = $user; $app->messages = array(); return $user; };
    $image_value = static function ($name, array $more = array()) { return serialize(array('originalname' => $name) + $more); };

    try {
        // Records: items 12 (of user 31) and 13 (of user 77) are published, 14 has an access level that user 31 lacks, 15 is a draft of user 77
        $db->content = array(
            12 => array('created_by' => 31, 'access' => 1, 'state' => 1),
            13 => array('created_by' => 77, 'access' => 1, 'state' => 1),
            14 => array('created_by' => 77, 'access' => 3, 'state' => 1),
            15 => array('created_by' => 77, 'access' => 1, 'state' => 0),
        );
        // Fields: 5 = image in folder mode, 7 and 8 = images in DB mode (secure folder), 9 = image in DB mode (media folder), 20 and 21 = file fields
        $db->fields = array(
            5  => array('field_type' => 'image', 'attribs' => '{"image_source":"1","dir":"thumbs"}', 'label' => 'Gallery'),
            7  => array('field_type' => 'image', 'attribs' => '{"image_source":"0","target_dir":"1","dir":"thumbs"}', 'label' => 'Photo'),
            8  => array('field_type' => 'image', 'attribs' => '{"image_source":"0","target_dir":"1","dir":"thumbs"}', 'label' => 'Second photo'),
            9  => array('field_type' => 'image', 'attribs' => '{"image_source":"0","target_dir":"0","dir":"thumbs"}', 'label' => 'Media photo'),
            20 => array('field_type' => 'file', 'attribs' => '{}', 'label' => 'File'),
            21 => array('field_type' => 'file', 'attribs' => '{}', 'label' => 'Other file'),
        );

        $author  = new UserDouble(31, array('core.edit.own@com_content.article.12', 'core.edit.own@com_content.article.13'));
        $editor  = new UserDouble(40, array('core.edit@com_content.article.13'));
        $manager = new UserDouble(34, array('flexicontent.viewallfiles@com_flexicontent'));
        $guest   = new UserDouble(0);

        // 1. The upload folder of an item: an item that the user may edit, or a temporary id issued to the user
        $folder = static function ($id, $user) use ($app, $as) { $as($user); return flexicontent_security::canUseItemUploadFolder($app, $id, $user); };
        $check($folder('12', $author) && $folder(12, $author), 'upload folder: own item with the edit-own right');
        $check(!$folder('13', $author), 'upload folder: the edit-own right does not open the item of another author');
        $check($folder('13', $editor) && !$folder('12', $editor), 'upload folder: the edit right of that item opens it, no other item');
        $check(!$folder('99', $editor) && !$folder('99', new UserDouble(41, array('core.edit@com_content.article.99'))), 'upload folder: no item, no folder');
        $check(!$folder('12', $guest) && !$folder('12', new UserDouble(0, array('core.edit.own@com_content.article.12'))), 'upload folder: a guest has no own items');
        foreach (array('', '0', 0, '012', '12 ', ' 12', '+12', '-12', '12.0', '1e1', '12/../13', '../../tmp', '..', 'item', '12_field_5', "12\n", null, false, array('12'), 12.0) as $id) {
            $check(!$folder($id, $author), 'upload folder: refused id ' . json_encode($id));
        }
        $app->session->set('rendered_uneditable', array(13 => -1, 14 => 2, 15 => 0), 'flexicontent');
        $check($folder('13', $author) && $folder('14', $author) && !$folder('15', $author), 'upload folder: an item that the item form keeps editable for the session');
        $app->session->set('rendered_uneditable', array(), 'flexicontent');
        $check(!$folder('13', $author), 'upload folder: no edit access of the session, no folder');

        $as($author);
        $temporary = flexicontent_security::getOrCreateTemporaryItemId($app);
        $check($folder($temporary, $author), 'upload folder: the temporary id issued to the user for an unsaved item');
        $check(!$folder($temporary, $editor), 'upload folder: not for another user');
        $check(!$folder(substr($temporary, 0, -1) . ($temporary[strlen($temporary) - 1] === '0' ? '1' : '0'), $author), 'upload folder: not with a changed signature');
        $check(!$folder('_2026_11_27_10_00_00_' . str_repeat('ab', 32), $author), 'upload folder: not an id that the client made up');
        $guest_id = (function () use ($app, $as, $guest) { $as($guest); $app->state = array(); return flexicontent_security::getOrCreateTemporaryItemId($app); })();
        $check($folder($guest_id, $guest), 'upload folder: the temporary id of a guest in the same session');
        $app->session->id = 'session-b';
        $check(!$folder($guest_id, $guest), 'upload folder: not in another session');
        $app->session->id = 'session-a';

        // 2. The upload task: a folder-mode image field needs such an id, the right to upload is not enough
        $controller = source('admin/controllers/filemanager.php');
        eval('use Joomla\CMS\Factory; use Joomla\CMS\Language\Text; use Joomla\Utilities\ArrayHelper; use Joomla\Filesystem\File; use Joomla\Filesystem\Path; use Joomla\Database\DatabaseInterface;
            final class UploadFixture {
                public $input, $task = "upload", $format = "", $runMode = "standalone", $record_name_pl = "filemanager";
                public $exitHttpHead, $exitMessages = array(), $exitLogTexts, $exitSuccess, $reached_folder = false;
                public function getModel($name) { $fixture = $this; return new class($fixture) {
                    private $fixture; public function __construct($fixture) { $this->fixture = $fixture; }
                    public function getFieldFolderPath(...$args) { $this->fixture->reached_folder = true; return JPATH_SITE . DS; } }; }
                public function refactorFilesArray($file) { return $file; }
                public function terminate($exitData = null, &$exitMessages = null, $data = null) { return (int) $this->exitHttpHead[0]["status"]; }
                public ' . methodSource($controller, 'upload') . ' }');
        $upload = static function ($user, array $request, array $session = array(), $task = 'upload') use ($app, $as) {
            $as($user);
            $app->input->data = $request;
            $app->session->set('uploader_file_data', $session, 'flexicontent');
            $fixture = new UploadFixture;
            $fixture->input = $app->input;
            $fixture->task = $task;
            return array($fixture->upload(), $fixture->reached_folder);
        };
        $uploader  = array('flexicontent.uploadfiles@com_flexicontent');
        $up_author = new UserDouble(31, array_merge($uploader, $author->rights));
        $up_other  = new UserDouble(50, $uploader);
        // 400 is the answer to a request that passed the checks and brought no file
        $check($upload(new UserDouble(31, $author->rights), array('fieldid' => 5, 'u_item_id' => '12')) === array(403, false), 'upload: the right to upload is still required');
        $check($upload($up_author, array('fieldid' => 5, 'u_item_id' => '12')) === array(400, false), 'upload: folder mode, into the own item');
        $check($upload($up_other, array('fieldid' => 5, 'u_item_id' => '12')) === array(403, false), 'upload: folder mode, the item of another user is refused');
        $check($upload($up_author, array('fieldid' => 5, 'u_item_id' => '13')) === array(403, false), 'upload: folder mode, an item that the user may not edit is refused');
        $check($upload($up_author, array('fieldid' => 5)) === array(403, false) && $upload($up_author, array('fieldid' => 5, 'u_item_id' => '0')) === array(403, false), 'upload: folder mode needs an item');
        $check($upload($up_author, array('fieldid' => 5, 'u_item_id' => '12-x')) === array(403, false), 'upload: folder mode, an id that is not an item id is refused');
        $as($up_other); $app->state = array();
        $other_temporary = flexicontent_security::getOrCreateTemporaryItemId($app);
        $check($upload($up_other, array('fieldid' => 5, 'u_item_id' => $other_temporary)) === array(400, false), 'upload: folder mode, the unsaved item of the user');
        $check($upload($up_author, array('fieldid' => 5, 'u_item_id' => $other_temporary)) === array(403, false), 'upload: folder mode, the unsaved item of another user is refused');
        $check($upload($up_other, array('file_row_id' => 'r1'), array('r1' => array('filetitle' => '', 'filedesc' => '', 'filelang' => '*', 'fileaccess' => 1, 'secure' => 1, 'stamp' => 0,
            'fieldid' => 5, 'u_item_id' => '12', 'file_mode' => 'folder_mode')), 'uploads') === array(403, false), 'upload: the item id kept in the session by the multi-file uploader is checked too');
        $check($upload($up_other, array('fieldid' => 7, 'u_item_id' => '12')) === array(400, false) && $upload($up_other, array('fieldid' => 20, 'u_item_id' => '12')) === array(400, false)
            && $upload($up_other, array()) === array(400, false), 'upload: DB mode and file fields upload into the file manager as before');
        $upload_source = methodSource($controller, 'upload');
        $check(strpos($upload_source, 'flexicontent_security::canUseItemUploadFolder($app, (string) $u_item_id, $user)') < strpos($upload_source, '$chunks = $this->input->get(')
            && strpos($upload_source, 'flexicontent_security::canUseItemUploadFolder(') < strpos($upload_source, '$model->getFieldFolderPath('), 'upload: the item is checked before any part of a file is taken');

        // 3. The file picker of a field
        $view = source('admin/views/fileselement/view.html.php');
        $guard = strpos($view, "if (\$has_item ? !flexicontent_security::canUseItemUploadFolder(\$app, \$u_item_id, \$user) : \$folder_mode)");
        $check($guard !== false && $guard > strpos($view, "'u_item_id', 0, 'string' )") && $guard < strpos($view, "if (!\$u_item_id && \$filter_item)")
            && $guard < strpos($view, '$model->getFilesFromPath(') && $guard < strpos($view, "\$upload_context = 'fc_upload_history.item_'"), 'picker: the item id is checked before it is used');
        $check(strpos($view, 'http_response_code(403);', $guard) < strpos($view, "if (!\$u_item_id && \$filter_item)"), 'picker: a refused id ends the request');
        $model = source('admin/models/filemanager.php');
        $check(preg_match("#if \(is_array\(\\\$itemid\) \|\| is_object\(\\\$itemid\) \|\| !preg_match\('([^']+)', \(string\) \\\$itemid\)\)\s+\{\s+die\(#", methodSource($model, 'getFieldFolderPath'), $folder_rule) === 1,
            'model: the folder of an item is built from a checked id only');
        foreach (array('12', $temporary, '') as $id) $check(preg_match($folder_rule[1], $id) === 1, 'model: folder id is accepted: ' . $id);
        foreach (array('../x', '12/..', '12\\x', '..', 'a.b', '12 ', "12\n", 'a-b') as $id) $check(preg_match($folder_rule[1], $id) === 0, 'model: folder id is refused: ' . json_encode($id));

        // 4. An image name posted to an image field in DB mode
        $db->files = array(
            50 => array('filename' => 'own.jpg',      'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 31),
            51 => array('filename' => 'other.jpg',    'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 77),
            52 => array('filename' => 'media.jpg',    'secure' => 0, 'url' => 0, 'published' => 1, 'uploaded_by' => 31),
            53 => array('filename' => 'hidden.jpg',   'secure' => 1, 'url' => 0, 'published' => 0, 'uploaded_by' => 31),
            54 => array('filename' => 'Case.jpg',     'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 31),
            55 => array('filename' => 'shown.jpg',    'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 77),
            56 => array('filename' => 'a_b%c.jpg',    'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 77),
            57 => array('filename' => 'nobody.jpg',   'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 0),
            58 => array('filename' => 'legacy.jpg',   'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 77),
        );
        $field_object = static function ($id, array $more = array()) use ($db) {
            return (object) array('id' => $id, 'field_type' => $db->fields[$id]['field_type'], 'label' => $db->fields[$id]['label'], 'item_id' => 0,
                'parameters' => new Registry($more + json_decode($db->fields[$id]['attribs'], true)));
        };
        $image = static function ($name, $user, array $relations = array(), array $options = array(), $field_id = 7) use ($db, $field_object) {
            $db->relations = $relations;
            return flexicontent_security::canAssignImageToField($field_object($field_id, $options), $name, $user);
        };
        $all = array('list_all_media_files' => 1);
        $check($image('own.jpg', $author), 'image name: an upload of the user');
        $check(!$image('other.jpg', $author) && !$image('other.jpg', $author, array(), $all), 'image name: an image of another uploader is refused');
        $check(!$image('missing.jpg', $author) && !$image('missing.jpg', $manager, array(), $all), 'image name: a name without a file record is refused, also for users who may view all files');
        $check(!$image('media.jpg', $author) && $image('media.jpg', $author, array(), array(), 9) && !$image('own.jpg', $author, array(), array(), 9), 'image name: the record must be in the folder that the field uses');
        $check(!$image('hidden.jpg', $author), 'image name: an unpublished file is refused');
        $check($image('Case.jpg', $author) && !$image('case.jpg', $author) && !$image('OWN.JPG', $author), 'image name: the name must be the exact name of the record');
        $check(!$image('', $author) && !$image(array('own.jpg'), $author) && !$image(null, $author)
            && !flexicontent_security::canAssignImageToField((object) array('id' => 0, 'parameters' => new Registry), 'own.jpg', $author), 'image name: no name or no field, no image');
        $check($image('other.jpg', $manager, array(), $all), 'image name: users who may view all files can use any image when the field lists all images');
        $check($image('other.jpg', new UserDouble(35, array('core.admin@com_flexicontent')), array(), $all), 'image name: so can component administrators');
        $check(!$image('other.jpg', $manager), 'image name: not when the field lists only its own images');
        $check(!$image('other.jpg', $manager, array(), $all + array('limit_by_uploader' => 1)), 'image name: not when the field is limited to the uploader');
        $check($image('other.jpg', $manager, array(array(7, 15, $image_value('other.jpg')))), 'image name: an image that the field uses in any item, for users who may view all files');
        $check(!$image('other.jpg', $manager, array(array(8, 15, $image_value('other.jpg')))), 'image name: the use by another field does not count');
        $check($image('shown.jpg', $author, array(array(7, 13, $image_value('shown.jpg', array('alt' => 'x'))))), 'image name: an image that the field shows in an item that the user may view');
        $check(!$image('shown.jpg', $author, array(array(7, 14, $image_value('shown.jpg')))), 'image name: not from an item with an access level that the user lacks');
        $check(!$image('shown.jpg', $author, array(array(7, 15, $image_value('shown.jpg')))), 'image name: not from an unpublished item of another user');
        $check(!$image('shown.jpg', $manager, array(array(7, 15, $image_value('shown.jpg'))), array('limit_by_uploader' => 1)), 'image name: a field limited to the uploader gives no image of a hidden item');
        $check($image('shown.jpg', $manager, array(array(7, 13, $image_value('shown.jpg'))), array('limit_by_uploader' => 1)), 'image name: an image of a visible item is still accepted there (copy of an item)');
        $check(!$image('shown.jpg', $author, array(array(8, 13, $image_value('shown.jpg')))), 'image name: another field of a visible item does not count');
        $check(!$image('shown.jpg', $author, array(array(7, 13, $image_value('xshown.jpg')), array(7, 13, $image_value('SHOWN.JPG')), array(7, 13, serialize(array('originalname' => 'no.jpg', 'alt' => 'shown.jpg'))))),
            'image name: a value with another name, or with the name in another property, does not count');
        $check($image('a_b%c.jpg', $author, array(array(7, 13, $image_value('a_b%c.jpg')))) && !$image('a_b%c.jpg', $author, array(array(7, 13, $image_value('aXbYYc.jpg')))), 'image name: wildcard characters in a name are plain characters');
        $check($image('legacy.jpg', $author, array(array(7, 13, 'legacy.jpg'))), 'image name: an old value that is the name alone counts');
        $check($image('nobody.jpg', $guest) && !$image('other.jpg', $guest), 'image name: a guest may use a file without an uploader, as in the file picker');
        $db->relations = array();
        $as(null);
        $check(!flexicontent_security::canAssignImageToField($field_object(7), 'own.jpg'), 'image name: no user, no image');

        // 5. The right to delete a file record
        $delete_all = array('flexicontent.deletefile@com_flexicontent');
        $delete_own = array('flexicontent.deleteownfile@com_flexicontent');
        $record = (object) array('id' => 51, 'uploaded_by' => 77);
        $check(flexicontent_security::canDeleteFileRecord($record, new UserDouble(31, $delete_all)), 'delete right: any file with the right to delete files');
        $check(flexicontent_security::canDeleteFileRecord($record, new UserDouble(77, $delete_own)), 'delete right: an own upload with the right to delete own files');
        $check(!flexicontent_security::canDeleteFileRecord($record, new UserDouble(31, $delete_own)), 'delete right: not the upload of another user');
        $check(!flexicontent_security::canDeleteFileRecord($record, new UserDouble(77)) && !flexicontent_security::canDeleteFileRecord($record, new UserDouble(77, array('flexicontent.deletefile@com_content'))),
            'delete right: not without a delete right of the component');
        $check(!flexicontent_security::canDeleteFileRecord((object) array('id' => 57, 'uploaded_by' => 0), new UserDouble(0, $delete_own)), 'delete right: a guest owns no file');
        $check(!flexicontent_security::canDeleteFileRecord(null, new UserDouble(31, $delete_all)), 'delete right: no record, nothing to delete');

        // 6. The usage check of the file manager model, with and without items whose use is not counted
        eval('use Joomla\Utilities\ArrayHelper;
            final class FlexicontentModelFilemanager {
                public $_db; public static $deleted = array();
                public function __construct() { $this->_db = \Joomla\CMS\Factory::$db; }
                public function getState($name) { return null; }
                public function delete($cid) { foreach ($cid as $id) { self::$deleted[] = (int) $id; unset($this->_db->files[(int) $id]); } return true; }
                public ' . methodSource($model, 'candelete') . ' ' . methodSource($model, 'getDeletable') . ' ' . methodSource($model, 'getItemsSingleprop') . ' '
                . methodSource($model, 'getItemsMultiprop') . ' ' . methodSource($model, 'getCustomLinksFileUsage') . ' ' . methodSource($model, 'getFieldsUsingDBmode') . ' }');
        $as($author);
        $deletable = static function ($file_id, $ignored = null) { $fm = new FlexicontentModelFilemanager; return $ignored === null ? $fm->candelete(array($file_id)) : $fm->candelete(array($file_id), $ignored); };
        $db->usage = array();
        $db->relations = array(array(20, 12, '50'));
        $ignored = array(12);
        $check(!$deletable(50), 'usage: a file that an item uses can not be deleted');
        $check($deletable(50, $ignored), 'usage: it can when that item is the only user and is not counted');
        $log = implode("\n", array_slice($db->log, -6));
        $check(substr_count($log, 'i.id NOT IN (12)') === 3 && strpos($log, "NOT (i.id IN (12) AND i.context = 'com_content.article')") !== false && strpos($log, 'IN (0)') === false,
            'usage: all three queries leave out the given item, not item 0');
        $ignored = array(7 => 12);
        $check($deletable(50, $ignored), 'usage: the keys of the given list do not matter');
        $db->relations[] = array(21, 13, '50');
        $ignored = array(12);
        $check(!$deletable(50, $ignored), 'usage: the use by another item still counts');
        $ignored = array(12, 13);
        $check($deletable(50, $ignored), 'usage: several items can be left out');
        $db->relations = array(array(7, 12, $image_value('own.jpg')));
        $ignored = array(12);
        $check(!$deletable(50) && $deletable(50, $ignored), 'usage: the same for an image of a field in DB mode');
        $db->relations = array(array(5, 12, $image_value('own.jpg')));
        $check($deletable(50), 'usage: a folder-mode image field with a file of the same name is no use of the file manager file');
        $db->relations = array();
        $db->usage = array(array(12, 'com_content.article', 50));
        $ignored = array(12);
        $check(!$deletable(50) && $deletable(50, $ignored), 'usage: a download link in the text of the item that is left out is not counted');
        $db->usage = array(array(12, 'com_modules.module', 50));
        $ignored = array(12);
        $check(!$deletable(50, $ignored), 'usage: a download link in a module with the same id still counts');
        $db->usage = array(array(13, 'com_content.article', 50));
        $ignored = array(12);
        $check(!$deletable(50, $ignored), 'usage: a download link in another item still counts');
        $db->usage = array();
        $check(strpos(methodSource($model, 'candelete'), 'array_flip($cid_noauth)') === false, 'usage: the list of items is no longer flipped');

        // 7. File and media file fields: a removed file is deleted after the save, with the delete right, when nothing uses it
        foreach (array('file' => 'plugins/flexicontent_fields/file/file.php', 'mediafile' => 'plugins/flexicontent_fields/mediafile/mediafile.php') as $type => $path) {
            $plugin = source($path);
            $class = 'FileFieldFixture_' . $type;
            eval('use Joomla\CMS\Factory; final class ' . $class . ' { public static $field_types = array("' . $type . '"); public ' . methodSource($plugin, 'onAfterSaveField') . ' ' . methodSource($plugin, 'canDeleteFile') . ' }');
            $save = static function ($user, array $delete_ids, array $relations_after) use ($db, $as, $class, $type, $field_object) {
                $as($user);
                $db->files = array(
                    60 => array('filename' => 'mine.pdf', 'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 31),
                    61 => array('filename' => 'theirs.pdf', 'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 77),
                );
                $db->relations = $relations_after;
                FlexicontentModelFilemanager::$deleted = array();
                $field = $field_object(20);
                $field->field_type = $type;
                if ($delete_ids) $field->delete_file_ids = $delete_ids;
                $post = array(); $file = null; $item = (object) array('id' => 12);
                (new $class)->onAfterSaveField($field, $post, $file, $item);
                return array(FlexicontentModelFilemanager::$deleted, isset($field->delete_file_ids));
            };
            $own_deleter = new UserDouble(31, $delete_own);
            $check($save($own_deleter, array(60), array()) === array(array(60), false), $type . ' field: an own file that nothing uses any more is deleted');
            $check($save(new UserDouble(31), array(60), array()) === array(array(), false), $type . ' field: not without a delete right');
            $check($save($own_deleter, array(61), array()) === array(array(), false), $type . ' field: not the file of another uploader with the right to delete own files');
            $check($save(new UserDouble(31, $delete_all), array(61, 61), array()) === array(array(61), false), $type . ' field: any file with the right to delete files, once');
            $check($save($own_deleter, array(60), array(array(20, 12, '60'))) === array(array(), false), $type . ' field: not while the item still has it (version that was not approved, other value)');
            $check($save($own_deleter, array(60), array(array(21, 12, '60'))) === array(array(), false), $type . ' field: not while another field of the item uses it');
            $check($save($own_deleter, array(60), array(array(20, 13, '60'))) === array(array(), false), $type . ' field: not while another item uses it');
            $check($save($own_deleter, array(999), array()) === array(array(), false), $type . ' field: a file without a record is left alone');
            $check($save(new UserDouble(31, $delete_all), array(), array()) === array(array(), false), $type . ' field: nothing was removed, nothing is deleted');
            $before_save = methodSource($plugin, 'onBeforeSaveField');
            $check(strpos($before_save, '->delete(') === false && strpos($before_save, 'canDeleteFile(') === false && substr_count($before_save, '$field->delete_file_ids[] = ') === 3,
                $type . ' field: nothing is deleted before the values are stored');
            $check(strpos($before_save, 'if (!empty($file_id) && $replaced_file_id) $field->delete_file_ids[] = $replaced_file_id;') !== false, $type . ' field: a replaced file is deleted only when the new file arrived');
            $check(strpos(methodSource($plugin, 'canDeleteFile'), '$ignored') === false, $type . ' field: no item is left out of the usage check after the save');
        }

        // 8. Image field in DB mode: the posted names, and the images that the item no longer names
        $image_plugin = source('plugins/flexicontent_fields/image/image.php');
        preg_match('/static \$csv_tags = [^;]+;/', $image_plugin, $csv_tags);
        eval('use Joomla\CMS\Factory; use Joomla\Filesystem\File; use Joomla\Filesystem\Path; use Joomla\Filesystem\Folder;
            final class ImageFieldFixture {
                public static $field_types = array("image"); ' . $csv_tags[0] . '
                public function unserialize_array($v, $force_array = false, $force_value = true) { return flexicontent_db::unserialize_array($v, $force_array, $force_value); }
                public ' . methodSource($image_plugin, 'onBeforeSaveField') . ' public ' . methodSource($image_plugin, 'onAfterSaveField') . ' public ' . methodSource($image_plugin, 'removeOriginalFile')
                . ' public ' . methodSource($image_plugin, 'canDeleteImage') . ' protected ' . methodSource($image_plugin, 'getImageFileRecords') . ' protected ' . methodSource($image_plugin, 'getStoredFilenames')
                . ' private static ' . methodSource($image_plugin, 'isSafeImagePath') . ' public static ' . methodSource($image_plugin, 'safeLinkUrl') . ' }');
        $image_files = array(
            50 => array('filename' => 'own.jpg',    'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 31),
            51 => array('filename' => 'other.jpg',  'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 77),
            59 => array('filename' => 'stored.jpg', 'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 77),
        );
        $app->input->data = array('task' => 'save', 'unique_tmp_itemid' => '12');
        $before = static function ($user, array $posted, array $relations, array $options = array()) use ($db, $as, $app, $field_object, $image_files) {
            $as($user);
            $db->files = $image_files;
            $db->relations = $relations;
            $field = $field_object(7, $options);
            $post = $posted; $file = null; $item = (object) array('id' => 12);
            $result = (new ImageFieldFixture)->onBeforeSaveField($field, $post, $file, $item);
            $names = array();
            foreach ((array) $post as $value) { $value = $value === null ? null : unserialize($value); $names[] = $value === null ? null : $value['originalname']; }
            return (object) array('result' => $result, 'names' => $names, 'queue' => isset($field->delete_filenames) ? $field->delete_filenames : array(), 'warnings' => $app->messages, 'field' => $field, 'item' => $item);
        };
        $stored = array(array(7, 12, $image_value('stored.jpg')));
        $r = $before($author, array(array('existingname' => 'own.jpg', 'originalname' => '')), array());
        $check($r->names === array('own.jpg') && $r->warnings === array(), 'image field: an own upload is accepted');
        $r = $before($author, array(array('existingname' => 'other.jpg')), array());
        $check($r->names === array() && count($r->warnings) === 1 && strpos($r->warnings[0], 'other.jpg') !== false, 'image field: an image of another uploader is left out, with a warning');
        $r = $before($author, array(array('originalname' => 'other.jpg', 'existingname' => '')), array());
        $check($r->names === array() && count($r->warnings) === 1, 'image field: also when it is posted as the original name');
        $r = $before($author, array(array('originalname' => 'nofile.jpg')), array());
        $check($r->names === array(), 'image field: a name without a file record is left out');
        $r = $before($author, array(array('originalname' => 'stored.jpg', 'alt' => 'kept'), array('existingname' => 'own.jpg')), $stored);
        $check($r->names === array('stored.jpg', 'own.jpg') && $r->warnings === array() && $r->queue === array(), 'image field: a value that the item already has is kept, whoever uploaded the image');
        $r = $before($author, array(array('originalname' => 'stored.jpg')), array(array(8, 12, $image_value('stored.jpg')), array(7, 15, $image_value('stored.jpg'))));
        $check($r->names === array(), 'image field: a value of another field of the item, or of a hidden item, is no reason to accept a name');
        $r = $before($author, array(array('originalname' => 'STORED.JPG')), $stored);
        $check($r->names === array(), 'image field: a stored name in other letter case is another file');
        $r = $before($author, array(array('existingname' => 'other.jpg'), array('existingname' => 'own.jpg')), array(), array('use_ingroup' => 1));
        $check($r->names === array(null, 'own.jpg'), 'image field: inside a field group the refused value leaves an empty position');
        $r = $before($manager, array(array('existingname' => 'other.jpg')), array(), $all);
        $check($r->names === array('other.jpg'), 'image field: users who may view all files select any image when the field lists all images');
        $app->input->data['task'] = 'importcsv';
        $r = $before($author, array(array('originalname' => 'other.jpg')), array());
        $check($r->names === array('other.jpg'), 'image field: an import takes the names as they are');
        $app->input->data['task'] = 'save';
        try { $before($author, array(array('existingname' => '../own.jpg')), array()); $thrown = false; } catch (\InvalidArgumentException $e) { $thrown = true; }
        $check($thrown, 'image field: a path is still refused before any record is looked up');

        // The images that the item no longer names: listed before the save, removed after it
        $thumbs = JPATH_SITE . DS . 'thumbs';
        $disk = static function (array $secure_files, array $media_files = array()) use ($thumbs) {
            foreach (array(COM_FLEXICONTENT_FILEPATH => $secure_files, COM_FLEXICONTENT_MEDIAPATH => $media_files) as $folder => $names) {
                foreach (glob($folder . DS . '*') as $old) unlink($old);
                foreach ($names as $name) file_put_contents($folder . DS . $name, 'image');
            }
            foreach (glob($thumbs . DS . '*') as $old) unlink($old);
            foreach (array_merge($secure_files, $media_files) as $name) foreach (array('l', 'm', 's', 'b') as $size) file_put_contents($thumbs . DS . $size . '_' . $name, 'thumb');
        };
        $after = static function ($r, $user, array $relations_after) use ($db, $as) {
            $as($user);
            $db->relations = $relations_after;
            $post = array(); $file = null;
            (new ImageFieldFixture)->onAfterSaveField($r->field, $post, $file, $r->item);
            return array_keys($db->files);
        };
        $own_deleter = new UserDouble(31, array_merge($delete_own, $author->rights));
        $all_deleter = new UserDouble(31, array_merge($delete_all, $author->rights));
        $two = array(array(7, 12, $image_value('own.jpg')), array(7, 12, $image_value('stored.jpg')));

        $disk(array('own.jpg', 'stored.jpg', 'other.jpg'));
        $r = $before($own_deleter, array(array('originalname' => 'stored.jpg')), $two);
        $check($r->queue === array('own.jpg') && array_keys($db->files) === array(50, 51, 59) && is_file(COM_FLEXICONTENT_FILEPATH . DS . 'own.jpg'), 'image field: before the save a removed image is only listed');
        $check($after($r, $own_deleter, $stored) === array(51, 59) && !is_file(COM_FLEXICONTENT_FILEPATH . DS . 'own.jpg') && !is_file($thumbs . DS . 'l_own.jpg') && !is_file($thumbs . DS . 'b_own.jpg')
            && is_file(COM_FLEXICONTENT_FILEPATH . DS . 'stored.jpg') && is_file($thumbs . DS . 'l_stored.jpg') && !isset($r->field->delete_filenames),
            'image field: after the save the own image that nothing uses is removed, with its record and thumbnails');

        $disk(array('own.jpg', 'stored.jpg'));
        $r = $before($own_deleter, array(array('originalname' => 'own.jpg')), $two);
        $check($r->queue === array('stored.jpg') && $after($r, $own_deleter, array(array(7, 12, $image_value('own.jpg')))) === array(50, 51, 59) && is_file(COM_FLEXICONTENT_FILEPATH . DS . 'stored.jpg'),
            'image field: the image of another uploader stays with the right to delete own files');
        $r = $before($all_deleter, array(array('originalname' => 'own.jpg')), $two);
        $check($after($r, $all_deleter, array(array(7, 12, $image_value('own.jpg')))) === array(50, 51) && !is_file(COM_FLEXICONTENT_FILEPATH . DS . 'stored.jpg'), 'image field: it is removed with the right to delete files');

        $disk(array('own.jpg'));
        $r = $before($author, array(), array(array(7, 12, $image_value('own.jpg'))));
        $check($r->queue === array('own.jpg') && $after($r, $author, array()) === array(50, 51, 59) && is_file(COM_FLEXICONTENT_FILEPATH . DS . 'own.jpg'), 'image field: nothing is removed without a delete right');
        foreach (array(
            'the item still has it (version that was not approved)' => array(array(7, 12, $image_value('own.jpg'))),
            'another field of the item uses it' => array(array(8, 12, $image_value('own.jpg'))),
            'another item uses it' => array(array(7, 13, $image_value('own.jpg'))),
            'an old value of another item is the name alone' => array(array(8, 13, 'own.jpg')),
        ) as $case => $relations_after) {
            $r = $before($own_deleter, array(), array(array(7, 12, $image_value('own.jpg'))));
            $check($after($r, $own_deleter, $relations_after) === array(50, 51, 59) && is_file(COM_FLEXICONTENT_FILEPATH . DS . 'own.jpg') && is_file($thumbs . DS . 'l_own.jpg'), 'image field: nothing is removed while ' . $case);
        }

        // A name without a record, and a record of the other folder
        $disk(array('index.html', 'media.jpg'), array('own.jpg'));
        $r = $before($all_deleter, array(), array(array(7, 12, 'index.html'), array(7, 12, $image_value('media.jpg'))));
        $db->files[52] = array('filename' => 'media.jpg', 'secure' => 0, 'url' => 0, 'published' => 1, 'uploaded_by' => 31);
        $check($r->queue === array('index.html', 'media.jpg') && $after($r, $all_deleter, array()) === array(50, 51, 59, 52) && is_file(COM_FLEXICONTENT_FILEPATH . DS . 'index.html')
            && is_file(COM_FLEXICONTENT_FILEPATH . DS . 'media.jpg') && is_file($thumbs . DS . 'l_media.jpg'), 'image field: a file without a record in the folder of the field is never removed');
        $as($all_deleter);
        $fixture = new ImageFieldFixture;
        $check($fixture->removeOriginalFile($field_object(7), 'index.html') === false && is_file(COM_FLEXICONTENT_FILEPATH . DS . 'index.html'), 'image field: the removal itself refuses a file without a record');
        $db->files = $image_files + array(62 => array('filename' => 'own.jpg', 'secure' => 0, 'url' => 0, 'published' => 1, 'uploaded_by' => 31));
        $disk(array('own.jpg'), array('own.jpg'));
        $check($fixture->removeOriginalFile($field_object(7), 'own.jpg') === true && array_keys($db->files) === array(51, 59, 62) && !is_file(COM_FLEXICONTENT_FILEPATH . DS . 'own.jpg')
            && is_file(COM_FLEXICONTENT_MEDIAPATH . DS . 'own.jpg'), 'image field: the record and file of the same name in the other folder are kept');
        $db->files = array(63 => array('filename' => 'OWN.jpg', 'secure' => 1, 'url' => 0, 'published' => 1, 'uploaded_by' => 31));
        $disk(array('own.jpg'));
        $check($fixture->removeOriginalFile($field_object(7), 'own.jpg') === false && array_keys($db->files) === array(63) && is_file(COM_FLEXICONTENT_FILEPATH . DS . 'own.jpg'), 'image field: a record with the name in other letter case is another file');
        $check($fixture->removeOriginalFile($field_object(7), '../own.jpg') === false, 'image field: a path is never removed');
        $before_save = methodSource($image_plugin, 'onBeforeSaveField');
        $check(strpos($before_save, 'removeOriginalFile(') === false && strpos($before_save, 'canDeleteImage(') === false, 'image field: nothing is removed before the values are stored');
        $check(strpos(methodSource($image_plugin, 'canDeleteImage'), '$ignored') === false, 'image field: no item is left out of the usage check after the save');

        $check($php_errors === array(), 'no PHP warning or notice: ' . implode(' | ', array_unique($php_errors)));
    } finally {
        removeTree($scratch);
    }

    if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
