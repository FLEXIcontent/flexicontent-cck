<?php
/** Standalone: php _tools/tests/category-association-propagation.php */
namespace Joomla\CMS {
    class Factory {
        public static $db;
        public static $user;
        public static function getDbo() { return self::$db; }
        public static function getUser() { return self::$user; }
        public static function getApplication() {
            return new class { public function getIdentity() { return Factory::$user; } };
        }
    }
}
namespace {
    define('_JEXEC', 1);
    require __DIR__ . '/../../site/classes/helpers/db.php';

    class AssociationUser {
        public $id = 7;
        public $edit = true;
        public $editOwn = false;
        public function authorise($action, $asset) {
            if ($asset !== 'com_content.article.2') throw new \RuntimeException('Wrong target asset');
            return $action === 'core.edit' ? $this->edit : $this->editOwn;
        }
    }
    class AssociationDatabase {
        public $query;
        public $target;
        public $relations = array(30, 31);
        public $writes = 0;
        public $fallback = false;
        public function __construct() { $this->target = (object) array('catid'=>30, 'created_by'=>8, 'checked_out'=>0); }
        public function setQuery($q) { $this->query = $q; return $this; }
        public function quote($s) { return "'" . $s . "'"; }
        public function qn($s) { return '`' . $s . '`'; }
        public function loadResult() {
            // The source main category is 10; the target keeps its own main category
            // when only secondary categories are shared.
            if (strpos($this->query, 'catid FROM #__content WHERE id = 2') !== false) return $this->target->catid;
            return 10;
        }
        public function loadColumn() { return strpos($this->query, 'itemid = 1') !== false ? array(10, 11) : $this->relations; }
        public function loadObject() { return $this->target ? clone $this->target : null; }
        public function loadObjectList() {
            $rows = array((object) array('src_id'=>10, 'id'=>20, 'language'=>'fr-FR'));
            if (!$this->fallback) $rows[] = (object) array('src_id'=>11, 'id'=>21, 'language'=>'fr-FR');
            return $rows;
        }
        public function execute() {
            $this->writes++;
            if (preg_match('/SET catid = (\d+)/', $this->query, $m)) $this->target->catid = (int) $m[1];
            elseif (strpos($this->query, 'DELETE') === 0) $this->relations = array();
            elseif (strpos($this->query, 'INSERT') === 0) {
                preg_match_all('/\((\d+),2\)/', $this->query, $m);
                $this->relations = array_map('intval', $m[1]);
                sort($this->relations);
            } else throw new \RuntimeException('Unexpected write: ' . $this->query);
        }
    }
    class AssociationHelper extends flexicontent_db {
        public static $flags;
        public static function getCategoriesUntranslatable() { return self::$flags; }
        public static function getLangAssocs($ids, $config = null) {
            return array(1 => array(1 => (object) array('language'=>'en-GB'), 2 => (object) array('language'=>'fr-FR')));
        }
    }
    $checks = 0;
    function same($expected, $actual, $label) {
        global $checks;
        $checks++;
        if ($expected !== $actual) throw new \RuntimeException($label . ': ' . var_export($actual, true));
    }
    function resetCase($main = true, $secondary = true) {
        \Joomla\CMS\Factory::$db = new AssociationDatabase;
        \Joomla\CMS\Factory::$user = new AssociationUser;
        AssociationHelper::$flags = array('main'=>$main, 'secondary'=>$secondary);
        return \Joomla\CMS\Factory::$db;
    }
    foreach (array(array(false,false),array(true,false),array(false,true),array(true,true)) as $flags) {
        $db = resetCase($flags[0], $flags[1]);
        $updated = AssociationHelper::propagateCategoriesToAssocs(1);
        $main = $flags[0] ? 20 : 30;
        $cats = array($main, $flags[1] ? 21 : 31); sort($cats);
        same($main, $db->target->catid, 'main flag');
        same($cats, $db->relations, 'independent secondary flag');
        same($flags[0] || $flags[1] ? array(2) : array(), $updated, 'updated IDs');
    }
    foreach (array('denied','own-not-owner','guest-own','other-checkout','missing') as $case) {
        $db = resetCase(); $user = \Joomla\CMS\Factory::$user;
        if ($case === 'denied' || $case === 'own-not-owner') $user->edit = false;
        if ($case === 'own-not-owner') $user->editOwn = true;
        if ($case === 'guest-own') { $user->id = 0; $user->edit = false; $user->editOwn = true; $db->target->created_by = 0; }
        if ($case === 'other-checkout') $db->target->checked_out = 8;
        if ($case === 'missing') $db->target = null;
        $before = serialize(array($db->target, $db->relations));
        same(array(), AssociationHelper::propagateCategoriesToAssocs(1), $case . ' skipped');
        same(0, $db->writes, $case . ' no writes');
        same($before, serialize(array($db->target, $db->relations)), $case . ' unchanged');
    }
    foreach (array('own','self-checkout') as $case) {
        $db = resetCase(); $user = \Joomla\CMS\Factory::$user;
        if ($case === 'own') { $user->edit = false; $user->editOwn = true; $db->target->created_by = $user->id; }
        else $db->target->checked_out = $user->id;
        same(array(2), AssociationHelper::propagateCategoriesToAssocs(1), $case . ' allowed');
        same(array(20,21), $db->relations, $case . ' categories');
    }
    $db = resetCase(); $db->fallback = true;
    AssociationHelper::propagateCategoriesToAssocs(1);
    same(array(11,20), $db->relations, 'unassociated category fallback');
    $db = resetCase();
    same(array(), AssociationHelper::propagateCategoriesToAssocs(0), 'invalid source');
    same(0, $db->writes, 'invalid source no writes');
    echo "$checks passed\n";
}
