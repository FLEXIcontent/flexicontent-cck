<?php
/**
 * Counters that requests arriving together must not overrun (download quota, download coupon), the clock of the coupon
 * expiry, the content check of the PDF preview, and edit coupons of items that are gone.
 * Runs the real methods with doubles; no Joomla, no database (a small in-memory double answers the statements).
 */
namespace Joomla\CMS {
    class Factory {
        public static $app, $db;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($service) { return Factory::$db; } }; }
    }
}
namespace Joomla\CMS\Component {
    class ComponentHelper { public static function getParams($option) { return new \Joomla\Registry\Registry; } }
}
namespace Joomla\CMS\Uri {
    class Uri { public static function getInstance() { return new class { public function isSsl() { return false; } }; } }
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
    use Joomla\CMS\Factory;

    error_reporting(E_ALL);
    define('_JEXEC', 1);
    $root = dirname(__DIR__);

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

    final class UserDouble {
        public $id, $guest, $groups;
        public function __construct($id, array $groups = array(1)) { $this->id = $id; $this->guest = !$id; $this->groups = $groups; }
        public function authorise($action, $asset = null) { return false; }
        public function getAuthorisedGroups() { return $this->groups; }
    }
    final class AppDouble {
        public $identity;
        public function getIdentity() { return $this->identity; }
        public function get($name, $default = null) { return $name === 'secret' ? 'fixture-secret' : $default; }
        public function getInput() {
            return new class { public $cookie; public function __construct() { $this->cookie = new class {
                public function getString($name, $default = '') { return $default; }
                public function set(...$args) {}
            }; } };
        }
    }

    /** The download log, the download coupons and the edit coupons as arrays; a statement without an answer is an error */
    final class CounterDb implements \Joomla\Database\DatabaseInterface {
        public $rows = array(), $next_id = 1, $on_insert = null;
        public $coupons = array(), $edit_coupons = array(), $edit_table = true, $log = array();
        private $sql = '', $affected = 0, $insert_id = 0;

        public function quote($text, $escape = true) { return "'" . addslashes((string) $text) . "'"; }
        public function quoteName($name) { return '`' . $name . '`'; }
        public function setQuery($sql, $offset = 0, $limit = 0) { $this->sql = (string) $sql; $this->log[] = $this->sql; return $this; }
        public function insertid() { return $this->insert_id; }
        public function getAffectedRows() { return $this->affected; }

        private function matching($since, $where) {
            return array_filter($this->rows, function ($row) use ($since, $where) { return $row['downloaded_on'] > $since && self::where($row, $where); });
        }
        private static function where(array $row, $where) {
            foreach (explode(' AND ', $where) as $condition) {
                if ($condition === '1=0') return false;
                if (!preg_match("/^(user_id|visitor_id|ip_hash) = '?([^']*)'?$/", $condition, $m)) throw new \LogicException('Condition not understood: ' . $condition);
                if ((string) $row[$m[1]] !== $m[2]) return false;
            }
            return true;
        }

        public function loadResult() {
            $table = '`#__flexicontent_download_log`';
            if (preg_match('/^SELECT (COUNT\(\*\)|COUNT\(DISTINCT file_id\)) FROM ' . $table . " WHERE downloaded_on > '([^']+)' AND \((.+)\)$/", $this->sql, $m)) {
                $rows = $this->matching($m[2], $m[3]);
                return $m[1] === 'COUNT(*)' ? count($rows) : count(array_unique(array_column($rows, 'file_id')));
            }
            if (preg_match('/^SELECT 1 FROM ' . $table . " WHERE file_id = (\d+) AND downloaded_on > '([^']+)' AND \(\((.+)\)\) LIMIT 1$/", $this->sql, $m)) {
                foreach (explode(') OR (', $m[3]) as $where) {
                    foreach ($this->matching($m[2], $where) as $row) if ($row['file_id'] == $m[1]) return 1;
                }
                return null;
            }
            throw new \LogicException('Query without an answer: ' . $this->sql);
        }

        public function addRow($user_id, $visitor, $ip, $file_id, $age = 10) {
            $this->rows[$this->next_id] = array('user_id' => (int) $user_id, 'visitor_id' => $visitor, 'ip_hash' => $ip, 'file_id' => (int) $file_id,
                'downloaded_on' => gmdate('Y-m-d H:i:s', time() - $age));
            return $this->next_id++;
        }

