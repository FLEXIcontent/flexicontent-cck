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
		// Return already calculated data
		static $permission = null;
		if ($permission && !$force) return $permission;
		
		$user_id = JFactory::getUser()->id;
		
		// Return cached data
		if ( FLEXI_CACHE ) {
			$catscache = JFactory::getCache('com_flexicontent_cats');  // Get desired cache group
			$catscache->setCaching(1); 		              // Force cache ON
			$catscache->setLifeTime(FLEXI_CACHE_TIME);  // Set expire time (default is 1 hour)
			
			$permission = $catscache->get(
				array('FlexicontentHelperPerm', 'getUserPerms'),
				array($user_id)
			);
		}
		
		else {
			// Caching disabled
			$permission = FlexicontentHelperPerm::getUserPerms($user_id);
		}
		
		return $permission;
	}
	
	
	
	static function getUserPerms($user_id = null)
	{
		$cparams   = JComponentHelper::getParams('com_flexicontent');

		// Handle jcomments integration
		$JComments_Installed = JPluginHelper::isEnabled('system', 'jcomments') &&  JPluginHelper::isEnabled('content', 'jcomments');

		// Handle komento integration
		$Komento_Installed = JPluginHelper::isEnabled('system', 'komento') && JPluginHelper::isEnabled('content', 'komento');

		// Find permissions for given user id
		$user = $user_id ? JFactory::getUser($user_id) : JFactory::getUser();  // no user id given, use current user)
		$user_id = $user->id;
		$permission = new stdClass;
		
		/**
		 * This is the Super User Privilege of Global Configuration	(core.admin ACTION allowed on ROOT ASSET: 'root.1')
		 * Alternative way is JAccess::check($user->id, 'core.admin', 'root.1'), but this will fail with emergency root user
		 */
		$permission->SuperAdmin		= $user->authorise('core.admin', 'root.1');
		
		//!!! ALLOWs USERS to change component's Configuration (core.admin ACTION allowed on COMPONENT ASSET: e.g. 'com_flexicontent')
		$permission->CanConfig		= $user->authorise('core.admin', 'com_flexicontent');
				
		//!!! ALLOWs USERS in JOOMLA BACKEND : (not used in J1.5)
		//   (a) to view the FLEXIcontent menu item in Components Menu and
		//   (b) to access the FLEXIcontent component screens (whatever they are allowed to see by individual FLEXIcontent area permissions)
		//       NOTE: the initially installed permissions allows all areas to be managed for J2.5 and none (except for items) for J1.5
		$permission->CanManage		= $user->authorise('core.manage', 			'com_flexicontent');
		
		// ITEMS/CATEGORIES: category-inherited permissions, (NOTE: these are the global settings, so:)
		// *** 1. the action permissions of individual items are checked seperately per item
		// *** 2. the view permission is checked via the access level of each item
		// --- *. We will not check the category tree even if SOFT DENY (null)
		// --- *. instead the code that need this can call FlexicontentHelperPerm::getPermAny(...)
		
		$permission->CanAdd       = $user->authorise('core.create', 				'com_flexicontent');
		$permission->CanEdit      = $user->authorise('core.edit', 					'com_flexicontent');
		$permission->CanEditOwn   = $user->authorise('core.edit.own', 			'com_flexicontent');
		$permission->CanPublish   = $user->authorise('core.edit.state',			'com_flexicontent');
		$permission->CanPublishOwn= $user->authorise('core.edit.state.own',	'com_flexicontent');
		$permission->CanDelete		= $user->authorise('core.delete', 				'com_flexicontent');
		$permission->CanDeleteOwn	= $user->authorise('core.delete.own', 		'com_flexicontent');
		
		$permission->CanChangeCat= $user->authorise('flexicontent.change.cat',	'com_flexicontent');
		$permission->CanChangeSecCat= $user->authorise('flexicontent.change.cat.sec',	'com_flexicontent');
		$permission->CanChangeFeatCat= $user->authorise('flexicontent.change.cat.feat',	'com_flexicontent');
		
		// Permission for changing the ACL rules of items and categories that user can edit
		// Currently given to user that can edit component configuration
		$permission->CanRights		= $permission->CanConfig;
		
		// Permission for changing the access level of items and categories that user can edit
		// (a) In J1.5 with FLEXIaccess, this is given to those that can edit the FLEXIaccess configuration
		// (b) In J1.5 without FLEXIaccess, this is given to users being at least an Editor
		// (c) In J2.5, this is the FLEXIcontent component ACTION 'accesslevel'
		$permission->CanAccLvl		= $user->authorise('flexicontent.accesslevel',		'com_flexicontent');
		
		// ITEMS: component controlled permissions
		$permission->DisplayAllItems    = $user->authorise('flexicontent.displayallitems','com_flexicontent'); // (backend) List all items (otherwise only items that can be edited)
		$permission->CanCopy      = $user->authorise('flexicontent.copyitems',  'com_flexicontent'); // (backend) Item Copy Task
		$permission->CanOrder     = $user->authorise('flexicontent.orderitems', 'com_flexicontent'); // (backend) Reorder items inside the category
		$permission->CanParams    = 1; // Legacy permission, we will not use it in FC v3.0.15+
		$permission->CanVersion   = $user->authorise('flexicontent.versioning', 'com_flexicontent'); // (backend) Use item versioning
		$permission->CanArchives  = $user->authorise('flexicontent.managearchives', 'com_flexicontent'); // Allow setting items to Archived state
		
		$permission->AssocAnyTrans      = $user->authorise('flexicontent.assocanytrans',      'com_flexicontent'); // (item edit form) associate any translation
		$permission->EditCreationDate   = $user->authorise('flexicontent.editcreationdate',   'com_flexicontent'); // (item edit form) edit creation date
		$permission->EditCreator        = $user->authorise('flexicontent.editcreator',        'com_flexicontent'); // (item edit form) edit creator (owner)
		$permission->EditPublishUpDown  = $user->authorise('flexicontent.editpublishupdown',  'com_flexicontent'); // (item edit form) edit publish up / down (for non-publishers)
		$permission->IgnoreViewState    = $user->authorise('flexicontent.ignoreviewstate',    'com_flexicontent'); // (Frontend Content Lists) ignore view state
		$permission->RequestApproval    = $user->authorise('flexicontent.requestapproval',    'com_flexicontent'); // (Workflow) Send Approval Requests (for ANY draft items)
		$permission->AutoApproveChanges = $user->authorise('flexicontent.autoapprovechanges', 'com_flexicontent'); // (Workflow) Can publish document changes regardless of edit state
		
		// CATEGORIES: management tab and usage
		$permission->CanCats      = $user->authorise('flexicontent.managecats',  'com_flexicontent'); // (item edit form) view the categories which user cannot assign to items
		$permission->ViewAllCats  = $user->authorise('flexicontent.usercats',    'com_flexicontent'); // (item edit form) view the categories which user cannot assign to items
		$permission->ViewTree     = 1;  // Old (non-used) ACL, we will always displaying of categories as tree
		$permission->MultiCat     = $user->authorise('flexicontent.multicat',    'com_flexicontent'); // (item edit form) allow user to assign items to multiple categories
		
		// REVIEWs: management tab and usage
		$permission->CanReviews       = $user->authorise('flexicontent.managereviews',  'com_flexicontent') && version_compare(FLEXI_VERSION, '3.3.99', '>');
		$permission->CanCreateReviews = $user->authorise('flexicontent.createreviews',  'com_flexicontent') && version_compare(FLEXI_VERSION, '3.3.99', '>');
		
		// TAGS: management tab and usage
		$permission->CanTags       = $user->authorise('flexicontent.managetags',  'com_flexicontent'); // (backend) Allow management of Item Types
		$permission->CanUseTags    = $user->authorise('flexicontent.usetags',     'com_flexicontent'); // edit tag assignments (item form)
		$permission->CanCreateTags = $user->authorise('flexicontent.createtags',  'com_flexicontent'); // create new tags
		
		// VARIOUS management TABS: types, archives, statistics, templates, tags
		$permission->CanTypes      = $user->authorise('flexicontent.managetypes',      'com_flexicontent'); // (backend) Allow management of Item Types
		$permission->CanTemplates  = $user->authorise('flexicontent.managetemplates',  'com_flexicontent'); // (backend) Allow management of Templates
		$permission->CanStats      = $user->authorise('flexicontent.managestats',      'com_flexicontent'); // (backend) Allow management of Statistics
		$permission->CanImport     = $user->authorise('flexicontent.manageimport',     'com_flexicontent'); // (backend) Allow management of (Content) Import
		$permission->CanAppsman    = $permission->CanConfig;
		
		// FIELDS: management tab
		$permission->CanFields      = $user->authorise('flexicontent.managefields', 'com_flexicontent'); // (backend) Allow management of Fields
		$permission->CanCopyFields  = $user->authorise('flexicontent.copyfields',   'com_flexicontent'); // (backend) Field Copy Task
		$permission->CanOrderFields = $user->authorise('flexicontent.orderfields',  'com_flexicontent'); // (backend) Reorder fields inside each item type
		$permission->CanAddField    = $user->authorise('flexicontent.createfield',  'com_flexicontent'); // (backend) Create fields
		$permission->CanEditField   = $user->authorise('flexicontent.editfield',    'com_flexicontent'); // (backend) Edit fields
		$permission->CanDeleteField = $user->authorise('flexicontent.deletefield',  'com_flexicontent'); // (backend) Delete fields
		$permission->CanPublishField= $user->authorise('flexicontent.publishfield', 'com_flexicontent'); // (backend) Publish fields
		
		// FILES: management tab
		$permission->CanFiles        = $user->authorise('flexicontent.managefiles',   'com_flexicontent'); // (backend) Allow management of Files
		$permission->CanUpload       = $user->authorise('flexicontent.uploadfiles',   'com_flexicontent'); // allow user to upload Files
		$permission->CanViewAllFiles = $user->authorise('flexicontent.viewallfiles',  'com_flexicontent'); // allow user to view all Files
		
		// AUTHORS: management tab
		$permission->CanAuthors   = $user->authorise('core.manage', 'com_users');
		$permission->CanGroups    = $permission->CanAuthors;
		
		// SEARCH INDEX: management tab
		$permission->CanIndex     = $permission->CanFields && ($permission->CanAddField || $permission->CanEditField);
		
		// OTHER components permissions
		$permission->CanPlugins   = $user->authorise('core.manage', 'com_plugins');

		$permission->JComments_Installed = $JComments_Installed;
		$permission->Komento_Installed   = $Komento_Installed;

		switch ($cparams->get('comments'))
		{
			case 0:
				$permission->CanComments = false;
				break;

			case 1:
				$permission->CanComments = $JComments_Installed && $user->authorise('core.manage', 'com_jcomments');
				break;

			case 3:
				$permission->CanComments = $Komento_Installed && $user->authorise('core.manage', 'com_komento');
				break;

			default:
				$permission->CanComments = true;
				break;
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
		// Return cached data
		$user_id = $user ? $user->id : JFactory::getUser()->id;
		if (FLEXI_CACHE) {
			$catscache = JFactory::getCache('com_flexicontent_cats');  // Get desired cache group
			$catscache->setCaching(1); 		              // Force cache ON
			$catscache->setLifeTime(FLEXI_CACHE_TIME);  // set expire time (default is 1 hour)
			
			$allowedCats = $catscache->get(
				array('FlexicontentHelperPerm', '_getAllowedCats'),
				array($user_id, $actions_allowed, $require_all, $check_published, $specific_catids, $find_first)
			);
		} else {
			$allowedCats = FlexicontentHelperPerm::_getAllowedCats($user_id, $actions_allowed, $require_all, $check_published, $specific_catids, $find_first);
		}
		
		return $allowedCats;
	}
	
	
	static function _getAllowedCats( $user_id, $actions_allowed, $require_all, $check_published, $specific_catids, $find_first)
	{
		global $globalcats;
		$db = JFactory::getDbo();
		$usercats = array();
		
		// -- passing user_id parameter to this function allows to cache per user
		$user = JFactory::getUser($user_id);
		
		$allcats = FlexicontentHelperPerm::returnAllCats ($check_published, $specific_catids);
		
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
	}
	
	
	/*
	 * Method to return all categories ids checking if published and if in specific subset
	 */
	static function returnAllCats ($check_published, $specific_catids)
	{
		global $globalcats;
		$usercats = array();
		
		if ($specific_catids) {
			foreach ($specific_catids as $k) {
				if (!$check_published || $globalcats[$k]->published==1) {
					$usercats[] = $k;
				}
			}
		} else {
			foreach ($globalcats as $k => $v) {
				if(!$check_published || $v->published==1) {
					$usercats[] = $k;
				}
			}
		}
		$usercats = array_unique($usercats);
		return $usercats;
	}
	
		
	/*
	 * Lookups the SECTION ids on which the given USER can perform the given ACTION,
	 * !! CURRENTLY UNUSED not implemented properly, e.g. items may not have an asset, and this is not taken in to account, maybe will be useful in the future
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
			$db = JFactory::getDbo();
			$user = JFactory::getUser($uid);
			
			// Query the assets table to retrieve the asset names for the specified section
			$query = "SELECT name FROM #__assets WHERE name like '{$asset_partial}.%';";
			$db->setQuery($query);
			$names = $db->loadColumn();
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
	
	
	
	/*
	 * Lookups the ACTION for the content type
	 *
	 * @access	public
	 * @param	integer		$type_id		The TYPE ID
	 * @param	string		$rule				The ACL rule name
	 * @param	string		$type				A type object
	 *
	 * @return boolean							True if action is allowd
	 * @since	3.0
	 * 
	 */
	static function checkTypeAccess($type_id, $rule, $type=null)
	{
		static $cache;
		
		// no type ID, return true, (thus item form will show even if it has empty TYPE selector)
		if (!$type_id)
		{
			return true;
		}

		if ( !isset($cache[$rule][$type_id]) )
		{
			$asset = 'com_flexicontent.type.'.$type_id;

			if ($rule=='core.create')
			{
				$cache[$rule][$type_id] = ($type ? ! $type->itemscreatable : true) || $user->authorise($rule, $asset);
			}

			// A non-implemented ACL (yet), just return TRUE
			else
			{
				$cache[$rule][$type_id] = true;  // $user->authorise($rule, $asset);
			}
		}

		return $cache[$rule][$type_id];
	}


	/*
	 * Lookup if the given ACL action is allowed in at least 1 category for a given user
	 *
	 * @access	public
	 * @param	string		$user_id		The ACL action name
	 * @param	integer		$action			The USER ID
	 *
	 * @return boolean , true if allowed
	 * @since	3.1.0
	 * 
	 */
	static function getPermAny($action = null, $user_id = null, $assetname='com_flexicontent')
	{
		// Find permissions for given user id
		$user = $user_id ? JFactory::getUser($user_id) : JFactory::getUser();  // no user id given, use current user)
		$user_id = $user->id;
		
		// Return already calculated data
		static $permsAny = array();
		if ( isset($permsAny[$user_id][$action]) ) return $permsAny[$user_id][$action];
		
		$permsAny[$user_id][$action] = $user->authorise($action, $assetname);

		if (!$permsAny[$user_id][$action])
		{
			$permsAny[$user_id][$action] = JAccess::check($user_id, $action, $assetname);
		}

		//if ($permsAny[$user_id][$action] === NULL)  // Soft deny check was broken in J3.6.5, use JAccess::check above
		if (!$permsAny[$user_id][$action])
		{
			// Get Allowed Cats which is cacheable !
			$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array($action), $require_all=true, $check_published = true, false, $find_first = true );
			$permsAny[$user_id][$action] = count($allowedcats) > 0;
		}
		
		return $permsAny[$user_id][$action];
	}
}
