<?php
/**
 * Permissions of a content type. A type restricts the creation of its items only: "create items" is the only item
 * action in the "type" section of access.xml. Editing, publishing and deleting items is decided by the permissions of
 * the item and its category, so the type lookup of the item model answers true for those rules and must keep doing so
 * while access.xml does not offer them: a lookup of a rule that no site can set per type would answer with what the
 * component grants. Runs the real lookup and the real edit checks of the item model against doubles; no Joomla.
 */
namespace Joomla\CMS {
    final class Factory {
        public static $app;
        public static function getApplication() { return self::$app; }
    }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_ROOT', dirname(__DIR__));

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

    // A user whose rights are given per asset ("action@asset"); every lookup is recorded
    final class FixtureUser {
        public $id, $rights, $asked = array();
        public function __construct($id, array $rights = array()) { $this->id = $id; $this->rights = $rights; }
        public function authorise($action, $asset = null) { $this->asked[] = $action . '@' . $asset; return in_array($action . '@' . $asset, $this->rights, true); }
        public function get($name) { return $this->$name; }
    }
    final class FixtureSession {
        public function has($name, $group = null) { return false; }
        public function get($name, $default = null, $group = null) { return $default; }
    }
    final class FixtureApp {
        public $identity, $session;
        public function __construct() { $this->session = new FixtureSession; }
        public function getIdentity() { return $this->identity; }
        public function getSession() { return $this->session; }
    }
    $app = \Joomla\CMS\Factory::$app = new FixtureApp;

    $permSource  = file_get_contents(JPATH_ROOT . '/site/helpers/permission.php');
    $itemSource  = file_get_contents(JPATH_ROOT . '/admin/models/parentclassitem.php');
    $accessXml   = simplexml_load_file(JPATH_ROOT . '/admin/access.xml');

    // The real lookup of the permission helper, and the real edit checks of the item model that use it
    eval('final class FlexicontentHelperPerm { public static ' . methodSource($permSource, 'checkTypeAccess') . ' }');
    eval('final class TypeAccessItemModel {
        public $type_alias = "com_content.article", $_record = null;
        public ' . methodSource($itemSource, 'canEdit') . '
        public ' . methodSource($itemSource, 'canEditState') . ' }');
    $model = new TypeAccessItemModel;

    $restricted = (object) array('id' => 0, 'itemscreatable' => 1);
    $open       = (object) array('id' => 0, 'itemscreatable' => 0);
    $nobody     = new FixtureUser(40);

    // 1. access.xml: the item actions that the permissions of a type have
    $typeActions = array();
    foreach ($accessXml->xpath('/access/section[@name="type"]/action') as $action) $typeActions[] = (string) $action['name'];
    sort($typeActions);
    $check($typeActions === array('core.create', 'flexicontent.change.cat', 'flexicontent.change.cat.feat', 'flexicontent.change.cat.sec'),
        'access.xml: a type has the "create items" action and the category change actions only. A new item action of the type must be implemented in FlexicontentHelperPerm::checkTypeAccess() and covered here before it is offered');

    // 2. Rules that a type does not have: always allowed, and the ACL is not asked
    $typeId = 100;
    foreach (array('core.edit', 'core.edit.own', 'core.edit.state', 'core.edit.state.own', 'core.delete', 'core.delete.own') as $rule) {
        $app->identity = $nobody; $nobody->asked = array();
        $check(FlexicontentHelperPerm::checkTypeAccess(++$typeId, $rule) === true && $nobody->asked === array(), 'type: ' . $rule . ' is no permission of a type, it is allowed without a lookup');
        $check(FlexicontentHelperPerm::checkTypeAccess(++$typeId, $rule, $restricted) === true && $nobody->asked === array(), 'type: ' . $rule . ' also for a type that restricts the creation of its items');
        $check(!in_array($rule, $typeActions, true), 'access.xml: ' . $rule . ' is not offered in the permissions of a type');
    }
    $check(FlexicontentHelperPerm::checkTypeAccess(0, 'core.edit') === true && FlexicontentHelperPerm::checkTypeAccess(0, 'core.create', $restricted) === true, 'type: an item without a type is not restricted by one');

    // 3. Creation of items: decided with the type record, for the current user
    $app->identity = $nobody; $nobody->asked = array();
    $check(FlexicontentHelperPerm::checkTypeAccess(201, 'core.create') === true && $nobody->asked === array(), 'create: without the type record the lookup cannot tell if the type uses its ACL, and allows');
    $check(FlexicontentHelperPerm::checkTypeAccess(202, 'core.create', $open) === true && $nobody->asked === array(), 'create: a type of any author is allowed without a lookup');
    $check(FlexicontentHelperPerm::checkTypeAccess(203, 'core.create', $restricted) === false && $nobody->asked === array('core.create@com_flexicontent.type.203'),
        'create: a type that uses its ACL is refused for a user without its create right');
    $app->identity = $creator = new FixtureUser(41, array('core.create@com_flexicontent.type.204'));
    $check(FlexicontentHelperPerm::checkTypeAccess(204, 'core.create', $restricted) === true && $creator->asked === array('core.create@com_flexicontent.type.204'),
        'create: and allowed for a user with it');
    $check(strpos(methodSource($itemSource, 'canCreateType'), "\$user->authorise('core.create', 'com_flexicontent.type.' . \$type->id)") !== false
        && strpos(methodSource($itemSource, 'canCreateType'), '$type->itemscreatable') !== false, 'create: the item model checks the creation of items with the type record');

    // 4. Editing an item: the permissions of the item decide, whatever the type of the item
    $item = static function ($id, $type_id, $created_by) { return (object) array('id' => $id, 'type_id' => $type_id, 'created_by' => $created_by, 'catid' => 5); };
    $editor   = new FixtureUser(42, array('core.edit@com_content.article.70', 'core.edit.state@com_content.article.70'));
    $ownEdit  = new FixtureUser(43, array('core.edit.own@com_content.article.71', 'core.edit.own@com_content.article.72', 'core.edit.state.own@com_content.article.71'));
    $check($model->canEdit($item(70, 301, 50), $editor) === true, 'edit: allowed by the edit right of the item');
    $check($model->canEdit($item(73, 301, 50), $editor) === false, 'edit: refused without a right on the item, the type gives none');
    $check($model->canEdit($item(71, 302, 43), $ownEdit) === true && $model->canEdit($item(72, 302, 50), $ownEdit) === false, 'edit: the right to edit own items is for the owner');
    $check($model->canEdit($item(70, 0, 50), $editor) === true, 'edit: an item without a type is checked the same way');
    $check($model->canEditState($item(70, 303, 50), $editor) === true && $model->canEditState($item(73, 303, 50), $editor) === false, 'state: allowed by the right on the item only');
    $check($model->canEditState($item(71, 304, 43), $ownEdit) === true && $model->canEditState($item(72, 304, 43), $ownEdit) === false, 'state: the right for own items is for the owner, on the item it is given for');
    $asked = array_merge($editor->asked, $ownEdit->asked);
    $check($asked !== array() && !preg_grep('/@com_flexicontent\.type\./', $asked), 'edit: no permission of the type is looked up for it');

    if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL . count($failures) . ' of ' . $checks . " checks failed\n"); exit(1); }
    echo $checks . " checks passed\n";
}
