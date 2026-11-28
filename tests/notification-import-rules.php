<?php
/**
 * Notification mails: the {FIELDS} summary lists only what every recipient may view, HTML bodies encode the values
 * placed into them, a missing addUnsafeTags() does not stop the mail, approval requests work with notifications
 * disabled and get the recipients of their own item. CSV import: folders of the system are not sources of imported
 * files. Template save: the folder is a plain name.
 * Runs the real methods with doubles; no Joomla, no database.
 */
namespace Joomla\CMS {
    class Factory {
        public static $app, $services = array();
        public static function getApplication() { return self::$app; }
        public static function getContainer() { return new class { public function get($service) { return Factory::$services[$service]; } }; }
        public static function getDate($value = 'now') { return new \Joomla\CMS\Date\Date($value); }
    }
}
namespace Joomla\CMS\Date {
    class Date extends \DateTime {
        public function __construct($value = 'now') { parent::__construct($value, new \DateTimeZone('UTC')); }
        #[\ReturnTypeWillChange]
        public function format($format, $local = false) { return parent::format($format); }
    }
}
namespace Joomla\Registry {
    class Registry {
        private $data;
        public function __construct($data = array()) { $this->data = is_string($data) ? (array) json_decode($data, true) : (array) $data; }
        public function get($name, $default = null) { return isset($this->data[$name]) ? $this->data[$name] : $default; }
    }
}
namespace Joomla\CMS\Component {
    class ComponentHelper { public static function getParams($component) { return new \Joomla\Registry\Registry(array()); } }
}
namespace Joomla\CMS\Language {
    class Text {
        public static $strings = array();
        public static function _($key) { return isset(self::$strings[$key]) ? self::$strings[$key] : $key; }
        public static function sprintf($key, ...$args) { return isset(self::$strings[$key]) ? vsprintf(self::$strings[$key], $args) : $key . '(' . implode(' | ', $args) . ')'; }
    }
}
namespace Joomla\CMS\Router {
    class Route { public static function _($url) { return '/' . $url; } }
}
namespace Joomla\CMS\Uri {
    class Uri {
        public static function getInstance() { return new self; }
        public function toString(array $parts = array()) { return 'https://example.org'; }
        public static function root() { return 'https://example.org/'; }
    }
}
namespace Joomla\CMS\Access {
    class Access {
        public static $levels = array(), $asked = array();
        public static function getAuthorisedViewLevels($user_id) { self::$asked[] = $user_id; return isset(self::$levels[$user_id]) ? self::$levels[$user_id] : array(1); }
    }
}
namespace Joomla\CMS\Mail {
    // As in Joomla before 5.1.3: there is no addUnsafeTags()
    class MailTemplate {
        public static $templates = array(), $sent = array();
        public $key, $data = array(), $recipients = array();
        public function __construct($key, $language = '') { $this->key = $key; }
        public static function getTemplate($key, $language) { return isset(self::$templates[$key]) ? clone self::$templates[$key] : null; }
        public static function createTemplate($key, $subject, $body, $tags, $htmlbody = '') { return true; }
        public function addTemplateData($data, $plain = false) { $this->data = array_merge($this->data, $data); }
        public function addRecipient($mail, $name = null, $type = 'to') { $this->recipients[] = $mail; }
        public function send() { if (!static::getTemplate($this->key, 'en-GB')) return false; self::$sent[] = $this; return true; }
    }
    class MailHelper {
        public static function cleanAddress($address) { return $address; }
        public static function cleanSubject($subject) { return $subject; }
    }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    // A site with the folders that the import rules name
    $site = str_replace('\\', '/', sys_get_temp_dir()) . '/fc_notification_import_rules_' . bin2hex(random_bytes(6));
    $folders = array('tmp/fcimport_docs', 'tmp/fcimport_media', 'cache/com_x', 'logs', 'administrator/logs', 'administrator/components', 'images/docs', 'media', 'private/tmp/fcimport_docs', 'private/logs/old');
    foreach ($folders as $folder) mkdir($site . '/' . $folder, 0777, true);
    register_shutdown_function(static function () use ($site) {
        $remove = static function ($dir) use (&$remove) { foreach (array_diff(scandir($dir), array('.', '..')) as $name) { is_dir($dir . '/' . $name) ? $remove($dir . '/' . $name) : unlink($dir . '/' . $name); } rmdir($dir); };
        if (is_dir($site)) $remove($site);
    });

    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('FLEXI_J16GE', true);
    define('JPATH_SITE', $site);
    define('JPATH_ADMINISTRATOR', $site . '/administrator');
    $repo = dirname(__DIR__);
    $_SERVER['SERVER_NAME'] = 'www.example.org';

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
    $source = function ($path) use ($repo) { return str_replace("\r\n", "\n", file_get_contents($repo . '/' . $path)); };

