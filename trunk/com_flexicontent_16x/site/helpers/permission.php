<?php

defined( '_JEXEC' ) or die( 'Restricted access' );
class FlexicontentHelperPerm{
	function getPerm($force = false) {
		$user = &JFactory::getUser();
		static $permission;
		if(!$permission || $force) {
			$permission = new stdClass;
			$permission->CanAdd 		= JAccess::check($user->id, 'core.create', 		'com_content');
			$permission->CanEdit 		= JAccess::check($user->id, 'core.edit', 			'com_content');
			$permission->CanPublish 		= JAccess::check($user->id, 'core.edit.state', 		'com_content');
			$permission->CanDelete 		= JAccess::check($user->id, 'core.delete', 		'com_content');
			$permission->CanCats 		= JAccess::check($user->id, 'core.categories', 		'com_flexicontent');
			$permission->CanTypes 		= JAccess::check($user->id, 'core.types', 			'com_flexicontent');
			$permission->CanFields 		= JAccess::check($user->id, 'core.fields', 			'com_flexicontent');
			$permission->CanTags 		= JAccess::check($user->id, 'core.tags', 			'com_flexicontent');
			$permission->CanArchives 	= JAccess::check($user->id, 'core.archives', 		'com_flexicontent');
			$permission->CanFiles	 	= JAccess::check($user->id, 'core.files', 			'com_flexicontent');
			$permission->CanStats	 	= JAccess::check($user->id, 'core.stats', 			'com_flexicontent');
			$permission->CanTemplates	= JAccess::check($user->id, 'core.templates', 		'com_flexicontent');
			$permission->CanRights	 	= JAccess::check($user->id, 'core.manage', 		'com_flexicontent');
			$permission->CanOrder	 	= JAccess::check($user->id, 'core.order', 			'com_flexicontent');
			$permission->CanCopy	 	= JAccess::check($user->id, 'core.copyitems', 		'com_flexicontent');
			$permission->CanParams	 	= JAccess::check($user->id, 'core.paramsitems', 	'com_flexicontent');
			$permission->CanVersion	 	= JAccess::check($user->id, 'core.versioning', 		'com_flexicontent');
			$permission->CanUseTags	= JAccess::check($user->id, 'core.usetags', 		'com_flexicontent');
		}
		return $permission;
	}
}
?>