        public function execute() {
            $table = '`#__flexicontent_download_log`';
            if (preg_match('/^INSERT INTO ' . $table . " \(user_id, visitor_id, ip_hash, file_id, field_id, downloaded_on, expire_on\) VALUES \((\d+), '([^']*)', '([^']*)', (\d+), (\d+), '([^']+)', '([^']+)'\)$/", $this->sql, $m)) {
                $this->rows[$this->next_id] = array('user_id' => (int) $m[1], 'visitor_id' => $m[2], 'ip_hash' => $m[3], 'file_id' => (int) $m[4], 'downloaded_on' => $m[6]);
                $this->insert_id = $this->next_id++;
                // What other requests record between the check of this request and its second count
                if ($this->on_insert) { $hook = $this->on_insert; $this->on_insert = null; $hook($this, $this->rows[$this->insert_id]); }
                return true;
            }
            if (preg_match('/^DELETE FROM ' . $table . ' WHERE id = (\d+)$/', $this->sql, $m)) { unset($this->rows[(int) $m[1]]); return true; }
            if (preg_match('/^DELETE FROM ' . $table . " WHERE expire_on < '[^']+' LIMIT 1000$/", $this->sql)) return true;

            if (preg_match('/^UPDATE #__flexicontent_download_coupons SET hits = hits \+ 1 WHERE id = (\d+) AND \(hits_limit = -1 OR hits < hits_limit\)$/', $this->sql, $m)) {
                $this->affected = 0;
                if (isset($this->coupons[$m[1]]) && ($this->coupons[$m[1]]['hits_limit'] === -1 || $this->coupons[$m[1]]['hits'] < $this->coupons[$m[1]]['hits_limit'])) {
                    $this->coupons[$m[1]]['hits']++;
                    $this->affected = 1;
                }
                return true;
            }

            if (preg_match('/^DELETE FROM #__flexicontent_edit_coupons WHERE id = (\d+)$/', $this->sql, $m)) {
                if (!$this->edit_table) throw new \RuntimeException("Table 'jos_flexicontent_edit_coupons' doesn't exist", 1146);
                unset($this->edit_coupons[(int) $m[1]]);
                return true;
            }

            throw new \LogicException('Statement without an answer: ' . $this->sql);
        }
    }

    Factory::$db = $db = new CounterDb;
    Factory::$app = $app = new AppDouble;
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    require $root . '/site/classes/helpers/security.php';
    require $root . '/site/classes/helpers/downloadlimit.php';
    final class LimitFixture extends flexicontent_downloadlimit {
        public static function identity($user) { Factory::$app->identity = $user; self::$identity = null; return self::getIdentity(); }
    }

    // 1. Download quota: one download after the other
    $settings = array('mode' => 0, 'window' => 3600, 'groups' => array(1 => 3), 'ip_factor' => 2, 'count_special' => 0, 'modal_html' => '');
    $member = new UserDouble(31);
    $start = static function ($user) use ($db) { $db->rows = array(); $db->on_insert = null; return LimitFixture::identity($user); };
    $start($member);
    $served = array();
    for ($i = 1; $i <= 5; $i++) $served[] = LimitFixture::consume($settings, 100 + $i, 10, $member);
    $check($served === array(true, true, true, false, false) && count($db->rows) === 3, 'quota: three downloads are served and recorded, the fourth and fifth are refused');
    $start($member);
    $db->addRow(31, '', 'x', 100, 7200);
    $db->addRow(31, '', 'x', 101, 7200);
    $db->addRow(32, '', 'x', 102);
    $check(LimitFixture::consume($settings, 103, 10, $member) && count($db->rows) === 4, 'quota: downloads outside the period and of other users do not count');
    $start(new UserDouble(31, array(1, 7)));
    $check(LimitFixture::consume(array('groups' => array(1 => 3, 7 => -1)) + $settings, 100, 10, Factory::$app->identity) && $db->rows === array(), 'quota: a user without a limit is served and not recorded');

    // 2. Download quota: requests that arrive together
    $start($member);
    $db->addRow(31, '', 'x', 100);
    $db->addRow(31, '', 'x', 101);
    $db->on_insert = static function ($db) { $db->addRow(31, '', 'x', 150, 0); };
    $check(LimitFixture::consume($settings, 102, 10, $member) === false, 'quota: a request that finds the limit exceeded after it was recorded is refused');
    $check(count($db->rows) === 3 && array_column($db->rows, 'file_id') === array(100, 101, 150), 'quota: it takes its own record back, the record of the other request stays');
    $check(LimitFixture::consume($settings, 102, 10, $member) === false && count($db->rows) === 3, 'quota: the limit is then used up');

