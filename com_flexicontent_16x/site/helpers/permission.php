<?php

defined( '_JEXEC' ) or die( 'Restricted access' );
class FlexicontentHelperPerm{
	function getPerm($force = false) {
		$user = &JFactory::getUser();
		static $permission;
		if(!$permission || $force) {
			// handle jcomments integration
			if (JPluginHelper::isEnabled('system', 'jcomments.system') || JPluginHelper::isEnabled('system', 'jcomments')) {
				$CanComments 	= 1;
			} else {
				$CanComments 	= 0;
			}
			$permission = new stdClass;
			//component
			$permission->CanConfig 		= JAccess::check($user->id, 'flexicontent.admin', 			'com_flexicontent');
			$permission->CanRights	 	= JAccess::check($user->id, 'flexicontent.manage', 			'com_flexicontent');
			$permission->CanTypes 		= JAccess::check($user->id, 'flexicontent.managetype',		'com_flexicontent');
			$permission->CanFields 		= JAccess::check($user->id, 'flexicontent.fields', 			'com_flexicontent');
			$permission->CanTags 		= JAccess::check($user->id, 'flexicontent.tags', 			'com_flexicontent');
			$permission->CanArchives 	= JAccess::check($user->id, 'flexicontent.archives', 			'com_flexicontent');
			$permission->CanStats	 	= JAccess::check($user->id, 'flexicontent.stats', 			'com_flexicontent');
			$permission->CanTemplates	= JAccess::check($user->id, 'flexicontent.templates', 		'com_flexicontent');
			$permission->CanVersion	 	= JAccess::check($user->id, 'flexicontent.versioning', 		'com_flexicontent');
			$permission->CanUseTags	= JAccess::check($user->id, 'flexicontent.usetags', 			'com_flexicontent');
			//items
			$permission->CanAdd 		= JAccess::check($user->id, 'flexicontent.create', 			'com_flexicontent');
			$permission->CanEdit 		= JAccess::check($user->id, 'flexicontent.editall', 			'com_flexicontent');
			$permission->CanPublish 		= JAccess::check($user->id, 'flexicontent.editall.state', 		'com_flexicontent');
			$permission->CanDelete 		= JAccess::check($user->id, 'flexicontent.deleteall', 			'com_flexicontent');
			$permission->CanOrder	 	= JAccess::check($user->id, 'flexicontent.order', 			'com_flexicontent');
			$permission->CanCopy	 	= JAccess::check($user->id, 'flexicontent.copyitems', 		'com_flexicontent');
			$permission->CanParams	 	= JAccess::check($user->id, 'flexicontent.paramsitem', 		'com_flexicontent');
			$permission->DisplayAllItems	 = JAccess::check($user->id, 'flexicontent.displayallitems',	'com_flexicontent');
			//categories
			$permission->CanCats 		= JAccess::check($user->id, 'flexicontent.managecat',		'com_flexicontent');
			$permission->CanUserCats 	= JAccess::check($user->id, 'flexicontent.usercats',			'com_flexicontent');
			$permission->CanViewTree 	= JAccess::check($user->id, 'flexicontent.viewtree',			'com_flexicontent');
			$permission->CanAddCats 	= JAccess::check($user->id, 'flexicontent.createcat', 		'com_flexicontent');
			$permission->CanEditAllCats 	= JAccess::check($user->id, 'flexicontent.editallcat',			'com_flexicontent');
			$permission->CanDeleteAllCats = JAccess::check($user->id, 'flexicontent.deleteallcat', 		'com_flexicontent');
			$permission->CanPublishAllCats = JAccess::check($user->id, 'flexicontent.editallcat.state', 	'com_flexicontent');
			//files
			$permission->CanFiles	 	= JAccess::check($user->id, 'flexicontent.managefile', 		'com_flexicontent');
			$permission->CanUpload	 	= JAccess::check($user->id, 'flexicontent.uploadfiles', 		'com_flexicontent');
			$permission->CanViewAllFiles	 = JAccess::check($user->id, 'flexicontent.viewallfiles', 		'com_flexicontent');
			//others
			$permission->CanPlugins	 	= JAccess::check($user->id, 'core.manage', 				'com_plugins');
			$permission->CanComments 	= JAccess::check($user->id, 'core.admin', 'root.1')?$CanComments:JAccess::check($user->id, 'core.manage', 'com_jcomments');
		}
		return $permission;
	}
	function checkUserElementsAccess($uid, $action, $section, $force=false) {
		static $permissions;
		if(!isset($permissions[$section.$action]) || $force) {
			$db = &JFactory::getDBO();
			$user = &JFactory::getUser($uid);
			$permissions[$section.$action] = array();
			$query = "SELECT name FROM #__assets WHERE name like 'flexicontent.{$section}.%';";
			$db->setQuery($query);
			$names = $db->loadResultArray();
			foreach($names as $name) {
				$id = str_replace("flexicontent.{$section}.",  "", $name);
				if($user->authorize($action, $name)) {
					$permissions[$section.$action][] = $id;
				}
			}
		}
		return $permissions[$section.$action];
	}
}
?>