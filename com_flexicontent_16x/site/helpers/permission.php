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
			$permission->CanConfig 		= JAccess::check($user->id, 'com_flexicontent.admin', 			'com_flexicontent');
			$permission->CanRights	 	= JAccess::check($user->id, 'com_flexicontent.manage', 			'com_flexicontent');
			$permission->CanTypes 		= JAccess::check($user->id, 'com_flexicontent.managetype',		'com_flexicontent');
			$permission->CanFields 		= JAccess::check($user->id, 'com_flexicontent.fields', 			'com_flexicontent');
			$permission->CanTags 		= JAccess::check($user->id, 'com_flexicontent.tags', 			'com_flexicontent');
			$permission->CanArchives 	= JAccess::check($user->id, 'com_flexicontent.archives', 			'com_flexicontent');
			$permission->CanStats	 	= JAccess::check($user->id, 'com_flexicontent.stats', 			'com_flexicontent');
			$permission->CanTemplates	= JAccess::check($user->id, 'com_flexicontent.templates', 		'com_flexicontent');
			$permission->CanVersion	 	= JAccess::check($user->id, 'com_flexicontent.versioning', 		'com_flexicontent');
			$permission->CanUseTags	= JAccess::check($user->id, 'com_flexicontent.usetags', 			'com_flexicontent');
			//items
			$permission->CanAdd 		= JAccess::check($user->id, 'com_flexicontent.create', 			'com_flexicontent');
			$permission->CanEdit 		= JAccess::check($user->id, 'com_flexicontent.edit', 			'com_flexicontent');
			$permission->CanPublish 		= JAccess::check($user->id, 'com_flexicontent.edit.state', 		'com_flexicontent');
			$permission->CanDelete 		= JAccess::check($user->id, 'com_flexicontent.delete', 			'com_flexicontent');
			$permission->CanOrder	 	= JAccess::check($user->id, 'com_flexicontent.order', 			'com_flexicontent');
			$permission->CanCopy	 	= JAccess::check($user->id, 'com_flexicontent.copyitems', 		'com_flexicontent');
			$permission->CanParams	 	= JAccess::check($user->id, 'com_flexicontent.paramsitem', 		'com_flexicontent');
			//categories
			$permission->CanCats 		= JAccess::check($user->id, 'com_flexicontent.managecat',		'com_flexicontent');
			$permission->CanAddCats 	= JAccess::check($user->id, 'com_flexicontent.createcat', 		'com_flexicontent');
			//files
			$permission->CanFiles	 	= JAccess::check($user->id, 'com_flexicontent.managefile', 		'com_flexicontent');
			$permission->CanUpload	 	= JAccess::check($user->id, 'com_flexicontent.uploadfiles', 		'com_flexicontent');
			$permission->CanViewAllFiles	 = JAccess::check($user->id, 'com_flexicontent.viewallfiles', 		'com_flexicontent');
			//others
			$permission->CanPlugins	 	= JAccess::check($user->id, 'core.manage', 					'com_plugins');
			$permission->CanComments 	= JAccess::check($user->id, 'core.admin', 'root.1')?$CanComments:JAccess::check($user->id, 'core.manage', 'com_jcomments');
		}
		return $permission;
	}
}
?>