    $start($member);
    $db->addRow(31, '', 'x', 100);
    $db->on_insert = static function ($db) { $db->addRow(31, '', 'x', 150, 0); };
    $check(LimitFixture::consume($settings, 102, 10, $member) === true && count($db->rows) === 3, 'quota: two requests together within the limit are both served');

    $start($member);
    $db->addRow(31, '', 'x', 100);
    $db->addRow(31, '', 'x', 101);
    $db->on_insert = static function ($db) { $db->addRow(32, '', 'x', 150, 0); };
    $check(LimitFixture::consume($settings, 102, 10, $member) === true && count($db->rows) === 4, 'quota: a request of another user at the same time does not count');

    // Five requests of one user pass the first check together; each one counts again after it is recorded
    $start($member);
    $db->addRow(31, '', 'x', 100);
    $db->addRow(31, '', 'x', 101);
    $pending = array();
    $db->on_insert = static function ($db) use (&$pending) { for ($i = 0; $i < 4; $i++) $pending[] = $db->addRow(31, '', 'x', 160 + $i, 0); };
    $first = LimitFixture::consume($settings, 159, 10, $member);
    $kept = 0;
    foreach ($pending as $row_id) {
        // The second count of each of the other requests, as consume() does it
        $over = count(array_filter($db->rows, static function ($row) { return $row['user_id'] === 31; })) > 3;
        if ($over) unset($db->rows[$row_id]); else $kept++;
    }
    $check($first === false && $kept === 1 && count($db->rows) === 3, 'quota: of five requests together with one download left, one is served');

    // 3. Download quota: each file counts once (unique mode)
    $unique = array('mode' => 1, 'groups' => array(1 => 2)) + $settings;
    $start($member);
    $db->addRow(31, '', 'x', 100);
    $check(LimitFixture::consume($unique, 101, 10, $member) && count($db->rows) === 2, 'unique files: a second file is served and recorded');
    $check(LimitFixture::consume($unique, 101, 10, $member) && LimitFixture::consume($unique, 100, 10, $member) && count($db->rows) === 2, 'unique files: files already taken are served again without a new record');
    $check(!LimitFixture::consume($unique, 102, 10, $member) && count($db->rows) === 2, 'unique files: a third file is refused');
    $start($member);
    $db->addRow(31, '', 'x', 100);
    $db->on_insert = static function ($db) { $db->addRow(31, '', 'x', 150, 0); };
    $check(!LimitFixture::consume($unique, 101, 10, $member) && array_column($db->rows, 'file_id') === array(100, 150), 'unique files: another file recorded at the same time uses the last place');
    $start($member);
    $db->addRow(31, '', 'x', 100);
    $db->on_insert = static function ($db) { $db->addRow(31, '', 'x', 101, 0); };
    $check(LimitFixture::consume($unique, 101, 10, $member) && count($db->rows) === 3, 'unique files: the same file recorded at the same time is one file');

    // 4. Download quota: guests, with the ceiling of their IP
    $visitor = new UserDouble(0);
    $id = $start($visitor);
    $check($id['user_id'] === 0 && strlen($id['visitor']) === 32 && strlen($id['ip']) === 32, 'guest: identified by a visitor id and the hash of the IP');
    $served = array();
    for ($i = 1; $i <= 4; $i++) $served[] = LimitFixture::consume($settings, 100 + $i, 10, $visitor);
    $check($served === array(true, true, true, false) && count($db->rows) === 3, 'guest: the limit of the visitor, one download after the other');
    $id = $start($visitor);
    for ($i = 0; $i < 5; $i++) $db->addRow(0, 'visitor-' . $i, $id['ip'], 100 + $i);
    $check(LimitFixture::consume($settings, 110, 10, $visitor) && count($db->rows) === 6, 'guest: the last download under the ceiling of the IP is served');
    $check(!LimitFixture::consume($settings, 111, 10, $visitor) && count($db->rows) === 6, 'guest: the ceiling of the IP refuses the next one');
    $id = $start($visitor);
    for ($i = 0; $i < 5; $i++) $db->addRow(0, 'visitor-' . $i, $id['ip'], 100 + $i);
    $db->on_insert = static function ($db, $own) { $db->addRow(0, 'visitor-9', $own['ip_hash'], 150, 0); };
    $check(!LimitFixture::consume($settings, 110, 10, $visitor) && count($db->rows) === 6 && !in_array(110, array_column($db->rows, 'file_id')), 'guest: requests of one IP together do not pass its ceiling');
    $id = $start($visitor);
    $db->addRow(0, 'visitor-1', $id['ip'], 100);
    $db->on_insert = static function ($db, $own) { $db->addRow(0, $own['visitor_id'], 'other-ip', 150, 0); $db->addRow(0, $own['visitor_id'], 'other-ip', 151, 0); $db->addRow(0, $own['visitor_id'], 'other-ip', 152, 0); };
    $check(!LimitFixture::consume($settings, 110, 10, $visitor) && count($db->rows) === 4, 'guest: requests with one visitor id together do not pass its limit');
    $consume = methodSource(source('site/classes/helpers/downloadlimit.php'), 'consume');
    $check(strpos($consume, 'self::canDownloadFile($settings, $state, $fileId)') < strpos($consume, 'INSERT INTO') && strpos($consume, 'INSERT INTO') < strpos($consume, 'self::isOverLimit($db, $settings, $id, $state[\'limit\'])'),
        'quota: checked before the download is recorded, as before, and counted again after it');

