<?php

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

defined( '_JEXEC' ) or die( 'Restricted access' );

class FlexicontentHelperPerm {
	
	
	/*
	 * Calculates global component PERMISSIONS of the current USER
	 *
	 * @access	public
	 * @param	boolean		$force		Forces the recalculation of the PERMISSIONS
	 *
	 * @return array							The array of user PERMISSIONS 
	 * @since	2.0
	 * 
	 */
	function getPerm($force = false)
	{
		static $permission = null;
		
		if(!$permission || $force) {
			// handle jcomments integration
			$CanComments 	= JPluginHelper::isEnabled('system', 'jcomments.system') || JPluginHelper::isEnabled('system', 'jcomments');
			
			$user =& JFactory::getUser();
			$core_admin = JAccess::check($user->id, 'core.admin', 'root.1');
			$permission = new stdClass;
			
			// !!! This is the Super User Privelege (can do anything, all other permissions are ignored)
			$permission->CanConfig 		= $user->authorise('core.admin', 			'com_flexicontent');	
					
			//!!! ALLOWs USERS in JOOMLA BACKEND :
			//   (a) to view the FLEXIcontent menu item in Components Menu and
			//   (b) to access the FLEXIcontent component screens (whatever they are allowed to see by individual FLEXIcontent area permissions)
			//       NOTE: the initially installed permissions allows all areas to be managed
			$permission->CanManage		= $user->authorise('core.manage', 			'com_flexicontent');
			
			// ITEMS/CATEGORIES: category-inherited permissions, (NOTE: these are the global settings, so:)
			// *** 1. the action permissions of individual items are checked seperately per item
			// *** 2. the view permission is checked via the access level of each item
			$permission->CanAdd 			= $user->authorise('core.create', 		'com_flexicontent');
			$permission->CanDelete 		= $user->authorise('core.delete', 		'com_flexicontent');
			$permission->CanDeleteOwn	= $user->authorise('core.delete.own', 'com_flexicontent');
			$permission->CanEdit 			= $user->authorise('core.edit', 			'com_flexicontent');
			$permission->CanEditOwn 	= $user->authorise('core.edit.own', 	'com_flexicontent');
			$permission->CanPublish 	= $user->authorise('core.edit.state', 'com_flexicontent');
			
			// ITEMS: component controlled permissions
			$permission->DisplayAllItems	= $user->authorise('flexicontent.displayallitems',	'com_flexicontent');		// List items (in backend) that can be edited
			$permission->CanCopy				 	= $user->authorise('flexicontent.copyitems',				'com_flexicontent');		// Multiple Copy Item Task
			$permission->CanOrder	 				= $user->authorise('flexicontent.order', 						'com_flexicontent');		// Reorder items inside the category
			$permission->CanParams				= $user->authorise('flexicontent.paramsitem', 			'com_flexicontent');		// Edit item parameters like meta data and template parameters
			$permission->CanVersion				= $user->authorise('flexicontent.versioning',				'com_flexicontent');		// Use item versioning
			
			// CATEGORIES: management tab and usage
			$permission->CanCats 					= $user->authorise('flexicontent.managecats',				'com_flexicontent');
			$permission->CanUserCats 			= $user->authorise('flexicontent.usercats',					'com_flexicontent');
			$permission->CanViewTree 			= $user->authorise('flexicontent.viewtree',					'com_flexicontent');
			$permission->MultiCat					= $user->authorise('flexicontent.multicat',					'com_flexicontent');
			// !!! new ... TODO: add the use of them
			//$permission->CanOrderCat	= $user->authorise('flexicontent.deleteowncat',		'com_flexicontent');
			//$permission->CanDeleteOwnCat	= $user->authorise('flexicontent.deleteowncat',		'com_flexicontent');
			
			// TAGS: management tab and usage
			$permission->CanTags 			= $user->authorise('flexicontent.managetags',	'com_flexicontent');  // allow tags management
			$permission->CanUseTags		= $user->authorise('flexicontent.usetags', 		'com_flexicontent');  // edit already assigned Tags of items
			$permission->CanNewTags		= $user->authorise('flexicontent.newtags',		'com_flexicontent');  // add new Tags to items
			
			// VARIOUS management TABS: types, fields, archives, statistics, templates, tags
			$permission->CanTypes 		= $user->authorise('flexicontent.managetypes',			'com_flexicontent');
			// UNUSED ... TODO: add the use of them
			//$permission->CanAddType 		= $user->authorise('flexicontent.createtype',			'com_flexicontent');
			//$permission->CanDeleteType 	= $user->authorise('flexicontent.deletetype',			'com_flexicontent');
			//$permission->CanEditType 		= $user->authorise('flexicontent.edittype',				'com_flexicontent');
			//$permission->CanPublishType	= $user->authorise('flexicontent.edittype.state',	'com_flexicontent');
			$permission->CanFields 		= $user->authorise('flexicontent.managefields', 		'com_flexicontent');
			$permission->CanArchives 	= $user->authorise('flexicontent.managearchives', 	'com_flexicontent');
			$permission->CanTemplates	= $user->authorise('flexicontent.managetemplates', 	'com_flexicontent');
			$permission->CanStats			= $user->authorise('flexicontent.managestats', 			'com_flexicontent');
			
			// FILES: management tab
			$permission->CanFiles	 				= $user->authorise('flexicontent.managefiles', 		'com_flexicontent');
			$permission->CanUpload	 			= $user->authorise('flexicontent.uploadfiles', 		'com_flexicontent');
			$permission->CanViewAllFiles	= $user->authorise('flexicontent.viewallfiles',		'com_flexicontent');
			
			// OTHER components permissions
			$permission->CanPlugins	 	= $user->authorise('core.manage', 'com_plugins');
			$permission->CanComments 	= $CanComments && $user->authorise('core.manage', 'com_jcomments');
		}
		
		// Config permission overrides the other
		if ($permission->CanConfig) {
			foreach($permission as $key => $value) {
				$permission->{$key} = 1;
			}
		}

		return $permission;
	}
	