    final class UserDouble {
        public $id, $name, $username, $rights;
        public function __construct($id, $name = '', $username = '', array $rights = array()) { $this->id = $id; $this->name = $name; $this->username = $username; $this->rights = $rights; }
        public function get($name) { return $this->$name; }
        public function authorise($action, $asset = null) { return in_array($action, $this->rights, true); }
    }
    final class AppDouble {
        public $config = array('mailfrom' => 'site@example.org', 'fromname' => 'Site', 'sitename' => 'Site & <Co>', 'offset' => 'UTC'), $identity, $messages = array();
        public function get($name, $default = null) { return isset($this->config[$name]) ? $this->config[$name] : $default; }
        public function getConfig() { return $this; }
        public function getIdentity() { return $this->identity; }
        public function isClient($client) { return $client === 'site'; }
        public function enqueueMessage($message, $type = 'message') { $this->messages[] = $message; }
    }
    final class MailerDouble {
        public $Encoding, $mails = array();
        public function createMailer() { return $this; }
        public function sendMail($from, $fromname, $recipient, $subject, $body, $html = false, $cc = null, $bcc = null) {
            $this->mails[] = array('to' => $recipient, 'subject' => $subject, 'body' => $body, 'html' => $html, 'bcc' => $bcc);
            return true;
        }
    }
    final class QueryDouble {
        private $parts = array();
        public function __call($name, $args) { $this->parts[] = strtoupper($name) . ' ' . implode(' ', array_map('strval', $args)); return $this; }
        public function __toString() { return implode(' ', $this->parts); }
    }
    // Users, groups and categories of the site; answers the queries of the notification methods and records them
    final class SiteDbDouble {
        public $log = array(), $fail_users = false;
        public $users = array(
            array('id' => 21, 'username' => 'mod', 'email' => 'mod@example.org', 'block' => 0, 'groups' => array(7)),
            array('id' => 22, 'username' => 'chief', 'email' => 'Chief@Example.org', 'block' => 0, 'groups' => array(7, 8)),
            array('id' => 23, 'username' => 'author', 'email' => 'author@example.org', 'block' => 0, 'groups' => array(9)),
            array('id' => 24, 'username' => 'gone', 'email' => 'gone@example.org', 'block' => 1, 'groups' => array(7)),
        );
        public $categories = array(
            1 => array('title' => '<i>News</i>', 'params' => '{"cats_enable_notifications":"1","cats_usergrps_notify_new_pending":["7"]}'),
            2 => array('title' => 'Events', 'params' => '{"cats_enable_notifications":"1","cats_usergrps_notify_new_pending":["9"]}'),
            3 => array('title' => 'Plain', 'params' => '{}'),
        );
        public $item_categories = array();
        private $sql;
        public function getQuery($new = false) { return new QueryDouble; }
        public function quoteName($name) { return $name; }
        public function quote($value) { return "'" . str_replace("'", "''", (string) $value) . "'"; }
        public function getNullDate() { return '0000-00-00 00:00:00'; }
        public function setQuery($sql, $offset = 0, $limit = 0) { $this->sql = (string) $sql; $this->log[] = $this->sql; return $this; }
        private function ids($list) { return array_map('intval', explode(',', $list)); }
        public function loadResult() {
            if (preg_match('/^SELECT params FROM #__categories WHERE id = (\d+)$/', $this->sql, $m)) return $this->categories[(int) $m[1]]['params'];
            throw new \RuntimeException('Unexpected query: ' . $this->sql);
        }
        public function loadColumn() {
            if (preg_match('/^SELECT params FROM #__categories WHERE id IN \(([\d,]+)\)$/', $this->sql, $m)) {
                $rows = array();
                foreach ($this->ids($m[1]) as $id) $rows[] = $this->categories[$id]['params'];
                return $rows;
            }
            if (preg_match('/^SELECT DISTINCT email FROM #__users as u JOIN #__user_usergroup_map ugm ON u\.id = ugm\.user_id AND ugm\.group_id IN \(([\d,]+)\)$/', $this->sql, $m)) {
                $rows = array();
                foreach ($this->users as $user) if (array_intersect($user['groups'], $this->ids($m[1]))) $rows[] = $user['email'];
                return $rows;
            }
            if (preg_match("/^SELECT DISTINCT email FROM #__users WHERE  id IN \(([\d,]+)\)  OR  username IN \(('[^()]*')\) $/", $this->sql, $m)) {
                $rows = array();
                foreach ($this->users as $user) if (in_array($user['id'], $this->ids($m[1])) || strpos($m[2], "'" . $user['username'] . "'") !== false) $rows[] = $user['email'];
                return $rows;
            }
            throw new \RuntimeException('Unexpected query: ' . $this->sql);
        }
        public function loadObjectList($key = null) {
            if (preg_match("/^SELECT id, email FROM #__users WHERE block = 0 AND email IN \(('[^()]*')\)$/", $this->sql, $m)) {
                if ($this->fail_users) throw new \RuntimeException('The users table can not be read');
                $rows = array();
                // As a case-insensitive collation matches
                foreach ($this->users as $user) if (!$user['block'] && stripos($m[1], "'" . $user['email'] . "'") !== false) $rows[] = (object) array('id' => $user['id'], 'email' => $user['email']);
                return $rows;
            }
            if (preg_match('/^SELECT DISTINCT c\.id, c\.title FROM #__categories AS c LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel\.catid = c\.id WHERE rel\.itemid = (\d+)$/', $this->sql, $m)) {
                $rows = array();
                foreach ($this->item_categories[(int) $m[1]] as $id) $rows[$id] = (object) array('id' => $id, 'title' => $this->categories[$id]['title']);
                return $rows;
            }
            throw new \RuntimeException('Unexpected query: ' . $this->sql);
        }
    }