    // 5. Download coupon: the statement that counts a download checks the limit
    $db->coupons = array(7 => array('hits' => 2, 'hits_limit' => 3), 8 => array('hits' => 500, 'hits_limit' => -1), 9 => array('hits' => 3, 'hits_limit' => 3), 10 => array('hits' => 0, 'hits_limit' => 0));
    // Two requests read the coupon with one download left, then both count their download
    $claims = array(flexicontent_security::claimDownloadCouponHit($db, 7), flexicontent_security::claimDownloadCouponHit($db, 7), flexicontent_security::claimDownloadCouponHit($db, '7'));
    $check($claims === array(true, false, false) && $db->coupons[7]['hits'] === 3, 'coupon: of the requests that arrive together only as many are served as the coupon has downloads left');
    $check(flexicontent_security::claimDownloadCouponHit($db, 8) && flexicontent_security::claimDownloadCouponHit($db, 8) && $db->coupons[8]['hits'] === 502, 'coupon: a coupon without a limit is always counted');
    $check(!flexicontent_security::claimDownloadCouponHit($db, 9) && $db->coupons[9]['hits'] === 3 && !flexicontent_security::claimDownloadCouponHit($db, 10), 'coupon: a used up coupon is not counted');
    $check(!flexicontent_security::claimDownloadCouponHit($db, 99) && !flexicontent_security::claimDownloadCouponHit($db, '8 OR 1=1 --x') === false, 'coupon: the id is a number');
    $controller = source('site/controller.php');
    $download = methodSource($controller, 'download');
    $claim = strpos($download, '&& !flexicontent_security::claimDownloadCouponHit($db, $file_node->coupon->id))');
    $check($claim !== false && $claim < strpos($download, '$filetable->hit($file_id)') && $claim > strpos($download, 'flexicontent_downloadlimit::consume('), 'coupon: counted before the file is served and before the file counters');
    $refusal = substr($download, $claim, strpos($download, '$filetable->hit($file_id)') - $claim);
    $check(strpos($refusal, "FLEXI_FDC_COUPON_REACHED_USAGE_LIMIT") !== false && strpos($refusal, "\$this->setRedirect('index.php', '');\n\t\t\t\t\treturn;") !== false, 'coupon: a download that could not be counted is refused');
    $check(strpos($controller, 'UPDATE #__flexicontent_download_coupons') === false && strpos($controller, 'SET hits = hits + 1') === false, 'coupon: no statement counts a coupon download without the limit');

    // 6. Coupon expiry: written and compared with one clock
    $check(strpos($download, "\$_nowDate = 'CURRENT_TIMESTAMP';") !== false && strpos($download, 'UTC_TIMESTAMP') === false
        && strpos($download, "expire_on = '.\$_nullDate.'   OR   expire_on > '.\$_nowDate") !== false, 'coupon expiry: compared with the local time of the database (CURRENT_TIMESTAMP is NOW())');
    foreach (array('plugins/flexicontent_fields/file/file.php', 'plugins/flexicontent_fields/mediafile/mediafile.php') as $path) {
        $plugin = source($path);
        $check(substr_count($plugin, "', expire_on = NOW() + INTERVAL '") === 1 && strpos($plugin, 'UTC_TIMESTAMP()') === false, 'coupon expiry: written with NOW(): ' . $path);
    }

