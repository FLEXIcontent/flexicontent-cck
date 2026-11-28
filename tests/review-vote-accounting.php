<?php
/**
 * Reviews and the votes of the reviewed item: the rating of a review is removed from the votes of the item only if
 * it was added to them. Runs the real voting, review form, moderation and back-end methods with dummy records;
 * no Joomla bootstrap, the database is a double that keeps the vote tables in memory.
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
    final class ComponentHelper { public static function getParams($option) { return new \Joomla\Registry\Registry; } }
}
namespace Joomla\CMS\Language {
    final class Text { public static function _($key) { return $key; } public static function sprintf($key, ...$args) { return $key; } }
}
namespace Joomla\CMS\Session {
    final class Session { public static function checkToken($method = 'post') { return true; } }
}
namespace Joomla\Utilities {
    final class ArrayHelper { public static function toInteger($values) { return array_map('intval', (array) $values); } }
}
namespace {
    use Joomla\CMS\Factory;
    use Joomla\Registry\Registry;

    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('JPATH_ROOT', dirname(__DIR__));

    // The controllers load the table classes by path, the classes are the doubles below
    $scratch = sys_get_temp_dir() . DS . 'fc-review-votes-' . bin2hex(random_bytes(6));
    define('JPATH_ADMINISTRATOR', $scratch);
    define('JPATH_BASE', $scratch);
    mkdir($scratch . '/components/com_flexicontent/tables', 0700, true);
    foreach (array('flexicontent_items', 'flexicontent_reviews') as $table) {
        file_put_contents($scratch . '/components/com_flexicontent/tables/' . $table . '.php', "<?php\n");
    }
    register_shutdown_function(static function () use ($scratch) {
        array_map('unlink', glob($scratch . '/components/com_flexicontent/tables/*.php'));
        foreach (array('/components/com_flexicontent/tables', '/components/com_flexicontent', '/components', '') as $dir) rmdir($scratch . $dir);
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
    function methods($file, array $names, $prefix = 'public ') {
        $source = file_get_contents(JPATH_ROOT . $file);
        $code = '';
        foreach ($names as $name) {
            if (strpos($source, 'function ' . $name . '(') !== false) $code .= $prefix . methodSource($source, $name) . "\n";
        }
        return $code;
    }

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };

    final class JexitStop extends RuntimeException { public $payload; }
    function jexit($payload = '') { $stop = new JexitStop('exit'); $stop->payload = $payload; throw $stop; }

    final class FakeInput {
        public function __construct(public array $data = array()) {}
        public function get($key, $default = null, $filter = 'cmd') {
            if (!isset($this->data[$key])) return $default;
            return $filter === 'int' ? (int) $this->data[$key] : ($filter === 'array' ? (array) $this->data[$key] : $this->data[$key]);
        }
        public function set($key, $value) { $this->data[$key] = $value; }
    }
    final class FakeUser {
        public $guest, $email;
        public function __construct(public $id = 0, public array $rights = array()) { $this->guest = !$id; $this->email = $id ? 'user' . $id . '@example.invalid' : ''; }
        public function get($key) { return $this->$key; }
        public function getAuthorisedViewLevels() { return $this->id ? array(1, 2) : array(1); }
        public function authorise($action, $asset = null) { return in_array($action, $this->rights, true); }
    }
    final class FakeSession {
        public $data = array();
        public function has($key, $namespace = 'default') { return isset($this->data[$key]); }
        public function get($key, $default = null, $namespace = 'default') { return $this->data[$key] ?? $default; }
        public function set($key, $value, $namespace = 'default') { $this->data[$key] = $value; }
    }
    final class FakeApp {
        public $input, $user, $session;
        public function getIdentity() { return $this->user; }
        public function getSession() { return $this->session; }
        public function getLanguage() { return new class { public function load(...$args) { return true; } }; }
        public function enqueueMessage($message, $type = 'message') {}
    }

    /** Database double: the vote tables of the items and the reviews, changed by the statements of the controllers. */
    final class FakeDb {
        public $votes = array('#__content_rating' => array(), '#__flexicontent_items_extravote' => array());
        public $reviews = array(), $review_seq = 0, $items = array(7 => true);
        public $voting = array('rating_resolution' => 5, 'rating_stars' => 5, 'allow_reviews' => 1, 'allow_submit_review_by' => 1, 'submit_acclvl' => 1, 'do_approval' => 0);
        private $sql = '';

        public function setQuery($sql) { $this->sql = trim(preg_replace('/\s+/', ' ', (string) $sql)); return $this; }
        public function Quote($text) { return "'" . $text . "'"; }
        public function getQuery($new = false) {
            return new class {
                private $parts = array('select' => '', 'from' => '', 'where' => array());
                public function select($s) { $this->parts['select'] = $s; return $this; }
                public function from($s) { $this->parts['from'] = $s; return $this; }
                public function where($s) { $this->parts['where'][] = $s; return $this; }
                public function __toString() { return 'SELECT ' . $this->parts['select'] . ' FROM ' . $this->parts['from'] . ' WHERE ' . implode(' AND ', $this->parts['where']); }
            };
        }
        public function row($table, $content_id, $field_id = 0) { return $this->votes[$table][$content_id . ':' . (int) $field_id] ?? null; }
        public function seed($table, $content_id, $field_id, $sum, $count, $lastip) {
            $this->votes[$table][$content_id . ':' . (int) $field_id] = (object) array('content_id' => $content_id, 'field_id' => (int) $field_id, 'rating_sum' => $sum, 'rating_count' => $count, 'lastip' => $lastip);
        }
        private function target() {
            preg_match('/(#__content_rating|#__flexicontent_items_extravote)/', $this->sql, $t);
            preg_match('/content_id = (\d+)/', $this->sql, $c);
            $f = preg_match('/field_id = (\d+)/', $this->sql, $m) ? (int) $m[1] : 0;
            return array($t[1], (int) $c[1], $f);
        }

        public function execute() {
            $sql = $this->sql;
            list($table, $content_id, $field_id) = $this->target();

            if (strpos($sql, 'INSERT ') === 0) {
                preg_match('/rating_sum = (\d+)/', $sql, $s); preg_match('/rating_count = (\d+)/', $sql, $n); preg_match("/lastip = '([^']*)'/", $sql, $ip);
                if ($this->row($table, $content_id, $field_id)) throw new LogicException('Duplicate votes row: ' . $sql);
                $this->seed($table, $content_id, $field_id, (int) $s[1], (int) $n[1], $ip[1]);
                return true;
            }
            if (preg_match("/^UPDATE (#__content_rating|#__flexicontent_items_extravote) SET lastip = '' WHERE content_id = (\d+) AND lastip = '([^']*)'$/", $sql, $m)) {
                foreach ($this->votes[$m[1]] as $row) { if ($row->content_id == $m[2] && $row->lastip === $m[3]) $row->lastip = ''; }
                return true;
            }
            if (strpos($sql, 'UPDATE ') === 0 && ($row = $this->row($table, $content_id, $field_id))) {
                foreach (array('rating_sum', 'rating_count') as $col) {
                    if (preg_match('/' . $col . ' = GREATEST\(CAST\(' . $col . ' AS SIGNED\) ([+-]) (-?\d+), 0\)/', $sql, $m)) {
                        $row->$col = max(0, $row->$col + ($m[1] === '-' ? -1 : 1) * (int) $m[2]);
                    } elseif (preg_match('/' . $col . ' = ' . $col . ' \+ (-?\d+)/', $sql, $m)) {
                        $row->$col += (int) $m[1];
                    } else {
                        throw new LogicException('Votes update without ' . $col . ': ' . $sql);
                    }
                }
                if (preg_match("/lastip = IF\(lastip = '([^']*)', '', lastip\)/", $sql, $m)) { if ($row->lastip === $m[1]) $row->lastip = ''; }
                elseif (preg_match("/lastip = '([^']*)'/", $sql, $m)) { $row->lastip = $m[1]; }
                return true;
            }
            if (strpos($sql, 'UPDATE ') === 0) return true;
            throw new LogicException('Statement without a scripted answer: ' . $sql);
        }

        public function loadObject() {
            if (strpos($this->sql, "SELECT * FROM #__flexicontent_fields WHERE field_type = 'voting'") === 0) {
                return (object) array('id' => 9, 'field_type' => 'voting', 'attribs' => json_encode($this->voting));
            }
            if (preg_match('/^SELECT \* FROM (#__content_rating|#__flexicontent_items_extravote) AS a WHERE content_id = \d+( AND field_id = \d+)?$/', $this->sql)) {
                list($table, $content_id, $field_id) = $this->target();
                $row = $this->row($table, $content_id, $field_id);
                return $row ? clone $row : null;
            }
            if (strpos($this->sql, 'SELECT id FROM #__users WHERE email = ') === 0) return null;
            throw new LogicException('Query without a scripted answer: ' . $this->sql);
        }
        public function loadResult() {
            if (preg_match('/^SELECT rating_count FROM #__content_rating WHERE content_id = (\d+)$/', $this->sql, $m)) {
                $row = $this->row('#__content_rating', (int) $m[1]);
                return $row ? $row->rating_count : null;
            }
            if (strpos($this->sql, 'SELECT title FROM #__viewlevels') === 0) return 'Special';
            if (preg_match('/^SELECT COUNT\(\*\) FROM (#__content_rating|#__flexicontent_items_extravote) WHERE content_id = \d+( AND field_id = \d+)?$/', $this->sql)) {
                list($table, $content_id, $field_id) = $this->target();
                return $this->row($table, $content_id, $field_id) ? 1 : 0;
            }
            if (preg_match("/^SELECT COUNT\(\*\) FROM #__flexicontent_reviews WHERE content_id = (\d+) AND type = 'item' AND email = '([^']*)'$/", $this->sql, $m)) {
                return count(array_filter($this->reviews, static function ($r) use ($m) { return $r->content_id == $m[1] && $r->email === $m[2]; }));
            }
            throw new LogicException('Query without a scripted answer: ' . $this->sql);
        }
        public function loadObjectList($key = '') {
            if (preg_match('/^SELECT field_id, rating_count FROM #__flexicontent_items_extravote WHERE content_id = (\d+)$/', $this->sql, $m)) {
                $rows = array();
                foreach ($this->votes['#__flexicontent_items_extravote'] as $row) {
                    if ($row->content_id == $m[1]) $rows[$row->$key] = (object) array('field_id' => $row->field_id, 'rating_count' => $row->rating_count);
                }
                return $rows;
            }
            if (preg_match('/^SELECT (.+) FROM #__flexicontent_reviews WHERE id IN \(([\d,]+)\) AND content_id > 0 AND average_rating > 0$/', $this->sql, $m)) {
                $rows = array();
                foreach (explode(',', $m[2]) as $id) {
                    if (!isset($this->reviews[$id]) || $this->reviews[$id]->average_rating <= 0) continue;
                    $row = clone $this->reviews[$id];
                    // A statement that names its columns gets only these columns
                    if ($m[1] !== '*') foreach (array_keys(get_object_vars($row)) as $col) { if (!in_array($col, array_map('trim', explode(',', $m[1])), true)) unset($row->$col); }
                    $rows[$row->$key] = $row;
                }
                return $rows;
            }
            throw new LogicException('Query without a scripted answer: ' . $this->sql);
        }
        public function loadColumn() {
            if (preg_match('/^SELECT id FROM #__flexicontent_reviews WHERE id IN \(([\d,]+)\)$/', $this->sql, $m)) {
                return array_values(array_filter(explode(',', $m[1]), function ($id) { return isset($this->reviews[$id]); }));
            }
            throw new LogicException('Query without a scripted answer: ' . $this->sql);
        }
    }

    // Table doubles. Like Joomla's table, store() does not write properties that are null
    #[AllowDynamicProperties]
    class FakeReviewsTable {
        const COLUMNS = array('id' => null, 'content_id' => 0, 'type' => 'item', 'average_rating' => 0, 'custom_ratings' => null, 'user_id' => 0, 'name' => '',
            'email' => '', 'title' => null, 'title_old' => null, 'text' => null, 'text_old' => null, 'state' => 0, 'approved' => 0, 'verified' => 0, 'attribs' => null);
        public function __construct($db = null) { foreach (static::COLUMNS as $col => $default) $this->$col = $default; }
        public function load($keys) {
            foreach (Factory::$db->reviews as $row) {
                $match = is_array($keys) ? !array_diff_assoc($keys, array_intersect_key((array) $row, $keys)) : $row->id == $keys;
                if ($match) { foreach ((array) $row as $col => $value) $this->$col = $value; return true; }
            }
            return false;
        }
        public function reset() { foreach (static::COLUMNS as $col => $default) { if ($col !== 'id') $this->$col = $default; } }
        public function check() { return true; }
        public function getError() { return ''; }
        public function store($updateNulls = false) {
            if (!$this->id) { $this->id = ++Factory::$db->review_seq; Factory::$db->reviews[$this->id] = (object) static::COLUMNS; }
            foreach (array_keys(static::COLUMNS) as $col) { if ($this->$col !== null) Factory::$db->reviews[$this->id]->$col = $this->$col; }
            return true;
        }
    }
    // The real helpers of the reviews table (a source without one of them behaves as before: every rating is counted, nothing is recorded)
    $helpers = '';
    foreach (array('isVoteCounted' => '($attribs) { return true; }', 'setVoteCounted' => '($review, $counted) {}',
        'hasCharVotes' => '($attribs) { return false; }', 'setCharVotes' => '($review, $stored) {}') as $name => $fallback) {
        $helpers .= methods('/admin/tables/flexicontent_reviews.php', array($name), 'public static ') ?: 'public static function ' . $name . $fallback;
    }
    eval('final class flexicontent_reviews extends FakeReviewsTable { ' . $helpers . ' }');
    final class flexicontent_items { public $type_id = 1, $id = 0; public function __construct($db = null) {} public function load($id) { $this->id = (int) $id; return isset(Factory::$db->items[(int) $id]); } public function getError() { return 'not found'; } }

    final class FlexicontentFields { public static function loadFieldConfig(&$field, &$item) { $field->parameters = new Registry($field->attribs); } }
    final class FLEXIUtilities { public static function getCache($group = '', $client = 0) { return new class { public function clean($group = null) {} }; } }
    eval('final class flexicontent_html {
        public static function dataFilter($value, $maxlength = 0, $type = "STRING", $decode = 0) { return trim(strip_tags((string) $value)); }
        public static function getVotingFieldConfig() { return (new FakeReviewModel)->getVotingReviewsField(); }
        public static ' . methodSource(file_get_contents(JPATH_ROOT . '/site/classes/helpers/html.php'), 'getVotingCharacteristics') . ' }');

    // Back-end reviews controller: the real methods, on top of doubles for the base controller and the review model
    final class FakeReviewModel {
        public function __construct(public $record = null) {}
        public function get($property, $default = null) {
            if (!$this->record || !isset($this->record->$property)) return $default;
            // The model keeps the attributes of a loaded record as a registry object
            return $property === 'attribs' ? new Registry($this->record->$property) : $this->record->$property;
        }
        public function getVotingReviewsField($item = null, $setAsDefault = false) {
            $field = Factory::$db->setQuery("SELECT * FROM #__flexicontent_fields WHERE field_type = 'voting'")->loadObject();
            FlexicontentFields::loadFieldConfig($field, $item);
            return $field;
        }
    }
    class FakeAdminBase {
        public $input, $canManage = true, $record_name = 'review', $records_dbtbl = 'flexicontent_reviews', $model = null;
        protected $voteInternal = false;
        public function __construct() { $this->input = Factory::$app->input; }
        public function getModel($name = '') { return $this->model ?: new FakeReviewModel; }
        public function remove() { foreach ($this->input->get('cid', array(), 'array') as $id) unset(Factory::$db->reviews[(int) $id]); return true; }
    }
    eval('use Joomla\Utilities\ArrayHelper;
        final class FlexicontentControllerReviews extends FakeAdminBase { ' . methods('/admin/controllers/reviews.php', array('setVoteInternal', 'remove', '_beforeModelStore',
            'setrating', '_applyRatingChange', '_applyCompositeChange', 'changeReviewVotes', 'ajaxvote', '_getUserRating', '_storeUserRating', '_ajaxvote_error')) . ' }');

    // Site controller: the real review form, own delete and moderation methods
    eval('use Joomla\Database\DatabaseInterface;
        final class SiteReviews { public $input; public function __construct() { $this->input = Joomla\CMS\Factory::$app->input; } '
        . methods('/site/controller.php', array('storereviewform', 'deletereviewform', 'trashreview', 'moderateReview', 'revertReviewVote', 'getReviewResolution',
            'getReviewStars', 'getReviewMaxLength', 'loadReviewLanguage', 'reviewPrepare')) . ' }');

    // One visitor: an account (or a guest), an address and a browser session
    final class Visitor {
        public $session;
        public function __construct(public FakeUser $user, public $ip) { $this->session = new FakeSession; }
        public function newSession() { $this->session = new FakeSession; return $this; }
    }
    $php_errors = array();
    $run = static function (Visitor $who, $class, $method, array $request, array $args = array()) use (&$php_errors) {
        $app = new FakeApp; $app->user = $who->user; $app->session = $who->session; $app->input = new FakeInput($request);
        Factory::$app = $app;
        $_SERVER['REMOTE_ADDR'] = $who->ip;
        set_error_handler(static function ($severity, $message, $file, $line) use (&$php_errors) {
            // Not the warnings that the code silences itself (@)
            if (error_reporting() & $severity) $php_errors[] = $message . ' (line ' . $line . ')';
            return true;
        });
        try { $controller = new $class; $controller->$method(...$args); $reply = null; }
        catch (JexitStop $stop) { $reply = json_decode($stop->payload, true); }
        finally { restore_error_handler(); }
        return $reply;
    };
    $star = static function (Visitor $who, $rating, $xid = 'main') use ($run) {
        $reply = $run($who, 'FlexicontentControllerReviews', 'ajaxvote', array('cid' => 7, 'xid' => $xid, 'user_rating' => $rating));
        return strpos(json_encode($reply), 'FLEXI_YOU_HAVE_ALREADY_VOTED') === false;
    };
    $review = static function (Visitor $who, $rating) use ($run) {
        $request = array('content_id' => 7, 'review_type' => 'item', 'title' => 'Title', 'text' => 'Text of the review', 'name' => 'Guest', 'email' => 'guest-' . $who->ip . '@example.invalid');
        $request += is_array($rating) ? array('xrating' => $rating) : array('rating' => $rating);
        $reply = $run($who, 'SiteReviews', 'storereviewform', $request);
        return is_array($reply) && empty($reply['error']);
    };
    $deleteOwn = static function (Visitor $who) use ($run) { $reply = $run($who, 'SiteReviews', 'deletereviewform', array('content_id' => 7, 'review_type' => 'item')); return is_array($reply) && empty($reply['error']); };
    $trash = static function (Visitor $who, $id) use ($run) { $reply = $run($who, 'SiteReviews', 'trashreview', array('review_id' => $id)); return is_array($reply) && empty($reply['error']); };
    $remove = static function (array $ids) use ($run) { $run(new Visitor(new FakeUser(42), '203.0.113.1'), 'FlexicontentControllerReviews', 'remove', array('cid' => $ids)); };
    $setRating = static function ($id, $percent) use ($run) { $reply = $run(new Visitor(new FakeUser(42), '203.0.113.1'), 'FlexicontentControllerReviews', 'setrating', array('id' => $id, 'rating' => $percent)); return is_array($reply) && empty($reply['error']); };
    // A manager saves the review form of the back-end with other ratings (the form data are validated by the caller)
    $managerSave = static function ($id, array $validated) {
        Factory::$app = new FakeApp; Factory::$app->user = new FakeUser(42); Factory::$app->session = new FakeSession; Factory::$app->input = new FakeInput;
        $controller = new FlexicontentControllerReviews;
        $controller->model = new FakeReviewModel($id ? Factory::$db->reviews[$id] : null);
        $data = array();
        $controller->_beforeModelStore($validated, $data, $controller->model);
        if (!$id) {
            $id = ++Factory::$db->review_seq;
            Factory::$db->reviews[$id] = (object) (array('id' => $id, 'state' => 1) + FakeReviewsTable::COLUMNS);
        }
        foreach ($validated as $col => $value) { if ($col !== 'email' && $col !== 'user_id') Factory::$db->reviews[$id]->$col = $value; }
        return $id;
    };

    $world = static function (array $voting = array()) {
        Factory::$db = new FakeDb;
        Factory::$db->voting = $voting + Factory::$db->voting;
        Factory::$db->seed('#__content_rating', 7, 0, 20, 5, '198.51.100.9');
        return Factory::$db;
    };
    $main = static function () { $row = Factory::$db->row('#__content_rating', 7); return $row->rating_sum . '/' . $row->rating_count; };
    $ownReview = static function (Visitor $who) { foreach (Factory::$db->reviews as $r) { if ($r->user_id == $who->user->id && $who->user->id) return $r; } return null; };
    $lastReview = static function () { return Factory::$db->reviews[Factory::$db->review_seq]; };
    $counted = static function ($row) { return flexicontent_reviews::isVoteCounted($row->attribs); };
    $ip = '192.0.2.10';

    // 1. The recorded value: only an explicit "not counted" is not counted
    $check(flexicontent_reviews::isVoteCounted(null) && flexicontent_reviews::isVoteCounted('') && flexicontent_reviews::isVoteCounted('{}')
        && flexicontent_reviews::isVoteCounted('not json') && flexicontent_reviews::isVoteCounted('{"vote_counted":1}') && flexicontent_reviews::isVoteCounted(new Registry('{"other":1}')),
        'a review without the recorded value has its rating counted');
    $check(!flexicontent_reviews::isVoteCounted('{"vote_counted":0}') && !flexicontent_reviews::isVoteCounted(new Registry('{"vote_counted":0}')) && !flexicontent_reviews::isVoteCounted((object) array('vote_counted' => 0)),
        'a review recorded as not counted is not counted');
    $row = new flexicontent_reviews; $row->attribs = '{"other":1}';
    flexicontent_reviews::setVoteCounted($row, false);
    $check(json_decode($row->attribs, true) === array('other' => 1, 'vote_counted' => 0), 'recording "not counted" keeps other attributes');
    flexicontent_reviews::setVoteCounted($row, true);
    $check($row->attribs === '{"other":1}', 'recording "counted" removes the value only');
    $row->attribs = null; flexicontent_reviews::setVoteCounted($row, true);
    $check($row->attribs === null, 'a counted review of an older version is left untouched');
    flexicontent_reviews::setVoteCounted($row, false); flexicontent_reviews::setVoteCounted($row, true);
    $check($row->attribs === '', 'the removed value is stored as an empty text, which the table does write');
    $bare = new stdClass; flexicontent_reviews::setVoteCounted($bare, false);
    $check(!property_exists($bare, 'attribs'), 'a table without the attributes column is not given one');

    // 2. Rating counted when the review is saved: it is removed when the review is deleted
    $world();
    $user = new Visitor(new FakeUser(31), $ip);
    $check($review($user, 5) && $main() === '25/6' && $counted($ownReview($user)) && $ownReview($user)->average_rating === 100, 'review of a new visitor is a vote of the item');
    $check($deleteOwn($user) && $main() === '20/5', 'deleting it removes its vote');

    // 3. Same address has just voted: the review is saved with its stars, but it is not a second vote, and deleting it removes nothing
    $world();
    $guest = new Visitor(new FakeUser, $ip);
    $user = new Visitor(new FakeUser(31), $ip);
    $check($star($guest, 1) && $main() === '21/6', 'guest vote is counted');
    $check($review($user, 5) && $main() === '21/6', 'review from the address that voted last is not counted again');
    $check($ownReview($user)->average_rating === 100 && !$counted($ownReview($user)), 'the review keeps its stars and is recorded as not counted');
    $check($deleteOwn($user) && $main() === '21/6', 'deleting the review does not remove a vote that it never added');
    $check(Factory::$db->row('#__content_rating', 7)->lastip === $ip && !$star($guest->newSession(), 1) && $main() === '21/6', 'and it does not free the address for another vote');
    $check($ownReview($user)->state === -2 && $counted($ownReview($user)), 'the trashed review carries no recorded value');
    for ($i = 0; $i < 3; $i++) { $review($user->newSession(), 5); $deleteOwn($user); $star($guest->newSession(), 1); }
    $check($main() === '21/6', 'repeating review, delete and vote from one address changes nothing');

    // 4. Not counted review: later changes
    $world();
    $guest = new Visitor(new FakeUser, $ip); $user = new Visitor(new FakeUser(31), $ip); $other = new Visitor(new FakeUser, '192.0.2.77');
    $star($guest, 2); $review($user, 5);
    $check($main() === '22/6' && !$star($user, 4) && $main() === '22/6' && $ownReview($user)->average_rating === 100, 'vote via the stars is refused while the address is the last voter, the review is unchanged');
    $check($review($user, 3) && $main() === '22/6' && $ownReview($user)->average_rating === 60 && !$counted($ownReview($user)), 'editing the review changes its stars, it stays not counted');
    $check($setRating($ownReview($user)->id, 80) && $main() === '22/6' && $ownReview($user)->average_rating === 80 && !$counted($ownReview($user)), 'a manager changing its rating in the list changes no vote');
    $managerSave($ownReview($user)->id, array('average_rating' => 40));
    $check($main() === '22/6', 'a manager saving it with another rating changes no vote');
    $remove(array($ownReview($user)->id));
    $check($main() === '22/6' && !$ownReview($user), 'a manager deleting it removes no vote');

    // 5. Not counted review, and the address is no longer the last voter: the next save or vote of the author is counted, as a new vote
    $world();
    $guest = new Visitor(new FakeUser, $ip); $user = new Visitor(new FakeUser(31), $ip); $other = new Visitor(new FakeUser, '192.0.2.77');
    $star($guest, 2); $review($user, 5); $star($other, 3);
    $check($main() === '25/7' && $review($user->newSession(), 4) && $main() === '29/8' && $counted($ownReview($user)), 'edit after another visitor voted: counted as a new vote');
    $check($review($user->newSession(), 2) && $main() === '27/8', 'further edit changes that vote');
    $check($deleteOwn($user) && $main() === '25/7', 'deleting removes exactly that vote');
    $world();
    $guest = new Visitor(new FakeUser, $ip); $user = new Visitor(new FakeUser(31), $ip);
    $star($guest, 2); $review($user, 5); $star($other->newSession(), 3);
    $check($star($user, 4) && $main() === '29/8' && $counted($ownReview($user)) && $ownReview($user)->average_rating === 80, 'vote via the stars after another visitor voted: counted, the review follows');
    $check($star($user, 1) && $main() === '26/8' && $ownReview($user)->average_rating === 20, 'changing it via the stars changes the vote and the review');
    $remove(array($ownReview($user)->id));
    $check($main() === '25/7', 'a manager deleting the review removes exactly that vote');

    // 6. Counted reviews behave as before: edits and manager changes are changes of the vote
    $world();
    $user = new Visitor(new FakeUser(31), $ip);
    $review($user, 5);
    $check($review($user->newSession(), 3) && $main() === '23/6' && $counted($ownReview($user)), 'edit of a counted review from a new session changes its vote');
    $check($setRating($ownReview($user)->id, 80) && $main() === '24/6', 'manager changes the rating in the list: vote changed');
    $managerSave($ownReview($user)->id, array('average_rating' => 20));
    $check($main() === '21/6', 'manager saves another rating: vote changed');
    $managerSave(0, array('average_rating' => 100, 'content_id' => 7));
    $check($main() === '26/7', 'manager creates a review: vote added');
    $check($deleteOwn($user) && $main() === '25/6', 'own delete removes the vote of the review');

    // 7. Moderation of guest reviews from one address: only the counted one is removed
    $world();
    $first = new Visitor(new FakeUser, $ip); $second = new Visitor(new FakeUser, $ip);
    $moderator = new Visitor(new FakeUser(50, array('core.edit.state')), '203.0.113.5');
    $check($review($first, 5) && $main() === '25/6' && $counted($lastReview()), 'first guest review from an address is counted');
    Factory::$db->reviews[1]->email = 'first@example.invalid';
    $check($review($second, 4) && $main() === '25/6' && !$counted($lastReview()) && $lastReview()->average_rating === 80, 'second guest review from the same address keeps its stars, not counted');
    $check($trash($moderator, 2) && $main() === '25/6', 'trashing the second review removes nothing');
    $check($trash($moderator, 1) && $main() === '20/5', 'trashing the first review removes its vote');

    // 8. Reviews saved by older versions carry no value: their rating is removed as before
    $world();
    Factory::$db->seed('#__content_rating', 7, 0, 25, 6, $ip);
    foreach (array(1 => null, 2 => '', 3 => '{}') as $id => $attribs) {
        Factory::$db->reviews[$id] = (object) (array('id' => $id, 'content_id' => 7, 'user_id' => 30 + $id, 'average_rating' => 60, 'state' => 1, 'attribs' => $attribs) + FakeReviewsTable::COLUMNS);
    }
    Factory::$db->review_seq = 3;
    $remove(array(1));
    $check($main() === '22/5', 'manager delete of an older review removes its vote');
    $check($deleteOwn(new Visitor(new FakeUser(32), $ip)) && $main() === '19/4', 'own delete of an older review removes its vote');
    $check($trash(new Visitor(new FakeUser(50, array('core.edit.state')), '203.0.113.5'), 3) && $main() === '16/3', 'moderator trash of an older review removes its vote');

    // 9. No way to add votes by cycling: counted review, delete, vote via the stars, new session, review, delete ...
    $world();
    $user = new Visitor(new FakeUser(31), $ip);
    $most = 5;
    for ($i = 0; $i < 4; $i++) {
        $review($user, 5); $most = max($most, Factory::$db->row('#__content_rating', 7)->rating_count);
        $deleteOwn($user);
        $star($user, 5); $most = max($most, Factory::$db->row('#__content_rating', 7)->rating_count);
        $user->newSession();
    }
    $row = Factory::$db->row('#__content_rating', 7);
    $check($most === 6 && $row->rating_count === 6 && $row->rating_sum === 25, 'the visitor never has more than one vote: ' . $main() . ', most ' . $most);
    $check($deleteOwn($user) && $main() === '20/5', 'and the vote is removed with the review record that carries it');

    // 10. Ratings without review are stored (option): the account is the voter, the address rule is not used and nothing is recorded
    $world(array('reviews_store_rating' => 1));
    $guest = new Visitor(new FakeUser, $ip); $user = new Visitor(new FakeUser(31), $ip);
    $star($guest, 1);
    $check($review($user, 5) && $main() === '26/7' && $counted($ownReview($user)) && $deleteOwn($user) && $main() === '21/6', 'account votes are counted and removed as before');

    // 11. Composite voting: the votes of all characteristics are stored when the voting is completed
    $composite = array('enable_extra_votes' => 1, 'extra_votes' => '1##Quality%%2##Price%%3##Service');
    $seedExtra = static function ($lastips) { foreach ($lastips as $xid => $lastip) Factory::$db->seed('#__flexicontent_items_extravote', 7, $xid, 12, 3, $lastip); };
    $extra = static function () { $out = array(); foreach (array(1, 2, 3) as $xid) { $row = Factory::$db->row('#__flexicontent_items_extravote', 7, $xid); $out[] = $row ? $row->rating_sum . '/' . $row->rating_count : '-'; } return implode(' ', $out); };
    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $visitor = new Visitor(new FakeUser, $ip);
    $check($star($visitor, 5, 1) && $star($visitor, 4, 2) && $extra() === '12/3 12/3 12/3' && $main() === '20/5', 'votes of an incomplete voting are not stored yet');
    $check($star($visitor, 3, 3) && $extra() === '17/4 16/4 15/4' && $main() === '24/6', 'completing the voting stores every characteristic and the average');
    $check($star($visitor, 5, 2) && $extra() === '17/4 17/4 15/4' && $main() === '24/6', 'changing one characteristic changes that vote');
    $check(!$star($visitor->newSession(), 5, 3) && $extra() === '17/4 17/4 15/4', 'a new session of the same address is refused');

    // A refused characteristic stores nothing, also when another characteristic has no votes yet
    $world($composite); $seedExtra(array(2 => 'b', 3 => $ip));
    $visitor = new Visitor(new FakeUser, $ip);
    $visitor->session->data['vote_history'] = array(7 => array(1 => 5, 2 => 4));
    $check(!$star($visitor, 3, 3) && $extra() === '- 12/3 12/3' && $main() === '20/5', 'a refused last vote stores none of the votes');

    // 12. Composite review: counted completely, or not at all
    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $user = new Visitor(new FakeUser(31), $ip);
    $check($review($user, array(1 => 5, 2 => 4, 3 => 3)) && $extra() === '17/4 16/4 15/4' && $main() === '24/6' && $counted($ownReview($user)), 'composite review: every characteristic and the average are votes');
    $check($review($user->newSession(), array(1 => 3, 2 => 4, 3 => 5)) && $extra() === '15/4 16/4 17/4' && $main() === '24/6' && $counted($ownReview($user)), 'edit from a new session changes these votes, it does not vote again');
    $check($star($user, 1, 1) && $extra() === '13/4 16/4 17/4', 'a later vote via the stars is a change of the votes of the review');
    Factory::$db->voting['submit_acclvl'] = 3;
    $check(!$review($user, array(1 => 5, 2 => 5, 3 => 5)) && $extra() === '13/4 16/4 17/4', 'without the access level for voting the ratings of the review can not be changed');
    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $review($user->newSession(), array(1 => 5, 2 => 4, 3 => 3));
    $remove(array($ownReview($user)->id));
    $check($extra() === '12/3 12/3 12/3' && $main() === '20/5', 'manager delete of a counted composite review removes all its votes');

    $world($composite); $seedExtra(array(1 => 'a', 2 => $ip, 3 => 'c'));
    $user = new Visitor(new FakeUser(31), $ip);
    $check($review($user, array(1 => 5, 2 => 4, 3 => 3)) && $extra() === '12/3 12/3 12/3' && $main() === '20/5', 'composite review with a refused characteristic adds no vote');
    $own = $ownReview($user);
    $check(!$counted($own) && $own->average_rating === 80 && json_decode($own->custom_ratings, true) === array(1 => 100, 2 => 80, 3 => 60), 'it keeps its stars and is recorded as not counted');
    $managerSave($own->id, array('average_rating' => 100, 'custom_ratings' => json_encode(array(1 => 100, 2 => 100, 3 => 100))));
    $check($extra() === '12/3 12/3 12/3' && $main() === '20/5', 'a manager saving other ratings for it changes no vote');
    $remove(array($own->id));
    $check($extra() === '12/3 12/3 12/3' && $main() === '20/5', 'a manager deleting it removes no vote');

    $world($composite); $seedExtra(array(1 => 'a', 2 => $ip, 3 => 'c'));
    $user = new Visitor(new FakeUser(31), $ip);
    $review($user, array(1 => 5, 2 => 4, 3 => 3));
    Factory::$db->row('#__flexicontent_items_extravote', 7, 2)->lastip = 'someone else';
    $check($review($user->newSession(), array(1 => 5, 2 => 5, 3 => 5)) && $extra() === '17/4 17/4 17/4' && $main() === '25/6' && $counted($ownReview($user)), 'edit when no characteristic is refused any more: counted, as new votes');
    $managerSave($ownReview($user)->id, array('average_rating' => 60, 'custom_ratings' => json_encode(array(1 => 60, 2 => 60, 3 => 60))));
    $check($extra() === '15/4 15/4 15/4' && $main() === '23/6', 'a manager saving other ratings for a counted composite review changes its votes');
    $remove(array($ownReview($user)->id));
    $check($extra() === '12/3 12/3 12/3' && $main() === '20/5', 'and deleting it removes them');

    // 13. The recorded value of composite reviews: a vote in every characteristic
    $check(!flexicontent_reviews::hasCharVotes(null) && !flexicontent_reviews::hasCharVotes('') && !flexicontent_reviews::hasCharVotes('{"vote_counted":0}')
        && flexicontent_reviews::hasCharVotes('{"chars_counted":1}') && flexicontent_reviews::hasCharVotes(new Registry('{"chars_counted":1}')),
        'only a review recorded so is known to have a vote in every characteristic');
    $row = new flexicontent_reviews;
    flexicontent_reviews::setCharVotes($row, true); flexicontent_reviews::setVoteCounted($row, false);
    $check(json_decode((string) $row->attribs, true) === array('chars_counted' => 1, 'vote_counted' => 0), 'the two recorded values do not replace each other');
    flexicontent_reviews::setCharVotes($row, false); flexicontent_reviews::setVoteCounted($row, true);
    $check($row->attribs === '', 'and both can be removed');

    // 14. Composite review that was counted: deleting or trashing it in the site removes the votes that the back-end removes
    $lastips = static function () { $out = array(Factory::$db->row('#__content_rating', 7)->lastip); foreach (array(1, 2, 3) as $xid) $out[] = Factory::$db->row('#__flexicontent_items_extravote', 7, $xid)->lastip; return implode(' ', $out); };
    $moderator = new Visitor(new FakeUser(50, array('core.edit.state')), '203.0.113.5');
    $ratings = array(1 => 5, 2 => 4, 3 => 3);

    $world($composite); Factory::$db->votes['#__content_rating'] = array();
    $user = new Visitor(new FakeUser(31), $ip);
    $check($review($user, $ratings) && $extra() === '5/1 4/1 3/1' && $main() === '4/1' && flexicontent_reviews::hasCharVotes($ownReview($user)->attribs), 'first voter of an item: every vote stored, and recorded so');
    $check($deleteOwn($user) && $extra() === '0/0 0/0 0/0' && $main() === '0/0', 'own delete, single voter: all votes removed');
    $check($lastips() === '   ' && empty($user->session->data['vote_history'][7]) && !flexicontent_reviews::hasCharVotes($ownReview($user)->attribs), 'own delete frees the address and the session, the trashed review records nothing');

    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $user = new Visitor(new FakeUser(31), $ip);
    $review($user, $ratings);
    $check($extra() === '17/4 16/4 15/4' && $main() === '24/6' && $deleteOwn($user) && $extra() === '12/3 12/3 12/3' && $main() === '20/5', 'own delete, other voters present: exactly the votes of the review are removed');

    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $user = new Visitor(new FakeUser(31), $ip);
    $review($user, $ratings);
    $check($trash($moderator, $ownReview($user)->id) && $extra() === '12/3 12/3 12/3' && $main() === '20/5', 'moderator trash, other voters present: exactly the votes of the review are removed');
    $check($lastips() === "$ip $ip $ip $ip" && $user->session->data['vote_history'][7] === array(1 => 5, 2 => 4, 3 => 3, 'main' => 4), 'a moderator does not free the address or the session of the author');
    $world($composite); Factory::$db->votes['#__content_rating'] = array();
    $review($user->newSession(), $ratings);
    $check($trash($moderator, $ownReview($user)->id) && $extra() === '0/0 0/0 0/0' && $main() === '0/0', 'moderator trash, single voter: all votes removed');

    // The same amounts as the back-end
    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $review($user->newSession(), $ratings); $deleteOwn($user); $site = $extra() . ' ' . $main();
    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $review($user->newSession(), $ratings); $remove(array($ownReview($user)->id));
    $check($site === $extra() . ' ' . $main(), 'site delete and back-end delete leave the same totals');

    // 15. Composite review that was not counted: nothing is removed, the address stays the last voter
    foreach (array('own delete', 'moderator trash') as $how) {
        $world($composite); $seedExtra(array(1 => 'a', 2 => $ip, 3 => 'c'));
        $user = new Visitor(new FakeUser(31), $ip);
        $review($user, $ratings);
        $done = $how === 'own delete' ? $deleteOwn($user) : $trash($moderator, $ownReview($user)->id);
        $check($done && $extra() === '12/3 12/3 12/3' && $main() === '20/5' && Factory::$db->row('#__flexicontent_items_extravote', 7, 2)->lastip === $ip, $how . ' of a not counted composite review removes nothing');
    }

    // 16. No way to add composite votes by cycling
    $voteAll = static function (Visitor $who, $rating) use ($star) { $ok = true; foreach (array(1, 2, 3) as $xid) $ok = $star($who, $rating, $xid) && $ok; return $ok; };
    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $guest = new Visitor(new FakeUser, $ip); $user = new Visitor(new FakeUser(31), $ip);
    $voteAll($guest, 1);
    $check($extra() === '13/4 13/4 13/4' && $main() === '21/6', 'composite guest vote is counted');
    for ($i = 0; $i < 3; $i++) { $review($user->newSession(), array(1 => 5, 2 => 5, 3 => 5)); $deleteOwn($user); $voteAll($guest->newSession(), 1); }
    $check($extra() === '13/4 13/4 13/4' && $main() === '21/6', 'repeating review, delete and vote from one address changes nothing');

    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $user = new Visitor(new FakeUser(31), $ip);
    $most = 5;
    for ($i = 0; $i < 4; $i++) {
        $review($user, array(1 => 5, 2 => 5, 3 => 5)); $most = max($most, Factory::$db->row('#__content_rating', 7)->rating_count, Factory::$db->row('#__flexicontent_items_extravote', 7, 1)->rating_count + 2);
        $deleteOwn($user);
        $voteAll($user, 5); $most = max($most, Factory::$db->row('#__content_rating', 7)->rating_count, Factory::$db->row('#__flexicontent_items_extravote', 7, 1)->rating_count + 2);
        $user->newSession();
    }
    $check($most === 6 && $extra() === '17/4 17/4 17/4' && $main() === '25/6', 'counted review, delete, vote, new session, review ...: never more than one vote of the visitor: ' . $extra() . ' ' . $main() . ', most ' . $most);

    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $user = new Visitor(new FakeUser(31), $ip);
    $review($user, array(1 => 5, 2 => 5, 3 => 5));
    for ($i = 0; $i < 3; $i++) { $deleteOwn($user); $voteAll($user, 4); $review($user, array(1 => 5, 2 => 5, 3 => 5)); }
    $check($extra() === '17/4 17/4 17/4' && $main() === '25/6' && $deleteOwn($user) && $extra() === '12/3 12/3 12/3' && $main() === '20/5', 'delete, vote and review again in one session: one vote, removed with the review');

    // 17. Composite review of an older version (not recorded): a characteristic that is behind the complete votes keeps its votes
    $legacy = static function (array $rows, $mainRow, $attribs = null) use ($world, $composite) {
        $world($composite);
        Factory::$db->seed('#__content_rating', 7, 0, $mainRow[0], $mainRow[1], 'x');
        foreach ($rows as $xid => $row) Factory::$db->seed('#__flexicontent_items_extravote', 7, $xid, $row[0], $row[1], 'x');
        Factory::$db->reviews[1] = (object) (array('id' => 1, 'content_id' => 7, 'user_id' => 31, 'average_rating' => 80, 'state' => 1,
            'custom_ratings' => json_encode(array(1 => 100, 2 => 80, 3 => 60)), 'attribs' => $attribs) + FakeReviewsTable::COLUMNS);
        Factory::$db->review_seq = 1;
    };
    $user = new Visitor(new FakeUser(31), $ip);
    $ways = array(
        'back-end delete' => static function () use ($remove) { $remove(array(1)); return true; },
        'own delete' => static function () use ($deleteOwn, $user) { return $deleteOwn($user->newSession()); },
        'moderator trash' => static function () use ($trash, $moderator) { return $trash($moderator, 1); },
    );
    foreach ($ways as $how => $delete) {
        // Six complete votes, the first two characteristics hold one vote each (of another voter), the last one holds all six
        $legacy(array(1 => array(5, 1), 2 => array(4, 1), 3 => array(21, 6)), array(24, 6));
        $check($delete() && $extra() === '5/1 4/1 18/5' && $main() === '20/5', $how . ' of an older composite review: votes of other voters survive: ' . $extra() . ' ' . $main());

        // Every characteristic holds all six votes: all votes of the review are removed
        $legacy(array(1 => array(26, 6), 2 => array(25, 6), 3 => array(21, 6)), array(24, 6));
        $check($delete() && $extra() === '21/5 21/5 18/5' && $main() === '20/5', $how . ' of an older composite review with all its votes stored: all removed: ' . $extra() . ' ' . $main());

        // Totals smaller than the votes of the review: nothing goes below zero
        $legacy(array(1 => array(2, 1), 2 => array(2, 1), 3 => array(2, 1)), array(2, 1));
        $check($delete() && $extra() === '0/0 0/0 0/0' && $main() === '0/0', $how . ': no total goes below zero: ' . $extra() . ' ' . $main());
    }
    $legacy(array(1 => array(5, 1), 2 => array(4, 1), 3 => array(21, 6)), array(24, 6), '{"chars_counted":1}');
    $remove(array(1));
    $check($extra() === '0/0 0/0 18/5' && $main() === '20/5', 'a review recorded with all its votes is removed exactly, whatever the totals are');

    // Changes of an older composite review follow the same rule
    $legacy(array(1 => array(5, 1), 2 => array(4, 1), 3 => array(21, 6)), array(24, 6));
    $managerSave(1, array('average_rating' => 20, 'custom_ratings' => json_encode(array(1 => 20, 2 => 20, 3 => 20))));
    $check($extra() === '5/1 4/1 19/6' && $main() === '21/6', 'manager changes the ratings of an older composite review: only complete characteristics change');
    $legacy(array(1 => array(5, 1), 2 => array(4, 1), 3 => array(21, 6)), array(24, 6));
    $check($review($user->newSession(), array(1 => 1, 2 => 1, 3 => 1)) && $extra() === '5/1 4/1 19/6' && $main() === '21/6' && !flexicontent_reviews::hasCharVotes($ownReview($user)->attribs), 'the author changes them: the same, and the review is still not recorded as complete');

    // A review created by a manager stores every vote and is recorded so
    $legacy(array(1 => array(5, 1), 2 => array(4, 1), 3 => array(21, 6)), array(24, 6));
    $created = $managerSave(0, array('content_id' => 7, 'average_rating' => 100, 'custom_ratings' => json_encode(array(1 => 100, 2 => 100, 3 => 100))));
    $check($extra() === '10/2 9/2 26/7' && $main() === '29/7' && flexicontent_reviews::hasCharVotes(Factory::$db->reviews[$created]->attribs), 'manager creates a composite review: every vote stored, and recorded so');
    $remove(array($created));
    $check($extra() === '5/1 4/1 21/6' && $main() === '24/6', 'and deleting it removes exactly these votes');

    // 18. A changed vote can not make a total negative
    $world($composite); $seedExtra(array(1 => 'a', 2 => 'b', 3 => 'c'));
    $visitor = new Visitor(new FakeUser, $ip);
    $voteAll($visitor, 5);
    Factory::$db->row('#__flexicontent_items_extravote', 7, 1)->rating_sum = 2; Factory::$db->row('#__content_rating', 7)->rating_sum = 1;
    $check($star($visitor, 1, 1) && $extra() === '0/4 17/4 17/4' && $main() === '0/6', 'lowering a vote on totals that are too small stops at zero: ' . $extra() . ' ' . $main());

    $check($php_errors === array(), 'the requests above raise no PHP warning or notice: ' . implode(' | ', array_unique($php_errors)));

    // The reviews controller is the only place that changes votes: no other back-end controller keeps its own voting task
    foreach (glob(JPATH_ROOT . '/admin/controllers/*.php') as $controller_file) {
        if (basename($controller_file) === 'reviews.php') continue;
        $check(!preg_match('/function\s+ajaxvote\s*\(/', file_get_contents($controller_file)), basename($controller_file) . ' has no voting task of its own');
    }

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
