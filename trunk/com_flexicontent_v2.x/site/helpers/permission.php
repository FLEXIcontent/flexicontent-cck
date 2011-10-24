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
			$user =& JFactory::getUser();
			$check = JAccess::check($user->id, 'core.admin', 'root.1');
			$permission = new stdClass;
			//component
			// !!! ALLOWs USERS to ALTER component access privileges
			$permission->CanConfig 		= ($check || JAccess::check($user->id, 'core.admin', 			'com_flexicontent'));			
			//!!! ALLOWs USERS in JOOMLA BACKEND : (a) to view FLEXIcontent menu item in Components Menu and (b) access the FLEXIcontent component screens (whatever they can see)
			$permission->CanUseFlexi	= ($check || JAccess::check($user->id, 'core.manage', 			'com_flexicontent'));
			$permission->CanTypes 		= ($check || JAccess::check($user->id, 'flexicontent.managetype',		'com_flexicontent'));
			$permission->CanFields 		= ($check || JAccess::check($user->id, 'flexicontent.fields', 			'com_flexicontent'));
			$permission->CanArchives 	= ($check || JAccess::check($user->id, 'flexicontent.archives', 			'com_flexicontent'));
			$permission->CanStats	 	= ($check || JAccess::check($user->id, 'flexicontent.stats', 			'com_flexicontent'));
			$permission->CanTemplates	= ($check || JAccess::check($user->id, 'flexicontent.templates', 		'com_flexicontent'));
			$permission->CanVersion	 	= ($check || JAccess::check($user->id, 'flexicontent.versioning', 		'com_flexicontent'));
			$permission->CanTags 		= ($check || JAccess::check($user->id, 'flexicontent.tags', 			'com_flexicontent'));
			$permission->CanUseTags	= ($check || JAccess::check($user->id, 'flexicontent.usetags', 			'com_flexicontent'));
			$permission->CanNewTag		= ($check || JAccess::check($user->id, 'flexicontent.newtag',			'com_flexicontent'));
			//items
			$permission->CanAdd 		= ($check || JAccess::check($user->id, 'flexicontent.create', 			'com_flexicontent'));
			$permission->CanEdit 		= ($check || JAccess::check($user->id, 'flexicontent.editall', 			'com_flexicontent'));
			$permission->CanPublish 		= ($check || JAccess::check($user->id, 'flexicontent.editall.state', 		'com_flexicontent'));
			$permission->CanDelete 		= ($check || JAccess::check($user->id, 'flexicontent.deleteall', 			'com_flexicontent'));
			$permission->CanOrder	 	= ($check || JAccess::check($user->id, 'flexicontent.order', 			'com_flexicontent'));
			$permission->CanCopy	 	= ($check || JAccess::check($user->id, 'flexicontent.copyitems', 		'com_flexicontent'));
			$permission->CanParams	 	= ($check || JAccess::check($user->id, 'flexicontent.paramsitem', 		'com_flexicontent'));
			$permission->DisplayAllItems	 = ($check || JAccess::check($user->id, 'flexicontent.displayallitems',	'com_flexicontent'));
			//categories
			$permission->CanCats 		= ($check || JAccess::check($user->id, 'flexicontent.managecat',		'com_flexicontent'));
			$permission->CanUserCats 	= ($check || JAccess::check($user->id, 'flexicontent.usercats',			'com_flexicontent'));
			$permission->CanViewTree 	= ($check || JAccess::check($user->id, 'flexicontent.viewtree',			'com_flexicontent'));
			$permission->CanAddCats 	= ($check || JAccess::check($user->id, 'flexicontent.createcat', 		'com_flexicontent'));
			$permission->CanEditAllCats 	= ($check || JAccess::check($user->id, 'flexicontent.editallcat',			'com_flexicontent'));
			$permission->CanDeleteAllCats = ($check || JAccess::check($user->id, 'flexicontent.deleteallcat', 		'com_flexicontent'));
			$permission->CanPublishAllCats = ($check || JAccess::check($user->id, 'flexicontent.editallcat.state', 	'com_flexicontent'));
			$permission->MultiCat = ($check || JAccess::check($user->id, 'flexicontent.multicat', 	'com_flexicontent'));
			//files
			$permission->CanFiles	 	= ($check || JAccess::check($user->id, 'flexicontent.managefile', 		'com_flexicontent'));
			$permission->CanUpload	 	= ($check || JAccess::check($user->id, 'flexicontent.uploadfiles', 		'com_flexicontent'));
			$permission->CanViewAllFiles	 = ($check || JAccess::check($user->id, 'flexicontent.viewallfiles', 		'com_flexicontent'));
			//others
			$permission->CanPlugins	 	= ($check || JAccess::check($user->id, 'core.manage', 'com_plugins'));
			$permission->CanComments 	= ($check ? $CanComments : JAccess::check($user->id, 'core.manage', 'com_jcomments'));
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
	function checkAllItemAccess($uid, $section, $id, $force=false, $recursive = false) {
		static $actions;
		if(!isset($actions[$id]) || $force) {
			$db = &JFactory::getDBO();
			//$user = &JFactory::getUser($uid);
			$query = "SELECT rules FROM #__assets WHERE name='flexicontent.{$section}.{$id}';";
			$db->setQuery($query);
			$rule_string = $db->loadResult();
			$rule = new JRules($rule_string);
			$actions[$id] = array();
			//$groups = $user->getAuthorisedGroups();
			$groups = JAccess::getGroupsByUser($uid, $recursive);
			foreach($rule->getData() as $action=>$data) {
				if($data->allow($groups)) $actions[$id][] = str_replace("flexicontent.", "", $action);
			}
		}
		return $actions[$id];
	}
}
?>