    final class FlexicontentHelperRoute { public static function getItemRoute($slug, $catslug = 0, $itemid = 0, $item = null) { return 'index.php?option=com_flexicontent&view=item&cid=' . (int) $catslug . '&id=' . (int) $slug; } }
    final class flexicontent_html {
        public static function getSefUrl($url) { return '/' . $url; }
        public static function striptagsandcut($text, $length) { return substr(strip_tags((string) $text), 0, $length); }
    }
    eval('final class FLEXIUtilities { public static function getLanguages() { return new stdClass; } public static ' . methodSource($source('site/classes/helpers/flexiutilities.php'), 'paramToArray') . ' }');

    require $repo . '/admin/helpers/notificationmail.php';
    require $repo . '/admin/helpers/notificationmailtemplate.php';

    // A mailer that has addUnsafeTags(), as in current Joomla
    final class CurrentMailTemplateDouble extends \Joomla\CMS\Mail\MailTemplate {
        public $unsafe = null;
        public function addUnsafeTags($tags) { $this->unsafe = $tags; }
    }
    final class CurrentJoomlaHelper extends FlexicontentHelperNotificationMail {
        protected static function createMailer($templateId, $language, array $conditionContext = array()) { return new CurrentMailTemplateDouble($templateId, $language); }
    }

    use Joomla\CMS\Access\Access;
    use Joomla\CMS\Factory;
    use Joomla\CMS\Language\Text;
    use Joomla\CMS\Mail\MailTemplate;
    use Joomla\Registry\Registry;

    $app = Factory::$app = new AppDouble;
    $app->identity = new UserDouble(5, 'Ann <A> & "B"', 'ann');
    $db = Factory::$services['Joomla\Database\DatabaseInterface'] = new SiteDbDouble;
    $mailer = Factory::$services['Joomla\CMS\Mail\MailerFactoryInterface'] = new MailerDouble;
    // Special for the moderators, registered for the author, public and guest for a visitor
    Access::$levels = array(0 => array(1, 5), 21 => array(1, 2, 3), 22 => array(1, 2, 3, 6), 23 => array(1, 2), 24 => array(1, 2, 3));


    /**
     * 1. {FIELDS} and the view levels of the recipients
     */

    $fields = array(
        (object) array('name' => 'title', 'label' => 'Title', 'value' => 'T', 'access' => 1, 'iscore' => 1),
        (object) array('name' => 'color', 'label' => 'Color', 'value' => 'red', 'access' => 1, 'iscore' => 0),
        (object) array('name' => 'phone', 'label' => 'Phone', 'value' => '555', 'access' => 2, 'iscore' => 0),
        (object) array('name' => 'internal-note', 'label' => 'Internal note', 'value' => array('secret', 'more'), 'access' => 3, 'iscore' => 0),
        (object) array('name' => 'board', 'label' => 'Board', 'value' => 'minutes', 'access' => 6, 'iscore' => 0),
        (object) array('name' => 'legacy', 'label' => 'Legacy', 'value' => 'old', 'access' => 0, 'iscore' => 0),
        (object) array('name' => 'nolevel', 'label' => 'No level', 'value' => 'x', 'iscore' => 0),
        (object) array('name' => 'empty', 'label' => 'Empty', 'value' => '', 'access' => 1, 'iscore' => 0),
    );
    $summary = function ($levels) use ($fields) { $tags = FlexicontentHelperNotificationMail::getFieldTags($fields, $levels); return explode("\n", $tags['fields']); };
    $all = FlexicontentHelperNotificationMail::getFieldTags($fields);
    $check($all['fields'] === "Color: red\nPhone: 555\nInternal note: secret, more\nBoard: minutes\nLegacy: old\nNo level: x", 'fields: without levels the summary lists every custom field with a value: ' . json_encode($all['fields']));
    $check($summary(array(1, 2, 3)) === array('Color: red', 'Phone: 555', 'Internal note: secret, more', 'Legacy: old', 'No level: x'), 'fields: moderators get the fields of their levels');
    $check($summary(array(1, 2)) === array('Color: red', 'Phone: 555', 'Legacy: old', 'No level: x'), 'fields: a registered recipient does not get the fields of the special level');
    $check($summary(array(1, 5)) === array('Color: red', 'Legacy: old', 'No level: x'), 'fields: a guest address gets public fields only');
    $check($summary(array()) === array('Legacy: old', 'No level: x'), 'fields: without any level only fields without access level remain');
    $limited = FlexicontentHelperNotificationMail::getFieldTags($fields, array(1));
    $check($limited['field_INTERNAL_NOTE'] === 'secret, more' && $limited['INTERNAL_NOTE'] === 'secret, more' && $limited['field_TITLE'] === 'T' && !isset($limited['TITLE']),
        'fields: the tag of a single field, written by the author of a template, is not limited');
    $check(array_keys($limited) === array_keys($all), 'fields: the same tags exist with and without levels');