	/*
	 * Lookups the SECTION ids on which the given USER can perform the given ACTION
	 *
	 * @access	public
	 * @param	integer		$uid			The USER id
	 * @param	string		$action		The ACTION name
	 * @param	string		$section	The SECTION for which to lookup SECTION ids
	 * @param	boolean		$force		Forces the recalculation of SECTION ids
	 *
	 * @return array							The array of SECTION ids (categories, items, fields ...)
	 * @since	2.0
	 * 
	 */
	function checkUserElementsAccess($uid, $action, $section, $force=false)
	{
		// $elements[$uid][$section][$action] is an array of SECTION id(s)
		// that the USER with ID {$uid} can perform the given ACTION {$action}
		static $elements = array();
		
		// For compatibility reasons, we use 'com_content' assets for both categories and items section
		$extension = ($section == 'category' || $section == 'item' ) ? 'com_content' : 'com_flexicontent';
		
		// For compatibility reasons, we use 'article' assets for both items
		$dbsection = ($section == 'item') ? 'article' : $section;
		
		// Create the PARTIAL asset NAME excluding the final id part
		$asset_partial = "{$extension}.{$dbsection}";
		
		if(!isset($elements[$uid][$section][$action]) || $force) {
			$db = &JFactory::getDBO();
			$user = &JFactory::getUser($uid);
			$elements[$uid][$section][$action] = array();
			$query = "SELECT name FROM #__assets WHERE name like '{$asset_partial}.%';";
			$db->setQuery($query);
			$names = $db->loadResultArray();
			if ($db->getErrorNum()) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$db->getErrorMsg()."\n"),'error');
			}
			$elements[$uid][$section][$action] = array();
			foreach($names as $name) {
				$id = str_replace("{$extension}.{$dbsection}.",  "", $name);
				if($user->authorize($action, $name)) {
					$elements[$uid][$section][$action][] = $id;
				}
			}
		}
		return $elements[$uid][$section][$action];
	}
	
	
	/*
	 * Lookups the ACTIONS that the given USER can perform on the given ASSET
	 *
	 * @access	public
	 * @param	integer		$uid				The USER ID
	 * @param	string		$section		The SECTION name of the ASSET
	 * @param	string		$id					The ID of the ASSET
	 * @param	boolean		$force			Forces the recalculation of ACTIONS
	 * @param	boolean		$recursive	Indicates to use heritage for calculating allowed ACTIONS
	 *
	 * @return array								The array of ACTION names (create, edit, edit.own ...)
	 * @since	2.0
	 * 
	 */
	function checkAllItemAccess($uid, $section, $id, $force=false, $recursive = false)
	{
		// $actions[$uid][$asset] is an array of ACTION names
		// allowed for USER with ID {$uid} on the ASSET with name {$asset}
		static $actions = array();
		
		// For compatibility reasons, we use 'com_content' assets for both categories and items section
		$extension = ($section == 'category' || $section == 'item' ) ? 'com_content' : 'com_flexicontent';
		
		// For compatibility reasons, we use 'article' assets for both items
		$dbsection = ($section == 'item') ? 'article' : $section;
		
		// Create the asset name
		$asset = "{$extension}.{$dbsection}.{$id}";
		
		if(!isset($actions[$uid][$asset]) || $force) {
			$db = &JFactory::getDBO();
			$user = &JFactory::getUser($uid);
			
			// Build the database query to get the rules for the asset.
			$query	= $db->getQuery(true);
			$query->select($recursive ? 'b.rules' : 'a.rules');
			$query->from('#__assets AS a');
			$query->where('name="'.$asset.'"');
			
			// If we want the rules cascading up to the global asset node we need a self-join.
			if ($recursive) {
				$query->leftJoin('#__assets AS b ON b.lft <= a.lft AND b.rgt >= a.rgt');
				$query->order('b.lft');
			}
			
			// Execute the query and load the rules from the result.
			$db->setQuery($query);
			$result	= $db->loadColumn();
			if ($db->getErrorNum()) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$db->getErrorMsg()."\n"),'error');
			}
			$rules_arr = array();
			foreach ($result as $rule_string) {
				$rule_string = str_replace($dbsection, $section, $rule_string);
				$rules_arr[] = $rule_string;
			}

			// Instantiate a JRules object for the asset rules
			$rules	= new JRules;
			$rules->mergeCollection($rules_arr);
			
			// Get user's groups (user identities)
			$groups = $user->getAuthorisedGroups();   //SAME AS: $groups = JAccess::getGroupsByUser($uid, $recursive);
			
			// Populate allowed user actions on the asset
			$actions[$uid][$asset] = array();
			$action_start = ($section == 'category' || $section == 'item' ) ? 'core.' : 'flexicontent.';
			foreach($rules->getData() as $action=>$data) {
				if($user->authorize('core.admin', 'com_flexicontent') || $rules->allow($action, $groups)) {
					$action_shortname = str_replace($action_start, "", $action);
					$actions[$uid][$asset][] = $action_shortname;
				}
			}
		}
		return $actions[$uid][$asset];
	}
	
	/**
	 * Lookups the categories (their IDs), that the user has access to perforn the specified action(s)
	 *
	 * @param array		$action_names		The required actions
	 * @param bool		$require_all		True to require --all-- (Logical AND) or false to require --any-- (Logical OR)
	 *
	 * @return array									The category IDs
	 * @since	2.0
	 */
	function getCats($action_names=array('core.create', 'core.edit', 'core.edit.own'), $require_all=true) {
		static $usercats = null;
		$db = & JFactory::getDBO();
		$user = & JFactory::getUser();
		
		$query = 'SELECT c.id '
			. ' FROM #__categories AS c'
			. ' WHERE c.published = 1';
		$db->setQuery($query);
		$allcats = $db->loadResultArray();
		$usercats = array();
		foreach ($allcats as $category_id)
		{
			// Construct asset name for the category
			$asset = 'com_content.category.'.$category_id;
			
			// We start with FALSE for OR and TRUE for AND
			$has_access = $require_all ? false : true;
			
			// Check all actions with Logical OR or Logical AND
			foreach ($action_names as $action_name)
			{
				if ($require_all) {
					$has_access = $has_access | $user->authorise($action_name, $asset);
				} else {
					$has_access = $has_access & $user->authorise($action_name, $asset);
				}
			}
			if ($has_access) $usercats[] = $category_id;
		}
		return $usercats;
	}

}


class FlexiAccess extends JAccess {
	public static function print_asset($asset='core.admin') {
		/*if (empty(self::$assetRules[$asset])) {
			self::$assetRules[$asset] = self::getAssetRules($asset, true);
		}*/
		echo "<pre style='font-size:12px;'>";
		print_r(self::$assetRules);
		echo "</pre>";
	}	
}

?>
