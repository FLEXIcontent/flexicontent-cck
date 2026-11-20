<?php
/** A frontend request must not depend on the colliding administrator item model. */
namespace Joomla\CMS { class Factory { public static $app; public static function getApplication() { return self::$app; } } }
namespace Joomla\CMS\Session { class Session { public static bool $valid = true; public static function checkToken($type) { return self::$valid; } } }
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace Joomla\Registry { class Registry { public function __construct($data) {} public function get($key, $default) { return $default; } } }
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($level, $message, $file, $line) { throw new \ErrorException($message, 0, $level, $file, $line); });
    class EndResponse extends \RuntimeException {}
    function jexit($text = '') { echo $text; throw new EndResponse; }
    class FlexicontentFields { public static function getFieldsByIds($ids) { return [(object) ['attribs' => '{}']]; } }
    class FLEXIUtilities { public static function getCache(...$args) { return new class { public function clean($group) {} }; } }
    class flexicontent_db {
        public static array $saved = [];
        public static function getFavoured($type, $id, $user) { return self::$saved[$type][$id][$user] ?? false; }
        public static function addfav($type, $id, $user) { self::$saved[$type][$id][$user] = true; }
        public static function removefav($type, $id, $user) { unset(self::$saved[$type][$id][$user]); }
        public static function getFavourites($type, $id) { return count(self::$saved[$type][$id] ?? []); }
    }
    $source = file_get_contents(__DIR__ . '/../site/controller.php');
    $start = strpos($source, 'function ajaxfav('); $method = ''; $depth = 0; $opened = false;
    foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
        if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
        $method .= is_array($token) ? $token[1] : $token;
        if ($token === '{') { ++$depth; $opened = true; }
        if ($token === '}' && --$depth === 0 && $opened) break;
    }
    eval('class FavouriteControllerFixture { public $input; public function getModel($type) { return new stdClass; } ' . $method . '}');
    \Joomla\CMS\Factory::$app = new class { public int $user = 10; public function getIdentity() { return (object) ['id' => $this->user]; } };
    $controller = new FavouriteControllerFixture;
    $controller->input = new class {
        public string $type = 'item';
        public function get($name, $default = null, $filter = null) { return $name === 'id' ? 47 : $this->type; }
    };
    $request = static function () use ($controller) { ob_start(); try { $controller->ajaxfav(); } catch (EndResponse $end) {} return ob_get_clean(); };
    foreach (['item' => 0, 'category' => 1] as $type => $index) {
        $controller->input->type = $type;
        if ($request() !== 'added' || !flexicontent_db::getFavoured($index, 47, 10)) throw new \RuntimeException('Favourite was not added to requested type/ID/user');
        \Joomla\CMS\Factory::$app->user = 11;
        if ($request() !== 'added') throw new \RuntimeException('Different user favourite lost');
        \Joomla\CMS\Factory::$app->user = 10;
        if ($request() !== 'removed' || flexicontent_db::getFavoured($index, 47, 10) || !flexicontent_db::getFavoured($index, 47, 11)) throw new \RuntimeException('Removal crossed user boundary');
        \Joomla\CMS\Session\Session::$valid = false;
        if ($request() !== 'JINVALID_TOKEN' || flexicontent_db::getFavoured($index, 47, 10)) throw new \RuntimeException('Token guard failed');
        \Joomla\CMS\Session\Session::$valid = true;
    }
    echo "PASS: item/category favourites, model collision, user isolation and token guard\n";
}