    // 7. PDF preview: only a file that starts as a PDF reaches the decoder, and the decoder is named
    $scratch = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fc-pdf-check-' . bin2hex(random_bytes(6));
    mkdir($scratch, 0700);
    try {
        $bodies = array(
            'real.pdf' => "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n", 'short.pdf' => '%PDF-',
            'svg.pdf' => '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><image href="file:///etc/passwd"/></svg>',
            'mvg.pdf' => "push graphic-context\nviewbox 0 0 640 480\nimage over 0,0 0,0 'label:@/etc/passwd'\npop graphic-context\n",
            'ps.pdf' => "%!PS-Adobe-3.0\n", 'late.pdf' => "\n%PDF-1.7\n", 'space.pdf' => ' %PDF-1.7', 'case.pdf' => '%pdf-1.7', 'cut.pdf' => '%PDF', 'empty.pdf' => '',
        );
        foreach ($bodies as $name => $body) file_put_contents($scratch . DIRECTORY_SEPARATOR . $name, $body);
        foreach ($bodies as $name => $body) {
            $expected = in_array($name, array('real.pdf', 'short.pdf'), true);
            $check(flexicontent_security::isPdfFile($scratch . DIRECTORY_SEPARATOR . $name) === $expected, 'pdf content: ' . $name . ($expected ? ' is a PDF' : ' is not a PDF'));
        }
        $check(!flexicontent_security::isPdfFile($scratch . DIRECTORY_SEPARATOR . 'none.pdf') && !flexicontent_security::isPdfFile($scratch) && !flexicontent_security::isPdfFile(null) && !flexicontent_security::isPdfFile(array()),
            'pdf content: a missing file, a folder or no path is not a PDF');
    } finally {
        foreach (glob($scratch . DIRECTORY_SEPARATOR . '*') as $file) unlink($file);
        rmdir($scratch);
    }
    $thumb = source('plugins/flexicontent_fields/file/pdf_thumb.php');
    $content_check = strpos($thumb, "if (!flexicontent_security::isPdfFile(\$path))\n{\n\thttp_response_code(415);\n\texit('Not a PDF file');\n}");
    $check($content_check !== false && $content_check > strpos($thumb, "!== 'pdf')") && $content_check < strpos($thumb, 'new \\Imagick()'), 'pdf preview: the content is checked after the path and before the decoder');
    $check(strpos($thumb, "\$image->readImage('pdf:' . \$path . '[0]');") !== false && substr_count($thumb, 'readImage(') === 1, 'pdf preview: the PDF decoder is named');

    // 8. Edit coupons: removed with their item, and before a new item gets a coupon
    $account = source('plugins/flexicontent_fields/account_via_submit/account_via_submit.php');
    eval('final class AccountFixture { public static $field_types = array("account_via_submit"); public ' . methodSource($account, 'onBeforeDeleteField') . ' }');
    $delete_item = static function ($field_type, $item) use ($db) {
        $db->log = array();
        $field = (object) array('field_type' => $field_type, 'id' => 4);
        (new AccountFixture)->onBeforeDeleteField($field, $item);
        return $db->log;
    };
    $db->edit_coupons = array(12 => true, 13 => true);
    $item = (object) array('id' => '12');
    $check($delete_item('account_via_submit', $item) === array('DELETE FROM #__flexicontent_edit_coupons WHERE id = 12') && array_keys($db->edit_coupons) === array(13), 'edit coupon: removed when its item is deleted');
    $item = (object) array('id' => '13 OR 1=1');
    $check($delete_item('account_via_submit', $item) === array('DELETE FROM #__flexicontent_edit_coupons WHERE id = 13'), 'edit coupon: the item id is a number');
    $item = (object) array('id' => 12);
    $check($delete_item('text', $item) === array(), 'edit coupon: other field types do nothing');
    $item = (object) array('id' => 0);
    $check($delete_item('account_via_submit', $item) === array(), 'edit coupon: no item, no statement');
    $db->edit_table = false;
    $item = (object) array('id' => 12);
    try { $delete_item('account_via_submit', $item); $thrown = false; } catch (\Throwable $e) { $thrown = true; }
    $check(!$thrown, 'edit coupon: deleting an item works before the field created its table');
    $db->edit_table = true;
    $before_save = methodSource($account, 'onBeforeSaveField');
    $cleanup = strpos($before_save, "\$query = 'DELETE FROM #__flexicontent_edit_coupons'\n\t\t\t\t. ' WHERE id = ' . (int) \$item->id . ' OR id NOT IN (SELECT c.id FROM #__content AS c)';");
    $check($cleanup !== false && $cleanup < strpos($before_save, "\$query = 'INSERT #__flexicontent_edit_coupons '") && $cleanup > strpos($before_save, '$this->initialize($field);'),
        'edit coupon: coupons of this id and of items that are gone are removed before a new coupon is written');

    $check($php_errors === array(), 'no PHP warning or notice: ' . implode(' | ', array_unique($php_errors)));

    if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