    $levels = function ($recipients) use ($db) { $db->log = array(); Access::$asked = array(); return FlexicontentHelperNotificationMail::getRecipientViewLevels($recipients); };
    $check($levels(array('mod@example.org')) === array(1, 2, 3), 'recipients: a moderator has the levels of the moderator');
    $check($db->log === array("SELECT id, email FROM #__users WHERE block = 0 AND email IN ('mod@example.org')"), 'recipients: the users are read by their quoted addresses');
    $check($levels(array('mod@example.org', 'chief@example.org')) === array(1, 2, 3), 'recipients: only levels that all recipients have, also for an address in other letter case');
    $check($levels(array('mod@example.org', 'author@example.org', 'chief@example.org')) === array(1, 2), 'recipients: one registered recipient limits the levels of all');
    $check($levels(array('mod@example.org', 'stranger@elsewhere.test')) === array(1), 'recipients: an address that is not of a user counts as a guest');
    $check($levels(array('gone@example.org')) === array(1, 5), 'recipients: a blocked user counts as a guest');
    $check($levels(array('site@example.org')) === null && $levels(array()) === null && $levels(array('', ' ', null, array('x'))) === null && $db->log === array(), 'recipients: nobody but the site itself, no limit and no query');
    $check($levels(array('SITE@example.org', ' mod@example.org ', 'mod@example.org')) === array(1, 2, 3) && Access::$asked === array(21), 'recipients: the address of the site is not looked at, a user is looked at once');
    $check($levels(array("o'neil@example.org")) === array(1, 5) && $db->log === array("SELECT id, email FROM #__users WHERE block = 0 AND email IN ('o''neil@example.org')"), 'recipients: addresses are quoted');
    $db->fail_users = true;
    $check($levels(array('mod@example.org')) === array(1, 5), 'recipients: when the users can not be read, everybody counts as a guest');
    $db->fail_users = false;


    /**
     * 2. A mailer without addUnsafeTags() still sends
     */

    MailTemplate::$templates = array('com_flexicontent.notification.new' => (object) array('subject' => 'S {TITLE}', 'body' => 'B {FIELDS}', 'htmlbody' => ''));
    MailTemplate::$sent = array();
    $data = array('title' => 'A <b>title</b>', 'fields' => 'Color: red');
    $check(!method_exists('Joomla\CMS\Mail\MailTemplate', 'addUnsafeTags'), 'unsafe tags: the mailer double is one without the method');
    $check(FlexicontentHelperNotificationMail::send('new', array('mod@example.org'), $data, 'en-GB') === true && count(MailTemplate::$sent) === 1, 'unsafe tags: the mail is sent where the method is missing');
    $check(MailTemplate::$sent[0]->data === $data && MailTemplate::$sent[0]->recipients === array('mod@example.org'), 'unsafe tags: with the data and the recipient');
    $check(FlexicontentHelperNotificationMail::send('new', array('mod@example.org'), $data, 'en-GB', false, array('catid' => 1)) === true && MailTemplate::$sent[1] instanceof FlexicontentMailTemplate,
        'unsafe tags: also with the mailer that renders conditions');
    $check(CurrentJoomlaHelper::send('new', array('mod@example.org'), $data, 'en-GB') === true && MailTemplate::$sent[2]->unsafe === array('title', 'fields'), 'unsafe tags: where the method exists, all tags are marked');
    $check($app->messages === array(), 'unsafe tags: no warning is shown');


    /**
     * 3. The notification methods of the item model
     */

    $model = $source('admin/models/parentclassitem.php');
    $model_parts = '
        public $_cparams, $_record, $_db, $_id = 0, $items = array(), $fields = array(), $states = array(), $publishable = array(), $approvables = array(), $sent = array();
        public function get($name) { return $this->items[$this->_id][$name]; }
        public function _new_JRegistry($params) { return new \Joomla\Registry\Registry($params); }
        public function getNotificationFields() { return $this->fields; }
        public function isUserDraft($cid) { return $this->approvables; }
        public function getItem($id, $check_view_access = true, $no_cache = false) { $this->_id = $id; return (object) $this->items[$id]; }
        public function canEditState($item) { return in_array($item->id, $this->publishable); }
        public function setitemstate($id, $state) { $this->states[$id] = $state; }
        public function cleanCache($group = null, $client = 0) {}
        public ' . methodSource($model, '& getNotificationsConf') . '
        public ' . methodSource($model, 'getApprovalRequestReceivers') . '
        public ' . methodSource($model, 'approval');
    eval('final class ApprovalFixture { ' . $model_parts . '
        public function sendNotificationEmails(&$notify_vars, &$params, $manual = 0) { $this->sent[$this->_id] = clone $notify_vars; return true; } }');
    eval('final class MailFixture { ' . $model_parts . ' public ' . methodSource($model, 'sendNotificationEmails') . ' }');

    $items = array(
        10 => array('id' => 10, 'title' => 'Mine', 'categories' => array(1)),
        11 => array('id' => 11, 'title' => 'Not mine', 'categories' => array(1)),
        12 => array('id' => 12, 'title' => 'Busy', 'categories' => array(1)),
        13 => array('id' => 13, 'title' => 'I can publish', 'categories' => array(1)),
        14 => array('id' => 14, 'title' => 'Mine too', 'categories' => array(2)),
    );
    $db->item_categories = array(10 => array(1), 14 => array(2));
    $approvables = array(
        (object) array('id' => 11, 'catid' => 1, 'created_by' => 6, 'title' => 'Not mine', 'checked_out' => 0),
        (object) array('id' => 12, 'catid' => 1, 'created_by' => 5, 'title' => 'Busy', 'checked_out' => 6),
        (object) array('id' => 10, 'catid' => 1, 'created_by' => 5, 'title' => 'Mine', 'checked_out' => 0),
        (object) array('id' => 13, 'catid' => 1, 'created_by' => 5, 'title' => 'I can publish', 'checked_out' => 5),
        (object) array('id' => 14, 'catid' => 2, 'created_by' => 5, 'title' => 'Mine too', 'checked_out' => 0),
    );
    $approval = function (array $cparams) use ($items, $approvables, $db) {
        $fixture = new ApprovalFixture;
        $fixture->_cparams = new Registry($cparams); $fixture->_db = $db; $fixture->items = $items; $fixture->approvables = $approvables; $fixture->publishable = array(13);
        return $fixture;
    };

