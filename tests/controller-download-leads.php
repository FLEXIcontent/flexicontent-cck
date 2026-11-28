<?php
/**
 * Site controller: item view cache vs session edit grant, download settings (limits) bound to the field that
 * holds the file, view method vs limits, multi-file archive clean-up, weblink task item state checks.
 * Runs the real controller methods with dummy records; no Joomla bootstrap, the database is a scripted double.
 */
namespace Joomla\CMS {
    final class Factory {
        public static $app, $db;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($service) { return Factory::$db; } }; }
        public static function getDate($when = 'now') { return new class { public function toSql() { return gmdate('Y-m-d H:i:s'); } }; }
    }
}
namespace Joomla\Registry {
    final class Registry {
        private $data;
        public function __construct($data = null) { $this->data = is_string($data) ? (array) json_decode($data, true) : (array) $data; }
        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
    }
}
namespace Joomla\CMS\Component {
    final class ComponentHelper { public static $params; public static function getParams($option) { return self::$params; } }
}
namespace Joomla\CMS\Language {
    final class Text { public static function _($key) { return $key; } public static function sprintf($key, ...$args) { return $key; } }
}
namespace Joomla\CMS\Uri {
    final class Uri {
        public static function getInstance() { return new class { public function toString() { return 'https://fixture.example/current'; } }; }
        public static function root($path = false) { return $path ? '' : 'https://fixture.example/'; }
    }
}
namespace Joomla\CMS\Plugin {
    final class PluginHelper { public static function importPlugin($group) {} }
}
namespace Joomla\CMS\MVC\Controller {
    class BaseController {
        public $input, $cachable = null, $redirect = null;
        public function display($cachable = false, $urlparams = array()) { $this->cachable = $cachable; }
        public function setRedirect($url, $msg = null) { $this->redirect = $url; }
    }
}
namespace Joomla\Filesystem {
    final class Path { public static function clean($path) { return preg_replace('#[/\\\\]+#', DIRECTORY_SEPARATOR, $path); } }
    final class File {
        public static function copy($from, $to) { return copy($from, $to); }
        public static function delete($file) { return unlink($file); }
    }
    final class Folder {
        private static function entries($path, $filter, $dirs) {
            $found = array();
            foreach (scandir($path) as $name) {
                if ($name === '.' || $name === '..' || is_dir($path . DIRECTORY_SEPARATOR . $name) !== $dirs) continue;
                if (preg_match('/' . $filter . '/', $name)) $found[] = $path . DIRECTORY_SEPARATOR . $name;
            }
            return $found;
        }
        public static function files($path, $filter = '.', $recurse = false, $full = false) { return self::entries($path, $filter, false); }
        public static function folders($path, $filter = '.', $recurse = false, $full = false) { return self::entries($path, $filter, true); }
        public static function delete($path) {
            foreach (array_diff(scandir($path), array('.', '..')) as $name) {
                $entry = $path . DIRECTORY_SEPARATOR . $name;
                is_dir($entry) ? self::delete($entry) : unlink($entry);
            }
            return rmdir($path);
        }
    }
}
namespace {
    use Joomla\CMS\Component\ComponentHelper;
    use Joomla\CMS\Factory;
    use Joomla\Registry\Registry;

    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('JDEBUG', false);
    define('FLEXI_J40GE', true);
    define('JPATH_ROOT', dirname(__DIR__));

    // Scratch folders: required stub paths, a "secure" files folder and the temporary folder of the archives
    $child   = ($argv[1] ?? '') === '--archive-child';
    $scratch = $child ? $argv[2] : sys_get_temp_dir() . DS . 'fc-controller-leads-' . bin2hex(random_bytes(6));
    define('JPATH_SITE', $scratch . DS . 'site');
    define('JPATH_ADMINISTRATOR', $scratch . DS . 'admin');
    define('JPATH_BASE', JPATH_SITE);
    define('COM_FLEXICONTENT_FILEPATH', $scratch . DS . 'secure');
    define('COM_FLEXICONTENT_MEDIAPATH', $scratch . DS . 'media');

    function removeTree($path) {
        if (!is_dir($path)) return;
        foreach (array_diff(scandir($path), array('.', '..')) as $name) {
            is_dir($path . DS . $name) ? removeTree($path . DS . $name) : unlink($path . DS . $name);
        }
        rmdir($path);
    }

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

