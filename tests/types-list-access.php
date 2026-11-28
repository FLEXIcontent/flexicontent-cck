<?php
/**
 * The "choose a type" list that opens from the add button (view=types, layout=typeslist). On the site it is reachable
 * without a login, because the add button can be shown to guests. It lists the types of the visitor's access levels
 * and the types that their own ACL lets the visitor create. Renders the real template against doubles.
 */
namespace Joomla\CMS {
    final class Factory {
        public static $app;
        public static function getApplication() { return self::$app; }
    }
}
namespace Joomla\CMS\Language {
    final class Text { public static function _($key) { return $key; } }
}
namespace Joomla\CMS\Session {
    final class Session { public static function getFormToken() { return 'token'; } }
}
namespace Joomla\CMS\Uri {
    final class Uri { public static function base() { return 'https://example.org/'; } }
}
namespace {
    define('_JEXEC', 1);

    $failures = array(); $checks = 0;
    $check = static function ($ok, $label) use (&$failures, &$checks) { ++$checks; if (!$ok) $failures[] = $label; };

    final class flexicontent_html {
        public static $types = array();
        public static function getTypesList($type_ids = false, $check_perms = false, $published = true) {
            $types = array();
            foreach (self::$types as $id => $type) $types[$id] = (object) ($type + array('id' => $id, 'description' => ''));
            return $types;
        }
        public static function is_safe_url($url) { return true; }
        public static function striptagsandcut($text, $length, &$uncut_length = 0, $ops = array()) { return strip_tags($text); }
    }
    final class FixtureUser {
        public $id, $levels, $rights;
        public function __construct($id, array $levels, array $rights = array()) { $this->id = $id; $this->levels = $levels; $this->rights = $rights; }
        public function authorise($action, $asset = null) { return in_array($action . '@' . $asset, $this->rights, true); }
        public function getAuthorisedViewLevels() { return $this->levels; }
    }
    final class FixtureApp {
        public $client = 'site', $identity, $input;
        public function __construct() {
            $this->input = new class {
                public $data = array('action' => 'new');
                public function getCmd($name, $default = '') { return isset($this->data[$name]) ? $this->data[$name] : $default; }
                public function getInt($name, $default = 0) { return isset($this->data[$name]) ? (int) $this->data[$name] : $default; }
            };
        }
        public function isClient($name) { return $this->client === $name; }
        public function getIdentity() { return $this->identity; }
    }
    $app = \Joomla\CMS\Factory::$app = new FixtureApp;

    // Access level 1 is public, 2 registered, 3 special, 0 is a type without a level (shown to all, as in the item lists).
    // itemscreatable 0: any author, 1: by the ACL of the type and hidden otherwise, 2: by ACL and shown disabled otherwise
    flexicontent_html::$types = array(
        1 => array('name' => 'Article',       'itemscreatable' => 0, 'access' => 1),
        2 => array('name' => 'Staff memo',    'itemscreatable' => 0, 'access' => 3),
        3 => array('name' => 'Press release', 'itemscreatable' => 1, 'access' => 1),
        4 => array('name' => 'Tender',        'itemscreatable' => 2, 'access' => 1),
        5 => array('name' => 'Board paper',   'itemscreatable' => 2, 'access' => 3),
        6 => array('name' => 'Partner news',  'itemscreatable' => 1, 'access' => 3),
        7 => array('name' => 'Old type',      'itemscreatable' => 0, 'access' => 0),
    );

    // The listed types: "<name>" with a link to the new item form, "<name> (disabled)" without one
    $listed = static function ($user, $client = 'site') use ($app) {
        $app->client = $client; $app->identity = $user;
        ob_start();
        (static function () { include dirname(__DIR__) . '/admin/views/types/tmpl/typeslist.php'; })();
        $html  = ob_get_clean();
        $names = array();
        foreach (flexicontent_html::$types as $id => $type) {
            if (strpos($html, $type['name']) === false) continue;
            $names[] = $type['name'] . (strpos($html, 'typeid=' . $id . '&amp;') !== false ? '' : ' (disabled)');
        }
        return implode(', ', $names);
    };

    $guest  = new FixtureUser(0, array(1));
    $author = new FixtureUser(31, array(1, 2), array('core.create@com_flexicontent.type.3', 'core.create@com_flexicontent.type.6'));
    $staff  = new FixtureUser(32, array(1, 2, 3));

    $check($listed($guest) === 'Article, Tender (disabled), Old type', 'site: a guest sees the public types, not the types of other access levels');
    $check($listed($author) === 'Article, Press release, Tender (disabled), Partner news, Old type', 'site: an author also sees the types that the ACL of the type lets him create');
    $check($listed($staff) === 'Article, Staff memo, Tender (disabled), Board paper (disabled), Old type', 'site: a user of the access level sees its types');
    $check($listed(new FixtureUser(33, array('1', '3'))) === 'Article, Staff memo, Tender (disabled), Board paper (disabled), Old type', 'site: access levels given as text are understood');
    $check($listed($guest, 'administrator') === 'Article, Staff memo, Tender (disabled), Board paper (disabled), Old type', 'backend: the list is not limited by access level');
    $check($listed($author, 'administrator') === 'Article, Staff memo, Press release, Tender (disabled), Board paper (disabled), Partner news, Old type', 'backend: nor for an author');

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