    // Notifications disabled, the default: the request is made, the mail goes to the address of the site
    $fixture = $approval(array('enable_notifications' => 0));
    $message = $fixture->approval(array(11, 12, 10, 13, 14));
    $check($fixture->states === array(10 => -3, 14 => -3), 'approval: with notifications disabled the items are set to pending without an error');
    $check(count($fixture->sent) === 2 && $fixture->sent[10]->notify_emails === array('site@example.org') && $fixture->sent[14]->notify_emails === array('site@example.org'),
        'approval: without configured receivers the request goes to the address of the site');
    $check(strpos($message, 'FLEXI_APPROVAL_NO_REQUEST_PRIV_EXCLUDED("Not mine")') !== false, 'approval: an item of another user is named by its own title: ' . $message);
    $check(strpos($message, 'FLEXI_APPROVAL_CHECKED_OUT_EXCLUDED("Busy")') !== false, 'approval: an item edited by another user is named by its own title');
    $check(strpos($message, 'FLEXI_APPROVAL_PUBLISHABLE_EXCLUDED("I can publish")') !== false && strpos($message, '2FLEXI_APPROVAL_ITEMS_SUBMITTED 3 FLEXI_APPROVAL_ITEMS_EXCLUDED') === 0, 'approval: the counts are right');
    $receivers = $approval(array('enable_notifications' => 0))->getApprovalRequestReceivers(10, 1);
    $check($receivers->notify_emails === array() && $receivers->notify_text === '', 'approval: no configuration, no receivers');

    // Receivers per category: every item of a batch gets its own
    $by_category = array('enable_notifications' => 1, 'nf_allow_cat_specific' => 1);
    $fixture = $approval($by_category);
    $fixture->approval(array(11, 12, 10, 13, 14));
    $check($fixture->sent[10]->notify_emails === array('mod@example.org', 'Chief@Example.org', 'gone@example.org'), 'approval: the first item gets the receivers of its category: ' . json_encode($fixture->sent[10]->notify_emails));
    $check($fixture->sent[14]->notify_emails === array('author@example.org'), 'approval: the next item of the batch gets the receivers of its own category: ' . json_encode($fixture->sent[14]->notify_emails));
    $check($fixture->sent[10]->isnew === 1 && $fixture->sent[10]->needs_publication_approval === 1 && array_keys($fixture->sent[14]->after_cats) === array(2), 'approval: the notification data of each item');

    // The configuration is read again for every call
    $fixture = $approval(array()); $fixture->_id = 10;
    $off = new Registry(array('enable_notifications' => 0));
    $on  = new Registry(array('enable_notifications' => 1, 'nf_allow_cat_specific' => 1, 'userlist_notify_new' => '21, author', 'usergrps_notify_new' => array('8', "8) OR (1", '0')));
    $check($fixture->getNotificationsConf($off) === false, 'configuration: disabled gives no configuration');
    $conf = $fixture->getNotificationsConf($on);
    $check(is_object($conf) && $conf->emails->notify_new_pending === array('mod@example.org', 'Chief@Example.org', 'gone@example.org'), 'configuration: a call after a disabled one is not answered from memory');
    $check($conf->emails->notify_new === array('mod@example.org', 'author@example.org', 'Chief@Example.org'), 'configuration: users by id, by login name and by group: ' . json_encode($conf->emails->notify_new));
    $fixture->_id = 14;
    $check($fixture->getNotificationsConf($on)->emails->notify_new_pending === array('author@example.org'), 'configuration: another item, other receivers');
    $check($fixture->getNotificationsConf($off) === false, 'configuration: and disabled again');
    $check(strpos(methodSource($model, '& getNotificationsConf'), 'static $nConf') === false, 'configuration: nothing is kept between calls');

    // The mail of a saved item
    $mail = function (array $options = array()) use ($db, $app, $mailer, $fields) {
        $options += array('templates' => array(), 'recipients' => array('mod@example.org'), 'new' => false, 'debug' => 0);
        MailTemplate::$templates = $options['templates']; MailTemplate::$sent = array();
        $mailer->mails = array(); $app->messages = array();
        $fixture = new MailFixture;
        $fixture->_cparams = new Registry(array('use_versioning' => 1)); $fixture->_db = $db; $fixture->_id = 30; $fixture->fields = $fields;
        $fixture->_record = (object) array('language' => '*', 'slug' => '30:x', 'categoryslug' => '1:news');
        $fixture->items = array(30 => array('id' => 30, 'title' => '<script>alert(1)</script> & "T"', 'state' => 1, 'creator' => '<b>Eve</b>', 'modifier' => "<em>Mo</em> O'Neil", 'created' => '2026-01-02 03:04:05',
            'modified' => '2026-02-03 04:05:06', 'created_by' => 5, 'catid' => 3, 'introtext' => '<p>Intro</p>', 'fulltext' => '', 'language' => '*', 'type_id' => 2));
        $vars = (object) array('needs_version_reviewal' => 0, 'needs_publication_approval' => 0, 'isnew' => $options['new'], 'notify_emails' => $options['recipients'], 'notify_text' => '',
            'before_cats' => $options['new'] ? array() : array(2 => (object) array('title' => '<s>Gone</s>')), 'after_cats' => array(1 => (object) array('title' => '<i>News</i>')),
            'original_item' => (object) array('title' => '<u>Old</u>', 'state' => 0, 'created_by' => 6, 'creator' => '<b>Old</b> owner'));
        $params = new Registry(array('nf_enable_debug' => $options['debug']));
        return $fixture->sendNotificationEmails($vars, $params);
    };