    if (!$child) {
        foreach (array('secure', 'media', 'tmp', 'site/components/com_flexicontent/classes/helpers', 'admin/components/com_flexicontent/tables') as $dir) {
            mkdir($scratch . DS . str_replace('/', DS, $dir), 0700, true);
        }
        // The controller loads these files by path, the classes are the doubles below
        file_put_contents(JPATH_SITE . '/components/com_flexicontent/classes/helpers/downloadlimit.php', "<?php\n");
        foreach (array('flexicontent_items', 'flexicontent_fields', 'flexicontent_files') as $table) {
            file_put_contents(JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/' . $table . '.php', "<?php\n");
        }
        file_put_contents(COM_FLEXICONTENT_FILEPATH . DS . 'open.txt', 'open file');
        file_put_contents(COM_FLEXICONTENT_FILEPATH . DS . 'second.txt', 'second file');
        file_put_contents(COM_FLEXICONTENT_FILEPATH . DS . 'orphan.txt', 'orphan file');
        register_shutdown_function('removeTree', $scratch);
    }

    require JPATH_ROOT . '/site/classes/helpers/security.php';

    final class StopRequest extends RuntimeException {}

    final class FakeInput {
        public function __construct(public array $data) {}
        public function get($key, $default = null, $filter = 'cmd') {
            if (!isset($this->data[$key])) return $default;
            return $filter === 'int' ? (int) $this->data[$key] : $this->data[$key];
        }
        public function getString($key, $default = null) { return $this->data[$key] ?? $default; }
        public function set($key, $value) { $this->data[$key] = $value; }
        public function getArray() { return $this->data; }
    }

    final class FakeUser {
        public $name = 'Dummy', $username = 'dummy', $guest;
        public function __construct(public $id = 0, public array $levels = array(1), public array $rights = array()) { $this->guest = !$id; }
        public function get($key) { return $this->$key; }
        public function getAuthorisedViewLevels() { return $this->levels; }
        public function authorise($action, $asset = null) { return in_array($action, $this->rights, true); }
    }

    final class FakeSession {
        public function __construct(public array $data = array()) {}
        public function has($key, $namespace = 'default') { return isset($this->data[$key]); }
        public function get($key, $default = null, $namespace = 'default') { return $this->data[$key] ?? $default; }
    }

    final class FakeApp {
        public $input, $messages = array(), $events = array(), $archive_report = false;
        public function __construct(public $user, public $session, public $tmp_path) {}
        public function getIdentity() { return $this->user; }
        public function getSession() { return $this->session; }
        public function getMenu() { return new class { public function getActive() { return null; } }; }
        public function get($key, $default = null) { return $key === 'tmp_path' ? $this->tmp_path : $default; }
        public function enqueueMessage($message, $type = 'message') { $this->messages[] = $message; }
        public function setHeader(...$args) {}
        public function triggerEvent($name, $args) { $this->events[] = (int) ($args[0]->id ?? 0); return array(); }
        public function redirect($url) { throw new StopRequest('redirect:' . $url); }
        public function close() {
            if ($this->archive_report) {
                // Child process: report what was sent and what is in the temporary folder, then end the request for real
                $body = ob_get_clean();
                echo json_encode(array('zip' => substr($body, 0, 2) === 'PK', 'entries' => array_values(array_diff(scandir($this->tmp_path), array('.', '..')))));
                exit(0);
            }
            throw new StopRequest('close');
        }
    }

    /** Scripted database: answers the controller's queries from dummy records. */
    final class FakeDb {
        public $files = array(), $fields = array(), $items = array(), $types = array(), $cats = array(), $rels = array(), $usage = array(), $coupons = array();
        public $levels = array(0, 1), $executed = array();
        private $sql = '', $limit = 0;

        public function setQuery($sql, $offset = 0, $limit = 0) { $this->sql = (string) $sql; $this->limit = $limit; return $this; }
        public function Quote($text, $escape = true) { return "'" . $text . "'"; }
        public function escape($text, $extra = false) { return $text; }
        public function getNullDate() { return '0000-00-00 00:00:00'; }
        public function execute() { $this->executed[] = $this->sql; return true; }
        public function getAffectedRows() { return 1; }
        public function loadObject() { $rows = $this->rows(); return $rows ? $rows[0] : null; }
        public function loadObjectList() { return $this->rows(); }
        public function loadColumn() { return array_map(static function ($row) { return current((array) $row); }, $this->rows()); }

        private function allowed($level) { return in_array((int) $level, $this->levels, true); }
        private function number($pattern) { return preg_match($pattern, $this->sql, $m) ? (int) $m[1] : null; }
        private function holders($file_id) {
            return array_values(array_filter($this->rels, function ($rel) use ($file_id) {
                return $rel->value === (string) $file_id && in_array($this->fields[$rel->field_id]->field_type, array('file', 'mediafile'), true);
            }));
        }

        private function rows() {
            $sql = $this->sql;

            if (strpos($sql, 'SELECT attribs, name, field_type FROM #__flexicontent_fields WHERE id = ') === 0) {
                $id = $this->number('/WHERE id = (\d+)/');
                return isset($this->fields[$id]) ? array($this->fields[$id]) : array();
            }

            if (strpos($sql, 'FROM #__flexicontent_download_coupons') !== false) {
                $id = $this->number('/WHERE id = (\d+)/');
                return isset($this->coupons[$id]) && strpos($sql, "token = '" . $this->coupons[$id]->token . "'") !== false ? array($this->coupons[$id]) : array();
            }

            // The file record of the download task
            if (strpos($sql, ' FROM #__flexicontent_files AS f ') !== false) {
                $file  = $this->files[$this->number('/ AND f\.id = (\d+)/')] ?? null;
                $item  = ($id = $this->number('/ AND i\.id = (\d+)/')) !== null ? ($this->items[$id] ?? false) : null;
                $field = ($id = $this->number('/ AND fi\.id = (\d+)/')) !== null ? ($this->fields[$id] ?? false) : null;
                if (!$file || !$file->published || $item === false || $field === false) return array();

                if (preg_match('/INNER JOIN #__flexicontent_fields_item_relations AS rel ON\s+rel\.field_id = (\d+) AND rel\.value = (\d+) AND rel\.item_id = (\d+)/', $sql, $m)) {
                    $held = array_filter($this->rels, static function ($rel) use ($m) { return $rel->field_id == $m[1] && $rel->value === $m[2] && $rel->item_id == $m[3]; });
                    if (!$held) return array();
                }

                $row = clone $file;
                $row->history_id = null;
                if ($item) {
                    $row->item_title = $item->title; $row->item_state = $item->state; $row->item_created_by = $item->created_by;
                    $row->item_access = $item->access; $row->item_type_id = $item->type_id; $row->item_language = '*';
                    $row->item_introtext = $row->item_fulltext = ''; $row->item_owner_email = 'owner@example.invalid';
                    $row->itemslug = $item->id; $row->catslug = $item->catid;
                }
                if (strpos($sql, 'has_file_access') !== false) {
                    $row->has_file_access = (int) $this->allowed($file->access);
                    $direct = strpos($sql, '1 AS has_field_access, 1 AS has_content_access') !== false;
                    $row->has_field_access = $direct ? 1 : (int) $this->allowed($field->access);
                    $row->has_content_access = $direct ? 1 : (int) ($this->allowed($item->access)
                        && $this->allowed($this->types[$item->type_id]->access) && $this->allowed($this->cats[$item->catid]->access));
                }
                return array($row);
            }

            // Direct file access: every assignment of the file
            if (strpos($sql, 'UNION ALL') !== false && strpos($sql, '#__flexicontent_file_usage') !== false) {
                $file_id = $this->number("/rel\.value = '(\d+)'/");
                $rows = array();
                foreach ($this->holders($file_id) as $rel) {
                    $field = $this->fields[$rel->field_id];
                    $rows[] = (object) array('item_id' => $rel->item_id, 'field_access' => $field->access, 'field_id' => $rel->field_id, 'field_type' => $field->field_type);
                }
                foreach ($this->usage as $usage) {
                    if ($usage->file_id == $file_id) $rows[] = (object) array('item_id' => $usage->id, 'field_access' => 0, 'field_id' => 0, 'field_type' => '');
                }
                // A query that does not return the field can not tell which assignment gave access
                if (strpos($sql, 'rel.field_id, fi.field_type') === false) foreach ($rows as $row) unset($row->field_id, $row->field_type);
                return $rows;
            }

            // The field and item that hold a file, the named field and item first
            if (strpos($sql, 'SELECT rel.field_id, rel.item_id FROM #__flexicontent_fields_item_relations AS rel') === 0) {
                $rows = $this->holders($this->number("/rel\.value = '(\d+)'/"));
                $field_id = $this->number('/ORDER BY \(rel\.field_id = (\d+)\) DESC/');
                $item_id  = $this->number('/, \(rel\.item_id = (\d+)\) DESC/');
                usort($rows, static function ($a, $b) use ($field_id, $item_id) {
                    return array($b->field_id == $field_id, $b->item_id == $item_id, $a->field_id, $a->item_id) <=> array($a->field_id == $field_id, $a->item_id == $item_id, $b->field_id, $b->item_id);
                });
                $rows = array_map(static function ($rel) { return (object) array('field_id' => $rel->field_id, 'item_id' => $rel->item_id); }, $rows);
                return $this->limit ? array_slice($rows, 0, $this->limit) : $rows;
            }

            // Item, its categories and their chain (viewable assignment check)
            if (strpos($sql, 'SELECT i.id, i.state, i.created_by, i.access, i.catid, i.publish_up, i.publish_down, ty.access AS type_access') === 0) {
                $item = $this->items[$this->number('/WHERE i\.id = (\d+)/')] ?? null;
                if (!$item) return array();
                $row = clone $item;
                $row->type_access = $this->types[$item->type_id]->access;
                return array($row);
            }
            if (strpos($sql, 'SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ') === 0) {
                return array();
            }
            if (strpos($sql, 'SELECT c.id, SUM(CASE WHEN parent.id IS NOT NULL AND parent.access NOT IN') === 0) {
                preg_match('/WHERE c\.id IN \(([\d,]+)\)/', $sql, $m);
                $rows = array();
                foreach (explode(',', $m[1]) as $id) {
                    $row = (object) array('id' => (int) $id, 'blocked_access' => 0, 'blocked_publication' => 0);
                    for ($cat = $this->cats[$id] ?? null; $cat; $cat = $cat->parent ? $this->cats[$cat->parent] : null) {
                        $row->blocked_access += (int) !$this->allowed($cat->access);
                        $row->blocked_publication += (int) ($cat->published != 1);
                    }
                    $rows[] = $row;
                }
                return $rows;
            }

            // The weblink value
            if (strpos($sql, 'SELECT value') === 0 && strpos($sql, "fi.field_type = 'weblink'") !== false) {
                $item_id = $this->number('/rel\.item_id = (\d+)/'); $field_id = $this->number('/rel\.field_id = (\d+)/'); $order = $this->number('/rel\.valueorder = (\d+)/');
                $field = $this->fields[$field_id] ?? null; $item = $this->items[$item_id] ?? null;
                if (!$field || !$item || $field->field_type !== 'weblink') return array();
                if (strpos($sql, 'fi.published = 1') !== false && !$field->published) return array();
                foreach ($this->rels as $rel) {
                    if ($rel->field_id == $field_id && $rel->item_id == $item_id && $rel->valueorder == $order) {
                        return array((object) array(
                            'value' => $rel->value,
                            'has_field_access' => (int) $this->allowed($field->access),
                            'has_content_access' => (int) ($this->allowed($item->access) && $this->allowed($this->types[$item->type_id]->access) && $this->allowed($this->cats[$item->catid]->access)),
                        ));
                    }
                }
                return array();
            }

            throw new LogicException('Query without a scripted answer: ' . $sql);
        }
    }

    // Table and helper doubles used by the download and weblink tasks
    #[AllowDynamicProperties]
    class FakeTable {
        public $id = null, $attribs = null, $field_type = null, $type_id = 0;
        public function __construct($db) {}
        public function load($id) {
            $record = Factory::$db->{static::SOURCE}[(int) $id] ?? null;
            foreach ((array) $record as $key => $value) $this->$key = $value;
            return (bool) $record;
        }
    }
    final class flexicontent_items extends FakeTable { const SOURCE = 'items'; }
    final class flexicontent_fields extends FakeTable { const SOURCE = 'fields'; }
    final class flexicontent_files { public static $hits = array(); public function __construct($db) {} public function hit($id) { self::$hits[] = (int) $id; } }
    final class FlexicontentFields { public static function loadFieldConfig(&$field, &$item) { $field->parameters = new Registry($field->attribs); } }
    final class FLEXIUtilities { public static function funcIsDisabled($name) { return true; } }
    final class flexicontent_upload { public static function getExt($file) { return pathinfo($file, PATHINFO_EXTENSION); } }
    final class flexicontent_db { public static function unserialize($value) { return flexicontent_security::unserialize($value); } }
    final class flexicontent_html {
        public static function getMobileDetector() { return new class { public function isMobile() { return false; } public function isTablet() { return false; } }; }
        public static function make_absolute_url($url) { return $url; }
        public static function getDateFieldDisplay($params, $date, $prefix) { return ""; }
    }

    // The real limit settings resolution, the quota bookkeeping (database) is replaced by a recorder
    eval(str_replace('class flexicontent_downloadlimit', 'class flexicontent_downloadlimit_real',
        substr(file_get_contents(JPATH_ROOT . '/site/classes/helpers/downloadlimit.php'), 5)));
    final class flexicontent_downloadlimit extends flexicontent_downloadlimit_real {
        public static $consumed = array(), $allow = true;
        public static function consume(array $settings, $fileId, $fieldId, $user = null) {
            self::$consumed[] = array('file' => (int) $fileId, 'field' => (int) $fieldId, 'limit' => $settings['groups'][1] ?? null);
            return self::$allow;
        }
        public static function getResetInfo(array $settings, $user = null) { return array('limit' => 1, 'used' => 1, 'window' => 3600, 'reset_in' => 60, 'reset_full_in' => 60); }
    }
    require JPATH_ROOT . '/site/classes/helpers/zip.php';

    $source = file_get_contents(JPATH_ROOT . '/site/controller.php');
    $methods = '';
    foreach (array('display', 'download', 'weblink', '_createFieldItemAccessClause', '_getSafeDownloadPath', '_isDownloadPathInside',
        '_checkDirectFileAccess', '_canViewItemState', '_canViewAssignedItem', '_getFileAssignment') as $name) {
        // A method that does not exist yet (older source) is left out, its callers then fail their checks
        if (strpos($source, 'function ' . $name . '(') !== false) $methods .= 'public ' . methodSource($source, $name) . "\n";
    }
    eval('use Joomla\Filesystem\Path; use Joomla\Database\DatabaseInterface; use Joomla\CMS\Mail\MailerFactoryInterface;
        final class LeadsController extends \Joomla\CMS\MVC\Controller\BaseController { public $refererURL = "https://fixture.example/"; ' . $methods . ' }');

    // Dummy records: field 10 holds file 100 in the public item 1 and is limited, field 11 is another file field without limits
    $limited = array('dl_limit_override' => 1, 'dl_limit_groups' => array(array('group_id' => 1, 'limit' => 3)), 'dl_limit_count_special' => 1);
    $makeDb = static function () use ($limited) {
        $db = new FakeDb;
        foreach (array(100 => 'open.txt', 101 => 'second.txt', 300 => 'orphan.txt') as $id => $name) {
            $db->files[$id] = (object) array('id' => $id, 'filename' => $name, 'filename_original' => $name, 'altname' => $name, 'secure' => 1, 'url' => 0,
                'hits' => 0, 'stamp' => 0, 'size' => 9, 'uploaded_by' => 99, 'published' => 1, 'access' => 1);
        }
        $fields = array(
            10 => array('file', $limited),
            11 => array('file', array('dl_limit_override' => 2)),
            12 => array('text', array()),
            13 => array('file', $limited + array('allowview' => 1)),
            20 => array('weblink', array()),
            21 => array('weblink', array()),
        );
        foreach ($fields as $id => $def) {
            $db->fields[$id] = (object) array('id' => $id, 'attribs' => json_encode($def[1]), 'name' => 'f' . $id, 'field_type' => $def[0], 'access' => 1, 'published' => 1);
        }
        $db->fields[21]->published = 0;
        $db->types[1] = (object) array('access' => 1);
        $db->cats[2] = (object) array('access' => 1, 'published' => 1, 'parent' => 0);
        $db->cats[3] = (object) array('access' => 1, 'published' => 1, 'parent' => 4);
        $db->cats[4] = (object) array('access' => 1, 'published' => 0, 'parent' => 0);
        $never = '0000-00-00 00:00:00';
        $items = array(
            1 => array(1, 2, $never, $never),             // published
            2 => array(0, 2, $never, $never),             // unpublished
            3 => array(-2, 2, $never, $never),            // trashed
            4 => array(1, 2, '2999-01-01 00:00:00', $never),  // scheduled
            5 => array(1, 2, $never, '2000-01-01 00:00:00'),  // expired
            6 => array(1, 3, $never, $never),             // inside an unpublished parent category
        );
        foreach ($items as $id => $def) {
            $db->items[$id] = (object) array('id' => $id, 'title' => 'Item ' . $id, 'state' => $def[0], 'created_by' => 99, 'access' => 1, 'catid' => $def[1],
                'publish_up' => $def[2], 'publish_down' => $def[3], 'type_id' => 1);
        }
        $link = serialize(array('link' => 'https://example.invalid/target', 'title' => 'T', 'hits' => 4));
        $db->rels = array(
            (object) array('field_id' => 10, 'item_id' => 1, 'value' => '100', 'valueorder' => 1),
            (object) array('field_id' => 13, 'item_id' => 1, 'value' => '101', 'valueorder' => 1),
            (object) array('field_id' => 21, 'item_id' => 1, 'value' => $link, 'valueorder' => 1),
        );
        foreach (array_keys($items) as $id) {
            $db->rels[] = (object) array('field_id' => 20, 'item_id' => $id, 'value' => $link, 'valueorder' => 1);
        }
        $db->coupons[7] = (object) array('id' => 7, 'token' => str_repeat('a', 64), 'file_id' => 100, 'has_expired' => 0, 'has_reached_limit' => 0);
        return $db;
    };

    $php_errors = array();
    $request = static function ($method, array $query, $user = null, array $session = array()) use ($makeDb, $scratch, &$php_errors) {
        $_GET = $query;
        $input = new FakeInput($query);
        $app = new FakeApp($user ?: new FakeUser, new FakeSession($session), $scratch . DS . 'tmp');
        $app->input = $input;
        Factory::$app = $app;
        Factory::$db = $makeDb();
        Factory::$db->levels = array_merge(array(0), $app->user->levels);
        flexicontent_downloadlimit::$consumed = array();
        $controller = new LeadsController;
        $controller->input = $input;
        $result = (object) array('stop' => null, 'error' => null, 'controller' => $controller, 'app' => $app, 'db' => Factory::$db);
        ob_start();
        set_error_handler(static function ($severity, $message, $file, $line) use (&$php_errors) { $php_errors[] = $message . ' (line ' . $line . ')'; return true; });
        try { $controller->$method(); }
        catch (StopRequest $e) { $result->stop = $e->getMessage(); }
        catch (Throwable $e) { $result->error = $e; }
        restore_error_handler();
        $result->body = ob_get_clean();
        $result->consumed = flexicontent_downloadlimit::$consumed;
        return $result;
    };

    ComponentHelper::$params = new Registry(array('dl_limit_enable' => 0));

    // Child process of the archive check: one multi-file download that really ends the request
    if ($child) {
        $_GET = array();
        $input = new FakeInput(array('task' => 'download_files', 'id' => '100,101'));
        $app = new FakeApp(new FakeUser(31, array(1)), new FakeSession, $scratch . DS . 'tmp');
        $app->input = $input; $app->archive_report = true;
        Factory::$app = $app; Factory::$db = $makeDb(); Factory::$db->levels = array(0, 1);
        $controller = new LeadsController; $controller->input = $input;
        ob_start();
        $controller->download();
        exit(2);
    }

    // 1. Item view cache: a session edit grant is not a part of the cache id, so its holder is served without the cache
    $view = array('option' => 'com_flexicontent', 'view' => 'item', 'id' => '5');
    $r = $request('display', $view);
    $check($r->error === null && $r->controller->cachable === true, 'item view of a visitor without edit grant stays cachable');
    $r = $request('display', $view, null, array('rendered_uneditable' => array(5 => -1)));
    $check($r->error === null && $r->controller->cachable === false, 'item view of a guest holding a session edit grant is not cached');
    $r = $request('display', $view, null, array('rendered_uneditable' => array(5 => 2)));
    $check($r->controller->cachable === false, 'item view of an edit coupon holder is not cached');
    $r = $request('display', array('view' => 'category', 'cid' => '2'), null, array('rendered_uneditable' => array(5 => 1)));
    $check($r->controller->cachable === false, 'other views of a grant holder are not cached either');
    $r = $request('display', $view, null, array('rendered_uneditable' => array()));
    $check($r->controller->cachable === true, 'an emptied grant list does not disable the cache');
    $r = $request('display', $view, new FakeUser(31));
    $check($r->controller->cachable === true && $r->controller->input->get('__fc_user_id__') === 31, 'logged users keep their own cache id');

    // 2. Download via the field: the limit of the field is applied, also when the file is "viewed" via a field without view button
    $dl = array('task' => 'download', 'id' => '100', 'cid' => '1', 'fid' => '10');
    $r = $request('download', $dl);
    $check($r->stop === 'close' && $r->body === 'open file' && $r->consumed === array(array('file' => 100, 'field' => 10, 'limit' => 3)), 'download via the field is counted and served');
    $r = $request('download', $dl + array('method' => 'view'));
    $check($r->stop === 'close' && $r->consumed === array(array('file' => 100, 'field' => 10, 'limit' => 3)), 'view method on a field without view button is counted');
    flexicontent_downloadlimit::$allow = false;
    $r = $request('download', $dl + array('method' => 'view'));
    $check($r->error instanceof RuntimeException && $r->error->getCode() === 403 && $r->body === '', 'view method is refused when the quota is exhausted');
    flexicontent_downloadlimit::$allow = true;
    $r = $request('download', array('task' => 'download', 'id' => '101', 'cid' => '1', 'fid' => '13', 'method' => 'view'));
    $check($r->stop === 'close' && $r->body === 'second file' && $r->consumed === array(), 'viewing via a field that offers the view button is not counted');
    $r = $request('download', array('task' => 'download', 'id' => '101', 'cid' => '1', 'fid' => '13'));
    $check($r->consumed === array(array('file' => 101, 'field' => 13, 'limit' => 3)), 'downloading via that field is counted');

    // 3. The request can not choose the field whose settings apply
    $expected = array(array('file' => 100, 'field' => 10, 'limit' => 3));
    $r = $request('download', array('task' => 'download_file', 'id' => '100'));
    $check($r->stop === 'close' && $r->body === 'open file' && $r->consumed === $expected && $r->app->events === array(10), 'direct file link: settings of the field that holds the file');
    $r = $request('download', array('task' => 'download_file', 'id' => '100', 'fid' => '11', 'cid' => '1'));
    $check($r->stop === 'close' && $r->consumed === $expected && $r->app->events === array(10), 'direct file link naming a field without limits: still the holding field');
    $r = $request('download', array('task' => 'download_file', 'id' => '100', 'method' => 'view'));
    $check($r->stop === 'close' && $r->consumed === $expected, 'direct file link with the view method is counted');
    $r = $request('download', array('task' => 'download', 'id' => '100', 'cid' => '1', 'fid' => '12'));
    $check($r->stop === 'close' && $r->consumed === $expected && $r->app->events === array(10), 'download naming a field of another kind: still the holding field');
    $coupon = array('conid' => '7', 'contok' => str_repeat('a', 64));
    $r = $request('download', array('task' => 'download', 'id' => '100', 'cid' => '1', 'fid' => '10') + $coupon);
    $check($r->stop === 'close' && $r->consumed === $expected, 'coupon link of the field is counted (count_special)');
    $r = $request('download', array('task' => 'download', 'id' => '100', 'cid' => '2', 'fid' => '11') + $coupon);
    $check($r->stop === 'close' && $r->body === 'open file' && $r->consumed === $expected && $r->app->events === array(10), 'coupon link with another field and item: still the holding field');
    $r = $request('download', array('task' => 'download', 'id' => '300', 'cid' => '1', 'fid' => '11') + $coupon);
    $check($r->error instanceof RuntimeException && $r->error->getCode() === 403, 'coupon still grants only its own file');

    // A file that no file field holds, and files reached by privilege, get the component settings only
    ComponentHelper::$params = new Registry(array('dl_limit_enable' => 1, 'dl_limit_groups' => array(array('group_id' => 1, 'limit' => 50))));
    $r = $request('download', array('task' => 'download_file', 'id' => '300', 'fid' => '11'));
    $check($r->stop === 'close' && $r->body === 'orphan file' && $r->consumed === array(array('file' => 300, 'field' => 0, 'limit' => 50)), 'unassigned file naming a field without limits: component limit');
    $r = $request('download', array('task' => 'download_file', 'id' => '100'), new FakeUser(99));
    $check($r->stop === 'close' && $r->consumed === array(array('file' => 100, 'field' => 0, 'limit' => 50)), 'uploader of the file: component limit, as before');
    ComponentHelper::$params = new Registry(array('dl_limit_enable' => 0));
    $r = $request('download', array('task' => 'download', 'id' => '100', 'cid' => '1', 'fid' => '11'));
    $check($r->stop === null && $r->body === '' && $r->consumed === array(), 'a file field that does not hold the file still gives no access');

    // 4. Weblink task: only for a viewable item and a published field
    $link = static function ($item, $field = 20, $user = null) use ($request) {
        return $request('weblink', array('task' => 'weblink', 'cid' => (string) $item, 'fid' => (string) $field, 'ord' => '1'), $user);
    };
    $updated = static function ($r) { return count(array_filter($r->db->executed, static function ($sql) { return strpos($sql, 'UPDATE #__flexicontent_fields_item_relations') === 0; })); };
    $r = $link(1);
    $check($r->stop === 'close' && $updated($r) === 1 && strpos($r->db->executed[0], '"hits";i:5;') !== false, 'weblink of a published item is followed and counted');
    foreach (array(2 => 'unpublished', 3 => 'trashed', 4 => 'scheduled', 5 => 'expired', 6 => 'in an unpublished parent category') as $item => $label) {
        $r = $link($item);
        $check($r->error === null && strpos((string) $r->stop, 'redirect:') === 0 && $updated($r) === 0, 'weblink of an item that is ' . $label . ' is refused to a guest');
    }
    $r = $link(2, 20, new FakeUser(31, array(1), array('core.edit')));
    $check($r->stop === 'close' && $updated($r) === 1, 'weblink of an unpublished item still works for a user who can edit it');
    $r = $link(2, 20, new FakeUser(99));
    $check($r->stop === 'close' && $updated($r) === 1, 'weblink of an unpublished item still works for its owner');
    $r = $link(2, 20, new FakeUser(31));
    $check(strpos((string) $r->stop, 'redirect:') === 0 && $updated($r) === 0, 'weblink of an unpublished item is refused to another logged user');
    $r = $link(1, 21);
    $check($r->stop === null && $r->controller->redirect === 'index.php' && $updated($r) === 0, 'weblink of an unpublished field is not found');

    $check($php_errors === array(), 'the requests above raise no PHP warning or notice: ' . implode(' | ', array_unique($php_errors)));

    // 5. Multi-file download: the archive has a random name and does not stay in the temporary folder
    if (!extension_loaded('zip') || !function_exists('proc_open')) {
        echo "skipped: archive checks need the zip extension and proc_open\n";
    } else {
        $names = array();
        foreach (array(1, 2) as $run) {
            $process = proc_open(array(PHP_BINARY, __FILE__, '--archive-child', $scratch), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            $code = proc_close($process);
            $report = json_decode($out, true);
            $check($code === 0 && is_array($report) && $report['zip'] === true, 'archive run ' . $run . ' sends a zip archive: ' . trim($out . ' ' . $err));
            $entries = is_array($report) ? $report['entries'] : array();
            $check(count($entries) === 1 && preg_match('/^fcmd_uid_31_\d{4}-\d\d-\d\d__\d\d-\d\d-\d\d_[0-9a-f]{16}\.zip$/', $entries[0]) === 1, 'archive run ' . $run . ' uses a name with a random part, work folder removed');
            $names[] = $entries[0] ?? '';
            $check(array_diff(scandir($scratch . DS . 'tmp'), array('.', '..')) === array(), 'archive run ' . $run . ' leaves nothing in the temporary folder');
        }
        $check($names[0] !== $names[1] && substr($names[0], -20) !== substr($names[1], -20), 'archive names of two downloads differ in their random part');
    }

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
