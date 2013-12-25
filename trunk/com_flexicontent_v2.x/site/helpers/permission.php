<?php

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

defined( '_JEXEC' ) or die( 'Restricted access' );

class FlexicontentHelperPerm
{
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
	static function getPerm($force = false)
	{
		static $permission = null;
		
		if(!$permission || $force)
		{
			// handle jcomments integration
			if (JPluginHelper::isEnabled('system', 'jcomments')) {
				$Comments_Enabled 	= 1;
				$destpath		= JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'plugins';
				$dest 			= $destpath.DS.'com_flexicontent.plugin.php';
				$source 		= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'jcomments'.DS.'com_flexicontent.plugin.php';
				
				jimport('joomla.filesystem.file');
				if (!JFile::exists($dest)) {
					if (!JFolder::exists($destpath)) { 
						if (!JFolder::create($destpath)) { 
							JError::raiseWarning(100, JText::_('FLEXIcontent: Unable to create jComments plugin folder'));
						}
					}
					if (!JFile::copy($source, $dest)) {
						JError::raiseWarning(100, JText::_('FLEXIcontent: Unable to copy jComments plugin'));
					} else {
						$mainframe->enqueueMessage(JText::_('Copied FLEXIcontent jComments plugin'));
					}
				}
			} else {
				$Comments_Enabled 	= 0;
			}
			
			$user = JFactory::getUser();
			$permission = new stdClass;
			
			// !!! This is the Super User Privelege of GLOBAL Configuration		(==> (for J2.5) core.admin ACTION allowed on ROOT ASSET: 'root.1')
			$permission->SuperAdmin		= JAccess::check($user->id, 'core.admin', 'root.1');
			
			//!!! ALLOWs USERS to change component's CONFIGURATION						(==> (for J2.5) core.admin ACTION allowed on COMPONENT ASSET: e.g. 'com_flexicontent')
			$permission->CanConfig		= $user->authorise('core.admin', 				'com_flexicontent');
					
			//!!! ALLOWs USERS in JOOMLA BACKEND : (not used in J1.5)
			//   (a) to view the FLEXIcontent menu item in Components Menu and
			//   (b) to access the FLEXIcontent component screens (whatever they are allowed to see by individual FLEXIcontent area permissions)
			//       NOTE: the initially installed permissions allows all areas to be managed for J2.5 and none (except for items) for J1.5
			$permission->CanManage		= $user->authorise('core.manage', 			'com_flexicontent');
			
			// ITEMS/CATEGORIES: category-inherited permissions, (NOTE: these are the global settings, so:)
			// *** 1. the action permissions of individual items are checked seperately per item
			// *** 2. the view permission is checked via the access level of each item
			// --- *. We will check for SOFT DENY, and then try to find the FIRST ALLOWED CATEGORY FOR EACH ACTION
			
			$permission->CanAdd     = $user->authorise('core.create', 				'com_flexicontent');
			if ($permission->CanAdd === NULL) {
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.create'), $require_all=true, $check_published = true, false, $find_first = true );
				$permission->CanAdd = count($allowedcats) > 0;
			}
			
			$permission->CanEdit    = $user->authorise('core.edit', 					'com_flexicontent');
			if ($permission->CanEdit === NULL) {
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.edit'), $require_all=true, $check_published = true, false, $find_first = true );
				$permission->CanEdit = count($allowedcats) > 0;
			}
			
			$permission->CanEditOwn = $user->authorise('core.edit.own', 			'com_flexicontent');
			if ($permission->CanEditOwn === NULL) {
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.edit.own'), $require_all=true, $check_published = true, false, $find_first = true );
				$permission->CanEditOwn = count($allowedcats) > 0;
			}
			
			$permission->CanPublish = $user->authorise('core.edit.state',			'com_flexicontent');
			if ($permission->CanPublish === NULL) {
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.edit.state'), $require_all=true, $check_published = true, false, $find_first = true );
				$permission->CanPublish = count($allowedcats) > 0;
			}
			
			$permission->CanPublishOwn= $user->authorise('core.edit.state.own',	'com_flexicontent');
			if ($permission->CanPublishOwn === NULL) {
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.edit.state.own'), $require_all=true, $check_published = true, false, $find_first = true );
				$permission->CanPublishOwn = count($allowedcats) > 0;
			}
			
			$permission->CanDelete		= $user->authorise('core.delete', 				'com_flexicontent');
			if ($permission->CanDelete === NULL) {
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.delete'), $require_all=true, $check_published = true, false, $find_first = true );
				$permission->CanDelete = count($allowedcats) > 0;
			}
			
			$permission->CanDeleteOwn	= $user->authorise('core.delete.own', 		'com_flexicontent');
			if ($permission->CanDeleteOwn === NULL) {
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.delete.own'), $require_all=true, $check_published = true, false, $find_first = true );
				$permission->CanDeleteOwn = count($allowedcats) > 0;
			}
			
			// Permission for changing the access level of items and categories that user can edit
			// (a) In J1.5, this is the FLEXIaccess component access permission, and
			// (b) In J2.5, this is the FLEXIcontent component ACTION 'accesslevel'
			$permission->CanRights		= $user->authorise('flexicontent.accesslevel',		'com_flexicontent');
			
			// ITEMS: component controlled permissions
			$permission->DisplayAllItems		= $user->authorise('flexicontent.displayallitems','com_flexicontent'); // (backend) List all items (otherwise only items that can be edited)
			$permission->CanCopy			= $user->authorise('flexicontent.copyitems',	'com_flexicontent'); // (backend) Item Copy Task
			$permission->CanOrder			= $user->authorise('flexicontent.orderitems',	'com_flexicontent'); // (backend) Reorder items inside the category
			$permission->CanParams		= $user->authorise('flexicontent.paramsitem',	'com_flexicontent'); // (backend) Edit item parameters like meta data and template parameters
			$permission->CanVersion		= $user->authorise('flexicontent.versioning',	'com_flexicontent'); // (backend) Use item versioning
			
			$permission->AssocAnyTrans		= $user->authorise('flexicontent.assocanytrans',		'com_flexicontent'); // (item edit form) associate any translation
			$permission->EditCreationDate	= $user->authorise('flexicontent.editcreationdate',	'com_flexicontent'); // (item edit form) edit creation date (frontend)
			$permission->IgnoreViewState	= $user->authorise('flexicontent.ignoreviewstate',	'com_flexicontent'); // (Frontend Content Lists) ignore view state
			
			// CATEGORIES: management tab and usage
			$permission->CanCats			= $user->authorise('flexicontent.managecats',	'com_flexicontent'); // (item edit form) view the categories which user cannot assign to items
			$permission->ViewAllCats	= $user->authorise('flexicontent.usercats',		'com_flexicontent'); // (item edit form) view the categories which user cannot assign to items
			$permission->ViewTree			= $user->authorise('flexicontent.viewtree',		'com_flexicontent'); // (item edit form) view categories as tree instead of flat list
			$permission->MultiCat			= $user->authorise('flexicontent.multicat',		'com_flexicontent'); // (item edit form) allow user to assign each item to multiple categories
			$permission->CanAddCats		= $permission->CanAdd && $permission->CanCats;
			
			// TAGS: management tab and usage
			$permission->CanTags			= $user->authorise('flexicontent.managetags',	'com_flexicontent'); // (backend) Allow management of Item Types
			$permission->CanUseTags		= $user->authorise('flexicontent.usetags', 		'com_flexicontent'); // edit already assigned Tags of items
			$permission->CanNewTags		= $user->authorise('flexicontent.newtags',		'com_flexicontent'); // add new Tags to items
			
			// VARIOUS management TABS: types, archives, statistics, templates, tags
			$permission->CanTypes			= $user->authorise('flexicontent.managetypes',			'com_flexicontent'); // (backend) Allow management of Item Types
			$permission->CanArchives	= $user->authorise('flexicontent.managearchives', 	'com_flexicontent'); // (backend) Allow management of Archives
			$permission->CanTemplates	= $user->authorise('flexicontent.managetemplates',	'com_flexicontent'); // (backend) Allow management of Templates
			$permission->CanStats			= $user->authorise('flexicontent.managestats', 			'com_flexicontent'); // (backend) Allow management of Statistics
			$permission->CanImport		= $user->authorise('flexicontent.manageimport',			'com_flexicontent'); // (backend) Allow management of (Content) Import
			
			// FIELDS: management tab
			$permission->CanFields			= $user->authorise('flexicontent.managefields', 'com_flexicontent'); // (backend) Allow management of Fields
			$permission->CanCopyFields	= $user->authorise('flexicontent.copyfields', 	'com_flexicontent'); // (backend) Field Copy Task
			$permission->CanOrderFields	= $user->authorise('flexicontent.orderfields', 	'com_flexicontent'); // (backend) Reorder fields inside each item type
			$permission->CanAddField		= $user->authorise('flexicontent.createfield', 	'com_flexicontent'); // (backend) Create fields
			$permission->CanEditField		= $user->authorise('flexicontent.editfield', 		'com_flexicontent'); // (backend) Edit fields
			$permission->CanDeleteField	= $user->authorise('flexicontent.deletefield', 	'com_flexicontent'); // (backend) Delete fields
			$permission->CanPublishField= $user->authorise('flexicontent.publishfield', 'com_flexicontent'); // (backend) Publish fields
			
			// FILES: management tab
			$permission->CanFiles				= $user->authorise('flexicontent.managefiles', 	'com_flexicontent'); // (backend) Allow management of Files
			$permission->CanUpload	 		= $user->authorise('flexicontent.uploadfiles', 	'com_flexicontent'); // allow user to upload Files
			$permission->CanViewAllFiles= $user->authorise('flexicontent.viewallfiles',	'com_flexicontent'); // allow user to view all Files
			
			// AUTHORS: management tab
			$permission->CanAuthors		= $user->authorise('core.manage', 'com_users');
			
			// SEARCH INDEX: management tab
			$permission->CanIndex			= $permission->CanFields && ($permission->CanAddField || $permission->CanEditField);
			
			// OTHER components permissions
			$permission->CanPlugins	 	= $user->authorise('core.manage', 'com_plugins');
			$permission->CanComments 	= $user->authorise('core.manage', 'com_jcomments');
			$permission->CanComments	=	$permission->CanComments && $Comments_Enabled;
			
			// Global parameter to force always displaying of categories as tree
			if (JComponentHelper::getParams('com_flexicontent')->get('cats_always_astree', 1)) {
				$permission->ViewTree = 1;
			}
		}
		
		return $permission;
	}
	
	
	/**
	 * Lookups the categories (their IDs), that the user has access to perforn the specified action(s)
	 *
	 * @param object	$user             The user on which to check privileges
	 * @param array		$actions_allowed  The required actions
	 * @param bool		$require_all      True to require --all-- (Logical AND) or false to require --any-- (Logical OR)
	 * @param bool		$check_published  True to include only published categories
	 *
	 * @return array									The category IDs
	 * @since	2.0
	 */
	static function getAllowedCats( &$user, $actions_allowed=array('core.create', 'core.edit', 'core.edit.own'), $require_all=true, $check_published = false, $specific_catids=false, $find_first = false )
	{
		global $globalcats;
		$db = JFactory::getDBO();
		$usercats = array();
		
		if (FLEXI_J16GE)
		{
			// *** J1.6+ ***
			$query = 'SELECT c.id '
				. ' FROM #__categories AS c'
				. ' WHERE extension='.$db->Quote(FLEXI_CAT_EXTENSION)
				. ($check_published ? '  AND c.published = 1 ' : '')
				. ($specific_catids ? '  AND c.id IN ('.implode(",", $specific_catids).')' : '')
				;
			$db->setQuery($query);
			$allcats = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			
			foreach ($allcats as $category_id)
			{
				// Construct asset name for the category
				$asset = 'com_content.category.'.$category_id;
				
				// Check all actions with Logical OR or Logical AND
				// We start with FALSE for OR and TRUE for AND
				$has_access = $require_all ? true : false;
				foreach ($actions_allowed as $action_name)
				{
					$has_access = $require_all ? ($has_access && $user->authorise($action_name, $asset)) : ($has_access || $user->authorise($action_name, $asset));
				}
				if ($has_access) {
					$usercats[] = $category_id;
					if ($find_first) break;  // J1.6+ performance consideration skip checking permissions of remaining categories
				}
			}
			return $usercats;
			
		} else if (!FLEXI_ACCESS || $user->gid == 25) {
			
			// *** J1.5 without FLEXIaccess or user is super admin, return all category ids ***
			
			if ($user->gid < 19) return array();  // Less than J1.5 Author
			return FlexicontentHelperPerm::returnAllCats ($check_published, $specific_catids);
			
		} else {
			
			// *** J1.5 with FLEXIaccess ***
			$aro_value = $user->gmid; // FLEXIaccess group
			
			// Create a limit for aco (ACTION privilege)
			$limit_aco = array();
			if ( in_array('core.create',$actions_allowed) )
				$limit_aco[] = 'aco = ' . $db->Quote('add');
			if ( in_array('core.edit',$actions_allowed) )
				$limit_aco[] = 'aco = ' . $db->Quote('edit');
			if ( in_array('core.edit.own',$actions_allowed) )
				$limit_aco[] = 'aco = ' . $db->Quote('editown');
			
			$oper = $require_all ? ' AND ' : ' OR ';
			if  (count($limit_aco) ) {
				$limit_aco = implode($oper, $limit_aco);
			} else {
				$limit_aco = 'aco = ' . $db->Quote('add') . $oper . ' aco = ' . $db->Quote('edit') . $oper . ' aco = ' . $db->Quote('editown');
			}
			
			// We will search for permission all on the given permission (add,edit,editown),
			// if found it means that there are no ACL limitations for given user, aka return all cats
			$query	= 'SELECT COUNT(*) FROM #__flexiaccess_acl'
					. ' WHERE acosection = ' . $db->Quote('com_content')
					. ' AND ( ' . $limit_aco . ' )'
					. ' AND arosection = ' . $db->Quote('users')
					. ' AND aro IN ( ' . $aro_value . ' )'
					. ' AND axosection = ' . $db->Quote('content')
					. ' AND axo = ' . $db->Quote('all')
					;
			$db->setQuery($query);
			
			
			// *** No limitations found, return all category ids ***
			
			if ($db->loadResult()) {
				return FlexicontentHelperPerm::returnAllCats ($check_published, $specific_catids);
			}
			
			// *** Limitations found, check and return category ids with 'create' permission ***
			
			// creating for -content- axosection is 'add' but for -category- axosection is 'submit'
			$limit_aco = str_replace('add', 'submit', $limit_aco);
			
			$query	= 'SELECT axo FROM #__flexiaccess_acl'
					. ' WHERE acosection = ' . $db->Quote('com_content')
					. ' AND ( ' . $limit_aco . ' )'
					. ' AND arosection = ' . $db->Quote('users')
					. ' AND aro IN ( ' . $aro_value . ' )'
					. ' AND axosection = ' . $db->Quote('category')
					. ($specific_catids ? '  AND axo IN ('.implode(",", $specific_catids).')' : '')
					;
			$db->setQuery($query);
			$allowedcats = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			
			$allowedcats = $allowedcats ? $allowedcats : array();
			// we add all descendent to the array
			foreach ($allowedcats as $allowedcat) {
				$usercats[] = $allowedcat;
				if ($globalcats[$allowedcat]->children) {
					foreach ($globalcats[$allowedcat]->descendantsarray as $k => $v) {
						if(!$check_published || $globalcats[$v]->published) {
							$usercats[] = $v;
						}
					}
				}
			}
			$usercats = array_unique($usercats);
			return $usercats;
		}
	}
	
	
	/*
	 * Method to return all categories ids checking if published and if in specific subset
	 */
	function returnAllCats ($check_published, $specific_catids)
	{
		global $globalcats;
		$usercats = array();
		
		if ($specific_catids) {
			foreach ($specific_catids as $k) {
				if (!$check_published || $globalcats[$k]->published) {
					$usercats[] = $k;
				}
			}
		} else {
			foreach ($globalcats as $k => $v) {
				if(!$check_published || $v->published) {
					$usercats[] = $k;
				}
			}
		}
		$usercats = array_unique($usercats);
		return $usercats;
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
	 * @return array							The array of SECTION ids (category ids OR item ids OR fields ids ...)
	 * @since	2.0
	 * 
	 */
	function checkUserElementsAccess($uid, $action, $section, $force=false)
	{
		// $elements[$uid][$section][$action] is an array of SECTION id(s)
		// that the USER with ID {$uid} can perform the specified ACTION {$action}
		static $elements = array();
		
		// For compatibility reasons, we use 'com_content' assets for both categories and items section
		$extension = ($section == 'category' || $section == 'item' ) ? 'com_content' : 'com_flexicontent';
		
		// For compatibility reasons, we use 'article' assets for items
		$dbsection = ($section == 'item') ? 'article' : $section;
		
		// Create the PARTIAL asset NAME excluding the final id part
		$asset_partial = "{$extension}.{$dbsection}";
		
		if(!isset($elements[$uid][$section][$action]) || $force) {
			// Get database and use objects
			$db = JFactory::getDBO();
			$user = JFactory::getUser($uid);
			
			// Query the assets table to retrieve the asset names for the specified section
			$query = "SELECT name FROM #__assets WHERE name like '{$asset_partial}.%';";
			$db->setQuery($query);
			$names = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
			
			// Create an empty array that will contain the sections on which the user can perform the given action
			$elements[$uid][$section][$action] = array();
			
			// Find all section ids (e.g. item ids) that user can perform the given action
			foreach($names as $name) {
				$id = str_replace("{$extension}.{$dbsection}.",  "", $name);
				if($user->authorise($action, $name)) {
					$elements[$uid][$section][$action][] = $id;
				}
			}
		}
		
		return $elements[$uid][$section][$action];  // RETURN the ids
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
	static function checkAllItemAccess($uid, $section, $id, $force=false, $recursive = false)
	{
		// $actions[$uid][$asset] is an array of ACTION names
		// allowed for USER with ID {$uid} on the ASSET with name {$asset}
		static $actions = array();
		
		// For compatibility reasons, we use 'com_content' assets for both categories and items section
		$extension = ($section == 'category' || $section == 'item' ) ? 'com_content' : 'com_flexicontent';
		
		// For compatibility reasons, we use 'article' assets for items
		$dbsection = ($section == 'item') ? 'article' : $section;
		
		// Create the asset name
		$asset = "{$extension}.{$dbsection}.{$id}";
		
			
		if(!isset($actions[$uid][$asset]) || $force) {
			// Get user object
			$user = JFactory::getUser($uid);
			
			// This string will be removed from action names to make them shorter e.g. will make 'core.edit.own' to be 'edit.own'
			$action_start = ($dbsection == 'category' || $dbsection == 'article' ) ? 'core.' : 'flexicontent.';
			
			// Create an empty array that will contain the actions allowed on the asset
			$actions[$uid][$asset] = array();
			
			// Retrieve all available user actions for the section
			$actions_arr = JAccess::getActions('com_flexicontent', $dbsection);
			
			// Find all allowed user actions on the asset
			foreach($actions_arr as $action_data) {
				$action_name = $action_data->name;
				if( $user->authorise($action_name, $asset) ) {
					$action_shortname = str_replace($action_start, "", $action_name);
					$actions[$uid][$asset][] = $action_shortname;
				}
			}
		}
		
		return $actions[$uid][$asset];  // RETURN the (shortened) action names
	}
	
	
	//*******************************************************************************
	// THIS function implementation  works properly too, but the above is more proper
	//*******************************************************************************
	
	/*function checkAllItemAccess($uid, $section, $id, $force=false, $recursive = false)
	{
		// $actions[$uid][$asset] is an array of ACTION names
		// allowed for USER with ID {$uid} on the ASSET with name {$asset}
		static $actions = array();
		
		// For compatibility reasons, we use 'com_content' assets for both categories and items section
		$extension = ($section == 'category' || $section == 'item' ) ? 'com_content' : 'com_flexicontent';
		
		// For compatibility reasons, we use 'article' assets for items
		$dbsection = ($section == 'item') ? 'article' : $section;
		
		// Create the asset name
		$asset = "{$extension}.{$dbsection}.{$id}";
		
		if(!isset($actions[$uid][$asset]) || $force) {
			$user = JFactory::getUser($uid);
			$db = JFactory::getDBO();
			
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
			$rules_string	= $db->loadResult(); // $db->loadColumn();
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
			
			// Instantiate a JRules object for the asset rules
			$rules = new JRules($rules_string);       //SAME AS: $rules = new JRules(); $rules->merge($rules_string);
			
			// Get user's groups (user identities)
			$groups = $user->getAuthorisedGroups();   //SAME AS: $groups = JAccess::getGroupsByUser($uid, $recursive);
			
			// This string will be removed from action names to make them shorter e.g. will make 'core.edit.own' to be 'edit.own'
			$action_start = ($dbsection == 'category' || $dbsection == 'article' ) ? 'core.' : 'flexicontent.';
			
			// Create an empty array that will contain the actions allowed on the asset
			$actions[$uid][$asset] = array();
			
			// Retrieve all available user actions for the section
			$actions_arr = JAccess::getActions('com_flexicontent', $dbsection);
			
			// Find all allowed user actions on the asset
			foreach($actions_arr as $action_data) {
				$action_name = $action_data->name;
				if($rules->allow($action_name, $groups) || $user->authorise($action_name, 'com_flexicontent') ) {
					$action_shortname = str_replace($action_start, "", $action_name);
					$actions[$uid][$asset][] = $action_shortname;
				}
			}
		}
		return $actions[$uid][$asset];
	}*/
	

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