    // Without a mail template the body written in the code is used, it is HTML
    $check($mail() === true && count($mailer->mails) === 1 && $mailer->mails[0]['html'] === true, 'legacy mail: sent as HTML when there is no mail template');
    $body = $mailer->mails[0]['body'];
    foreach (array('<script>', '<b>Eve</b>', '<em>Mo</em>', '<u>Old</u>', '<b>Old</b>', '<s>Gone</s>', '<i>News</i>', "O'Neil", '& "T"') as $raw) {
        $check(strpos($body, $raw) === false, 'legacy mail: the body has no unencoded value: ' . $raw);
    }
    foreach (array('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;T&quot;', '&lt;u&gt;Old&lt;/u&gt; &nbsp; ==> &nbsp; ', '&lt;b&gt;Eve&lt;/b&gt;', '&lt;b&gt;Old&lt;/b&gt; owner', '&lt;em&gt;Mo&lt;/em&gt; O&#039;Neil',
        '1. &lt;i&gt;News&lt;/i&gt;<br/>', '1. &lt;s&gt;Gone&lt;/s&gt;<br/>', '<b>FLEXI_NF_CONTENT_TITLE</b>', '<a href="https://example.org/index.php?option=com_flexicontent') as $encoded) {
        $check(strpos($body, $encoded) !== false, 'legacy mail: the body has: ' . $encoded);
    }
    $check(strpos($mailer->mails[0]['subject'], 'FLEXI_NF_BY_USER(Ann <A> & "B" | ann)') !== false, 'legacy mail: the subject is plain text and is not encoded: ' . $mailer->mails[0]['subject']);
    $check($mail(array('new' => true)) === true && strpos($mailer->mails[0]['body'], '1. &lt;i&gt;News&lt;/i&gt;<br/>') !== false && strpos($mailer->mails[0]['body'], '<i>News</i>') === false, 'legacy mail: the categories of a new item are encoded');
    $mail(array('debug' => 1));
    $debug = implode("\n", $app->messages);
    $check(strpos($debug, 'SUBJECT: ') !== false && strpos($debug, 'Ann <A>') === false && strpos($debug, 'Ann &lt;A&gt; &amp; &quot;B&quot;') !== false, 'legacy mail: the debug message shows the subject encoded');

    // With a mail template the values go to Joomla as they are, and {FIELDS} depends on the recipients
    $templates = array('com_flexicontent.notification.existing' => (object) array('subject' => 'S {TITLE}', 'body' => 'B {FIELDS}', 'htmlbody' => ''));
    $check($mail(array('templates' => $templates)) === true && $mailer->mails === array() && count(MailTemplate::$sent) === 1, 'template mail: sent with the template, not with the legacy body');
    $sent = MailTemplate::$sent[0]->data;
    $check($sent['title'] === '<script>alert(1)</script> & "T"' && $sent['creator'] === '<b>Eve</b>' && $sent['categories'] === '<i>News</i>', 'template mail: the tags are not encoded twice, Joomla encodes them for the HTML part');
    $check($sent['fields'] === "Color: red\nPhone: 555\nInternal note: secret, more\nLegacy: old\nNo level: x", 'template mail: a moderator gets the fields of the moderator levels: ' . json_encode($sent['fields']));
    $mail(array('templates' => $templates, 'recipients' => array('mod@example.org', 'author@example.org')));
    $sent = MailTemplate::$sent[0]->data;
    $check($sent['fields'] === "Color: red\nPhone: 555\nLegacy: old\nNo level: x" && $sent['field_INTERNAL_NOTE'] === 'secret, more', 'template mail: with a registered recipient the special field leaves {FIELDS}: ' . json_encode($sent['fields']));
    $mail(array('templates' => $templates, 'recipients' => array('site@example.org')));
    $check(strpos(MailTemplate::$sent[0]->data['fields'], 'Board: minutes') !== false, 'template mail: the address of the site alone gets all fields');
    $send_source = methodSource($model, 'sendNotificationEmails');
    $check(strpos($send_source, 'FlexicontentHelperNotificationMail::getRecipientViewLevels($notify_emails)') !== false, 'template mail: the levels are those of the recipients of this mail');


    /**
     * 4. Mails of the notification plugin (item owner, subscribers)
     */

    Text::$strings = array(
        'FLEXI_NOTIFICATION_MESSAGE_OWNER' => "Hello %s,\n\nOne of your items has been updated :\n\nId : %s\nTitle : %s\nCategory : %s\nState : %s\nLink : %s\n\nCheers,\n%s.",
        'FLEXI_NOTIFICATION_MESSAGE' => "Hello %s,\n\nAn item of your favourites has been updated :\n\nId : %s\nTitle : %s\nCategory : %s\nLink : %s\n\nCheers,\n%s.",
        'FLEXI_SUBJECT_DEFAULT' => 'Update notification',
    );
    $plugin = $source('plugins/flexicontent/flexinotify/flexinotify.php');
    $route_helper = "require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');";
    $check(substr_count($plugin, $route_helper) === 2, 'plugin: the two mail methods load the route helper');
    eval('final class NotifyFixture { public ' . str_replace($route_helper, '', methodSource($plugin, 'sendOwnerNotification')) . ' public ' . str_replace($route_helper, '', methodSource($plugin, 'sendNotifications')) . ' }');

    $GLOBALS['globalcats'] = array(3 => (object) array('title' => 'News & <i>more</i>', 'slug' => '3:news'));
    $item = (object) array('id' => 30, 'title' => '<img src=x onerror=alert(1)> "T"', 'alias' => 'x', 'catid' => 3, 'state' => 1, 'language' => '*');
    $owner = (object) array('name' => 'Owner <b>O</b>', 'email' => 'owner@example.org');
    $notify_owner = function (array $params) use ($mailer, $app, $item, $owner) {
        $mailer->mails = array(); $app->messages = array();
        (new NotifyFixture)->sendOwnerNotification($item, $owner, new Registry($params));
        return $mailer->mails[0];
    };
    $sent = $notify_owner(array());
    $check($sent['html'] === true && $sent['to'] === array('owner@example.org') && $sent['subject'] === 'Update notification', 'plugin: the owner gets an HTML mail with the default subject');
    $check(strpos($sent['body'], 'Hello Owner &lt;b&gt;O&lt;/b&gt;,<br />') === 0, 'plugin: the name of the owner is encoded: ' . substr($sent['body'], 0, 60));
    $check(strpos($sent['body'], 'Title : &lt;img src=x onerror=alert(1)&gt; &quot;T&quot;<br />') !== false && strpos($sent['body'], 'Category : News &amp; &lt;i&gt;more&lt;/i&gt;<br />') !== false, 'plugin: title and category are encoded');
    $check(strpos($sent['body'], '<img') === false && strpos($sent['body'], '<i>') === false && strpos($sent['body'], '<b>') === false && strpos($sent['body'], 'Site &amp; &lt;Co&gt; - https://example.org/') !== false, 'plugin: no value arrives as markup');
    $check(strpos($sent['body'], 'Link : <a href="https://example.org/index.php?option=com_flexicontent') !== false && strpos($sent['body'], 'State : FLEXI_PUBLISHED<br />') !== false, 'plugin: the link and the state are in the mail');

    // The custom body of the owner mail is the body parameter, not the subject parameter
    $sent = $notify_owner(array('mailsubject_owner' => 'Your item <changed>', 'mailbody_owner' => "Dear %s,\nitem %s (%s) of %s is now %s.\n%s\n%s"));
    $check($sent['subject'] === 'Your item <changed>', 'plugin: the custom subject is used as subject, plain text');
    $check(strpos($sent['body'], 'Dear Owner &lt;b&gt;O&lt;/b&gt;,<br />' . "\n" . 'item 30 (&lt;img src=x onerror=alert(1)&gt; &quot;T&quot;) of News &amp; &lt;i&gt;more&lt;/i&gt; is now FLEXI_PUBLISHED.') === 0,
        'plugin: the custom body is used, with encoded values: ' . substr($sent['body'], 0, 80));
    $check(strpos($sent['body'], 'Your item') === false, 'plugin: the subject is not a part of the body');
    $check(strpos($notify_owner(array('mailbody_owner' => 'Only text, 100 percent'))['body'], 'Only text, 100 percent') === 0, 'plugin: a custom body without placeholders is used as it is');
    foreach (array('%s %s %s %s %s %s %s %s', 'Done 100% %s %y', '   ') as $unusable) {
        $check(strpos($notify_owner(array('mailbody_owner' => $unusable))['body'], 'Hello Owner &lt;b&gt;O&lt;/b&gt;,') === 0, 'plugin: a custom body that the values do not fit gives the default text: ' . $unusable);
    }
    $check(strpos(methodSource($plugin, 'sendOwnerNotification'), "cleanSubject(\$params->get('mailsubject_owner'))\n\t\t\t: \\Joomla\\CMS\\Language\\Text::_('FLEXI_SUBJECT_DEFAULT');\n\n\t\t\$body") === false, 'plugin: the body is not built from the subject');

    $subscribers = array((object) array('name' => 'Sub <u>One</u>', 'email' => 'one@example.org'), (object) array('name' => 'Two', 'email' => 'two@example.org'));
    $mailer->mails = array();
    (new NotifyFixture)->sendNotifications($item, $subscribers, new Registry(array()));
    $check(count($mailer->mails) === 2 && $mailer->mails[0]['html'] === true && strpos($mailer->mails[0]['body'], 'Hello Sub &lt;u&gt;One&lt;/u&gt;,<br />') === 0, 'plugin: the name of a subscriber is encoded');
    $check(strpos($mailer->mails[1]['body'], 'Hello Two,') === 0 && strpos($mailer->mails[1]['body'], 'Title : &lt;img src=x onerror=alert(1)&gt; &quot;T&quot;<br />') !== false
        && strpos($mailer->mails[1]['body'], 'Category : News &amp; &lt;i&gt;more&lt;/i&gt;<br />') !== false && strpos($mailer->mails[1]['body'], '<img') === false, 'plugin: title and category of the subscriber mail are encoded');
    $mailer->mails = array();
    (new NotifyFixture)->sendNotifications($item, $subscribers, new Registry(array('send_personalized' => 0)));
    $check(count($mailer->mails) === 1 && $mailer->mails[0]['bcc'] === array('one@example.org', 'two@example.org') && strpos($mailer->mails[0]['body'], 'Hello FLEXI_SUBSCRIBER,') === 0
        && strpos($mailer->mails[0]['body'], '<img') === false, 'plugin: the single mail to all subscribers is encoded too');


    /**
     * 5. CSV import: where imported files may come from
     */

    require $repo . '/site/classes/helpers/security.php';
    $import = $source('admin/controllers/import.php');
    eval('final class ImportFixture { public ' . methodSource($import, '_isImportSourceFolder') . ' }');
    $folder_ok = function ($request_value, $used = true) {
        $folder = flexicontent_security::siteRelativeFolder($request_value);
        return $folder !== false && (new ImportFixture)->_isImportSourceFolder($folder, $used);
    };
    $app->config += array('tmp_path' => $site . '/tmp', 'log_path' => $site . '/administrator/logs');
    foreach (array('tmp/fcimport_docs', 'tmp/fcimport_media', '/tmp/fcimport_docs/', 'tmp\\fcimport_docs', 'images', 'images/docs', 'media', 'private', 'private/tmp', 'private/logs/old') as $folder) {
        $check($folder_ok($folder), 'import: files can be imported from: ' . $folder);
    }
    foreach (array('', '/', 'tmp', '/tmp/', 'tmp\\', 'TMP', 'cache', 'cache/com_x', 'logs', 'administrator', 'administrator/logs', 'administrator/components', 'Administrator/Logs', 'no-such-folder', '../' . basename($site), 'tmp/..', 'images/../tmp') as $folder) {
        $check(!$folder_ok($folder), 'import: files can not be imported from: ' . json_encode($folder));
    }
    $check($folder_ok('', false) && $folder_ok('/', false), 'import: an empty folder is accepted for an import without a file column that uses it');
    $check(!$folder_ok('tmp', false) && !$folder_ok('administrator/logs', false), 'import: a folder of the system is refused also when no column uses it');
    $fixture = new ImportFixture;
    $check(!$fixture->_isImportSourceFolder(false) && !$fixture->_isImportSourceFolder(null) && !$fixture->_isImportSourceFolder(array('images')), 'import: a refused folder value stays refused');
    $check(!$fixture->_isImportSourceFolder('.') && !$fixture->_isImportSourceFolder('images/..') && $fixture->_isImportSourceFolder('images'), 'import: another spelling of the site root is the site root');

    // The temporary and the log folder that the site configuration names
    $app->config = array('tmp_path' => $site . '/private/tmp', 'log_path' => $site . (DIRECTORY_SEPARATOR === '\\' ? '\\private\\logs\\' : '/private/logs/')) + $app->config;
    $check(!$folder_ok('private/tmp') && $folder_ok('private/tmp/fcimport_docs') && $folder_ok('private'), 'import: the configured temporary folder is refused, a folder made inside it is not');
    $check(!$folder_ok('private/logs') && !$folder_ok('private/logs/old'), 'import: the configured log folder is refused with its sub-folders');
    $check(!$folder_ok('tmp') && !$folder_ok('administrator/logs') && $folder_ok('tmp/fcimport_docs') && $folder_ok('images/docs'), 'import: the standard folders are judged as before');
    $app->config = array('tmp_path' => sys_get_temp_dir(), 'log_path' => '') + $app->config;
    $check($folder_ok('images') && $folder_ok('private/tmp') && !$folder_ok('tmp') && !$folder_ok('logs'), 'import: folders of the configuration that are outside the site, or not set, change nothing');
    unset($app->config['tmp_path'], $app->config['log_path']);
    $check($folder_ok('images/docs') && !$folder_ok('tmp') && !$folder_ok('cache'), 'import: also without these settings');

    $check(strpos($import, "if (!\$this->_isImportSourceFolder(\$conf[\$folder_var], isset(\$used_folders[\$folder_var])))") !== false
        && strpos($import, '_isImportSourceFolder($conf[$folder_var]') > strpos($import, "flexicontent_security::siteRelativeFolder(\$jinput->get('docs_folder', '', 'string'))"),
        'import: both folders of the request are checked after they were found to be inside the site');
    $check(strpos($import, "\$file_field_folders = array('image' => 'media_folder', 'file' => 'docs_folder', 'mediafile' => 'docs_folder');") !== false
        && strpos($import, "\$ff_types_to_paths = array('image' => \$mfolder, 'file' => \$dfolder, 'mediafile' => \$dfolder);") !== false, 'import: the columns that use a folder are those of the file check');


    /**
     * 6. Template save
     */

    $templates_controller = $source('admin/controllers/templates.php');
    $check(strpos($templates_controller, "\$folder  = \$this->input->getCmd('folder', 'table');") !== false && strpos($templates_controller, "getString('folder'") === false, 'templates: the folder of a saved layout is read as a plain name');
    $check(strpos($source('admin/models/template.php'), "\$folder = \$jinput->getCmd('folder', 'table');") !== false, 'templates: as the model reads it');

    restore_error_handler();
    if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
